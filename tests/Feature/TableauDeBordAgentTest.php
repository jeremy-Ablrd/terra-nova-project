<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\Statut;
use App\Enums\TypeDemande;
use App\Models\Alerte;
use App\Models\ApiRequest;
use App\Models\Demande;
use App\Models\JournalActivite;
use App\Models\Service;
use App\Models\User;
use App\Services\Journal;
use App\Services\NovaTerraApi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D19 (données API pour les agents) et F50 (tableau de bord simplifié) : lecture seule, agent uniquement. */
class TableauDeBordAgentTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 12:00:00');
        Model::preventLazyLoading();
        Cache::flush();

        $this->agent = User::factory()->agent()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    private function api(string $code, string $type, int $vague, string $vu, int $xp = 100, int $tri = 1, int $difficulte = 1): ApiRequest
    {
        return ApiRequest::forceCreate([
            'request_code' => $code, 'requester_name' => 'Nom '.$code, 'requester_type' => $type,
            'message_public' => 'Message '.$code, 'difficulty_level' => $difficulte, 'difficulty' => 'Facile',
            'xp_total' => $xp, 'sort_order' => $tri, 'visible_since_wave' => $vague, 'first_seen_at' => $vu, 'payload' => [],
        ]);
    }

    private function requetes(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $action();
        $nombre = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $nombre;
    }

    // --- Tableau de bord ---

    public function test_demandes_indicators_are_exact(): void
    {
        Demande::factory()->statut(Statut::Nouvelle)->create(['created_at' => '2026-10-08 10:00:00']);
        Demande::factory()->statut(Statut::Nouvelle)->create(['created_at' => '2026-10-01 09:00:00']);
        Demande::factory()->statut(Statut::EnCours)->create(['created_at' => '2026-10-09 10:00:00']);
        Demande::factory()->count(3)->statut(Statut::Traitee)->create(['created_at' => '2026-09-01 10:00:00']);
        Demande::factory()->importee('D01')->type(TypeDemande::Institution)->statut(Statut::Nouvelle)->create(['created_at' => '2026-08-01 10:00:00']);

        $this->actingAs($this->agent)->get('/agent')->assertOk()
            ->assertSee('2 nouvelles demandes')
            ->assertSee('1 demande en cours')
            ->assertSee('3 demandes traitées')
            ->assertSee('2 demandes créées ces 7 derniers jours')
            ->assertSee('Plus ancienne demande encore nouvelle')
            ->assertSee('9 jours')
            ->assertSee('2 demandes en attente')   // compteur D17 conservé
            ->assertSee('Centre technique municipal : toutes les demandes')
            ->assertSee('statut=nouvelle', false);
    }

    public function test_singular_and_empty_wording(): void
    {
        $this->actingAs($this->agent)->get('/agent')
            ->assertSee('Aucune nouvelle demande')
            ->assertSee('Aucune demande nouvelle en attente.')
            ->assertSee('Aucune alerte active')
            ->assertSee('Aucun service interrompu')
            ->assertSee('Aucune activité enregistrée pour le moment.');

        Demande::factory()->statut(Statut::Nouvelle)->create(['created_at' => '2026-10-10 08:00:00']);

        $this->actingAs($this->agent)->get('/agent')
            ->assertSee('1 nouvelle demande')
            ->assertSee('1 demande créée ces 7 derniers jours')
            ->assertSee('moins d&#039;un jour', false);
    }

    public function test_platform_indicators_are_exact(): void
    {
        Alerte::factory()->count(2)->create();
        Alerte::factory()->programmee()->create();
        Alerte::factory()->terminee()->create();
        Service::factory()->interrompu()->create();
        Service::factory()->create();
        Service::factory()->interrompu()->create(['actif' => false]);
        User::factory()->count(3)->create();
        User::factory()->admin()->create();

        $this->actingAs($this->agent)->get('/agent')
            ->assertSee('2 alertes actives')
            ->assertSee('1 service interrompu')
            ->assertSee('3 comptes citoyens')
            ->assertSee(route('alertes.index'));
    }

    public function test_recent_activity_shows_the_five_latest_entries_without_personal_data(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Test']);
        foreach (range(1, 7) as $jour) {
            Carbon::setTestNow(sprintf('2026-10-%02d 08:00:00', $jour));
            Journal::enregistrer($admin, ActionJournal::SynchronisationLancee);
        }
        Carbon::setTestNow('2026-10-10 12:00:00');

        $citoyen = User::factory()->create(['name' => 'Habitante Secrète', 'email' => 'secrete@example.test']);
        $demande = Demande::factory()->create(['user_id' => $citoyen->id, 'message' => 'Contenu privé du message'])->refresh();
        app(\App\Services\TransitionDemande::class)->passer($demande, Statut::Nouvelle, $this->agent);

        $this->actingAs($this->agent)->get('/agent')->assertOk()
            ->assertSeeInOrder(['10/10/2026 12:00', '07/10/2026 08:00', '06/10/2026 08:00', '05/10/2026 08:00', '04/10/2026 08:00'])
            ->assertDontSee('03/10/2026 08:00')
            ->assertSee('Admin Test')
            ->assertSee('Synchronisation lancée')
            ->assertSee('Statut de demande modifié')
            ->assertSee($demande->reference)
            ->assertDontSee('Habitante Secrète')
            ->assertDontSee('secrete@example.test')
            ->assertDontSee('Contenu privé du message')
            ->assertSee(route('agent.journal.index'));
    }

    public function test_sync_state_comes_from_the_cache_without_countdown(): void
    {
        Cache::forever(NovaTerraApi::CACHE_LAST_SYNC, '2026-10-10T11:30:00+04:00');
        Cache::forever(NovaTerraApi::CACHE_SESSION, [
            'current_wave' => 8, 'visible_requests_count' => 50, 'next_wave_number' => 9, 'minutes_until_next_wave' => 59,
        ]);

        $reponse = $this->actingAs($this->agent)->get('/agent')
            ->assertSee('Dernière synchro réussie : 10/10/2026 11:30')
            ->assertSee('Valeurs au moment de la dernière synchro à 11:30')
            ->assertSee('Vague actuelle : 8')
            ->assertSee('50 demandes visibles')
            ->assertSee('Vague suivante : 9')
            ->assertDontSee('Dernière erreur');

        $this->assertStringNotContainsString('minutes', $reponse->getContent());
    }

    public function test_sync_state_with_an_error_and_without_any_sync(): void
    {
        $this->actingAs($this->agent)->get('/agent')
            ->assertSee('Aucune synchronisation réussie pour l&#039;instant.', false);

        Cache::forever(NovaTerraApi::CACHE_LAST_SYNC, '2026-10-10T09:05:00+04:00');
        Cache::forever(NovaTerraApi::CACHE_LAST_ERROR, 'API Terra Nova : accès refusé (403).');

        $this->actingAs($this->agent)->get('/agent')
            ->assertSee('Dernière erreur : API Terra Nova : accès refusé (403).')
            ->assertSee('Dernière synchro réussie : 10/10/2026 09:05');
    }

    public function test_dashboard_query_count_does_not_depend_on_volume(): void
    {
        $this->actingAs($this->agent);
        $avant = $this->requetes(fn () => $this->get('/agent')->assertOk());

        Demande::factory()->count(30)->create();
        Alerte::factory()->count(10)->create();
        Service::factory()->count(5)->interrompu()->create();
        User::factory()->count(20)->create();
        foreach (range(1, 12) as $i) {
            Journal::enregistrer($this->agent, ActionJournal::SynchronisationLancee);
        }

        $apres = $this->requetes(fn () => $this->get('/agent')->assertOk());

        $this->assertSame($avant, $apres);
        $this->assertLessThanOrEqual(12, $apres);
    }

    public function test_dashboard_writes_nothing(): void
    {
        Demande::factory()->create();
        $avant = [Demande::count(), JournalActivite::count(), ApiRequest::count()];

        $this->actingAs($this->agent)->get('/agent')->assertOk();
        $this->actingAs($this->agent)->get('/agent/donnees-api')->assertOk();

        $this->assertSame($avant, [Demande::count(), JournalActivite::count(), ApiRequest::count()]);
    }

    // --- Données API ---

    public function test_default_sort_and_whitelisted_sorts(): void
    {
        $this->api('CODE-A', 'Citoyen', 0, '2026-10-09 10:00:00', xp: 300, tri: 3);
        $this->api('CODE-B', 'Institution', 0, '2026-10-09 11:00:00', xp: 100, tri: 1);
        $this->api('CODE-C', 'Institution', 2, '2026-10-09 12:00:00', xp: 200, tri: 2);

        $this->actingAs($this->agent)->get('/agent/donnees-api')->assertOk()
            ->assertSeeInOrder(['CODE-B', 'CODE-C', 'CODE-A']);
        $this->actingAs($this->agent)->get('/agent/donnees-api?tri=xp_total&sens=desc')
            ->assertSeeInOrder(['CODE-A', 'CODE-C', 'CODE-B']);
        $this->actingAs($this->agent)->get('/agent/donnees-api?tri=first_seen_at&sens=desc')
            ->assertSeeInOrder(['CODE-C', 'CODE-B', 'CODE-A']);
    }

    public function test_unknown_sort_values_are_ignored_without_sql_error(): void
    {
        $this->api('CODE-A', 'Citoyen', 0, '2026-10-09 10:00:00', tri: 2);
        $this->api('CODE-B', 'Citoyen', 0, '2026-10-09 10:00:00', tri: 1);

        foreach (['?tri=payload', '?tri=xp_total;DROP TABLE users', '?tri[]=xp_total', '?tri=id&sens=sideways', '?sens[]=desc', '?type[]=x&vague[]=1', '?vague=abc', '?vague=999&type=Inconnu'] as $requete) {
            $this->actingAs($this->agent)->get('/agent/donnees-api'.$requete)->assertOk()
                ->assertSeeInOrder(['CODE-B', 'CODE-A']);
        }
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_filters_have_counters_and_combine(): void
    {
        $this->api('CODE-A', 'Citoyen', 0, '2026-10-09 10:00:00', tri: 3);
        $this->api('CODE-B', 'Institution', 0, '2026-10-09 11:00:00', tri: 1);
        $this->api('CODE-C', 'Institution', 2, '2026-10-09 12:00:00', tri: 2);

        $this->actingAs($this->agent)->get('/agent/donnees-api')
            ->assertSeeInOrder(['Tous', '(3)', 'Citoyen', '(1)', 'Institution', '(2)'])
            ->assertSeeInOrder(['Toutes', '(3)', 'Vague 0', '(2)', 'Vague 2', '(1)']);

        $this->actingAs($this->agent)->get('/agent/donnees-api?type=Institution')
            ->assertSee('CODE-B')->assertSee('CODE-C')->assertDontSee('CODE-A');

        $reponse = $this->actingAs($this->agent)->get('/agent/donnees-api?type=Institution&vague=2')
            ->assertSee('CODE-C')->assertDontSee('CODE-B')->assertDontSee('CODE-A');

        // Filtres actifs : aria-current + texte + gras/souligné (classe), pas la couleur seule.
        $this->assertSame(2, substr_count($reponse->getContent(), 'aria-current="true"'));
        $this->assertSame(2, substr_count($reponse->getContent(), 'filtre-actif'));
    }

    public function test_pagination_keeps_sort_and_filters(): void
    {
        foreach (range(1, 30) as $i) {
            $this->api('CODE-'.$i, 'Institution', 1, '2026-10-09 10:00:00', xp: $i, tri: $i);
        }
        $this->api('CODE-AUTRE', 'Citoyen', 1, '2026-10-09 10:00:00');

        $reponse = $this->actingAs($this->agent)->get('/agent/donnees-api?type=Institution&tri=xp_total&sens=desc')->assertOk();
        $this->assertSame(25, substr_count($reponse->getContent(), 'class="px-4 py-3 text-left font-medium whitespace-nowrap"'));

        preg_match('/href="([^"]*page=2[^"]*)"/', $reponse->getContent(), $m);
        $lien = html_entity_decode($m[1] ?? '');
        $this->assertStringContainsString('type=Institution', $lien);
        $this->assertStringContainsString('tri=xp_total', $lien);
        $this->assertStringContainsString('sens=desc', $lien);

        $this->actingAs($this->agent)->get($lien)->assertOk()
            ->assertSeeInOrder(['CODE-5', 'CODE-4', 'CODE-3', 'CODE-2', 'CODE-1'])
            ->assertDontSee('CODE-AUTRE');
    }

    public function test_table_is_accessible_and_page_has_one_h1_and_breadcrumb(): void
    {
        $this->api('CODE-A', 'Citoyen', 0, '2026-10-09 10:00:00');

        $reponse = $this->actingAs($this->agent)->get('/agent/donnees-api')->assertOk()
            ->assertSee('<caption', false)
            ->assertSee('scope="col"', false)
            ->assertSee('Fil d&#039;Ariane', false)
            ->assertSee(route('agent.index'));

        $this->assertSame(1, substr_count($reponse->getContent(), '<h1'));
        $this->assertSame(1, substr_count($reponse->getContent(), '<main'));
    }

    public function test_empty_state_is_in_french(): void
    {
        $this->actingAs($this->agent)->get('/agent/donnees-api')->assertOk()
            ->assertSee('Aucune donnée reçue de l&#039;API pour le moment.', false);
    }

    public function test_nouvelle_badge_follows_donnees_api_vues_at_and_the_button_resets_it(): void
    {
        $this->api('CODE-A', 'Citoyen', 0, '2026-10-09 10:00:00', tri: 1);
        $this->api('CODE-B', 'Citoyen', 0, '2026-10-10 11:00:00', tri: 2);
        $badge = 'w-fit">Nouvelle</span>';

        // Jamais marqué : tout est nouveau.
        $this->assertSame(2, substr_count($this->actingAs($this->agent)->get('/agent/donnees-api')->getContent(), $badge));

        // Marqué hier à 12 h : seule la ligne vue après est nouvelle.
        $this->agent->forceFill(['donnees_api_vues_at' => '2026-10-09 12:00:00'])->save();
        $page = $this->actingAs($this->agent->fresh())->get('/agent/donnees-api')->getContent();
        $this->assertSame(1, substr_count($page, $badge));
        $this->assertMatchesRegularExpression('/CODE-B\s*<span[^>]*>Nouvelle<\/span>/', $page);

        // « Marquer comme vues » : plus rien de nouveau, aucune entrée de journal.
        $this->actingAs($this->agent->fresh())->post(route('agent.donnees-api.vues'))->assertRedirect();
        $this->assertSame('2026-10-10 12:00:00', $this->agent->fresh()->donnees_api_vues_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, JournalActivite::count());
        $this->assertSame(0, substr_count($this->actingAs($this->agent->fresh())->get('/agent/donnees-api')->getContent(), $badge));

        // Une ligne vue après le marquage redevient nouvelle.
        Carbon::setTestNow('2026-10-10 15:00:00');
        $this->api('CODE-C', 'Citoyen', 0, '2026-10-10 14:00:00', tri: 3);
        $this->assertSame(1, substr_count($this->actingAs($this->agent->fresh())->get('/agent/donnees-api')->getContent(), $badge));
    }

    public function test_marking_as_seen_only_touches_the_connected_agent(): void
    {
        $autre = User::factory()->agent()->create();

        $this->actingAs($this->agent)->post(route('agent.donnees-api.vues'));

        $this->assertNotNull($this->agent->fresh()->donnees_api_vues_at);
        $this->assertNull($autre->fresh()->donnees_api_vues_at);
    }

    public function test_api_error_keeps_the_last_valid_data_visible(): void
    {
        $this->api('CODE-A', 'Citoyen', 0, '2026-10-09 10:00:00');
        Cache::forever(NovaTerraApi::CACHE_LAST_SYNC, '2026-10-10 09:05:00');
        Cache::forever(NovaTerraApi::CACHE_LAST_ERROR, 'API Terra Nova : injoignable.');

        $this->actingAs($this->agent)->get('/agent/donnees-api')->assertOk()
            ->assertSee('CODE-A')
            ->assertSee('La dernière synchronisation avec l&#039;API a échoué : API Terra Nova : injoignable.', false)
            ->assertSee('Les dernières données valides restent affichées.')
            ->assertSee('Dernière synchro réussie : 10/10/2026 09:05');
    }

    public function test_a_row_without_first_seen_date_does_not_break_the_page(): void
    {
        $this->api('CODE-A', 'Citoyen', 0, '2026-10-09 10:00:00');
        ApiRequest::query()->update(['first_seen_at' => null]);

        $this->actingAs($this->agent)->get('/agent/donnees-api?tri=first_seen_at')->assertOk()->assertSee('CODE-A');
    }

    public function test_data_page_query_count_does_not_depend_on_volume(): void
    {
        $this->actingAs($this->agent);
        foreach (range(1, 3) as $i) {
            $this->api('P-'.$i, 'Institution', $i, '2026-10-09 10:00:00', tri: $i);
        }
        $peu = $this->requetes(fn () => $this->get('/agent/donnees-api')->assertOk());

        foreach (range(4, 80) as $i) {
            $this->api('P-'.$i, 'Citoyen', $i % 9, '2026-10-09 10:00:00', tri: $i);
        }
        $beaucoup = $this->requetes(fn () => $this->get('/agent/donnees-api?type=Citoyen&tri=xp_total')->assertOk());

        $this->assertSame($peu, $beaucoup);
    }

    // --- Accès et navigation ---

    public function test_admin_and_citizen_get_403_and_guest_is_redirected(): void
    {
        $admin = User::factory()->admin()->create();
        $citoyen = User::factory()->create();

        foreach ([$admin, $citoyen] as $utilisateur) {
            $this->actingAs($utilisateur)->get('/agent')->assertForbidden();
            $this->actingAs($utilisateur)->get('/agent/donnees-api')->assertForbidden();
            $this->actingAs($utilisateur)->post(route('agent.donnees-api.vues'))->assertForbidden();
            $this->assertNull($utilisateur->fresh()->donnees_api_vues_at);
        }

        auth()->logout();
        $this->get('/agent')->assertRedirect(route('login'));
        $this->get('/agent/donnees-api')->assertRedirect(route('login'));
        $this->post(route('agent.donnees-api.vues'))->assertRedirect(route('login'));
    }

    public function test_data_link_is_in_the_agent_navigation_only(): void
    {
        $url = route('agent.donnees-api.index');

        $page = $this->actingAs($this->agent)->get('/agent')->assertSee($url)->getContent();
        $this->assertSame(2, substr_count($page, 'href="'.$url.'"')); // barre latérale, bloc synchro
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertDontSee($url);
        $this->actingAs(User::factory()->create())->get('/espace')->assertDontSee($url);
    }

    public function test_no_refresh_button_on_the_agent_pages(): void
    {
        $this->actingAs($this->agent)->get('/agent/donnees-api')->assertDontSee('Actualiser maintenant')
            ->assertDontSee(route('admin.synchronisation.index'));
        $this->actingAs($this->agent)->get('/agent')->assertDontSee('Actualiser maintenant');
    }
}
