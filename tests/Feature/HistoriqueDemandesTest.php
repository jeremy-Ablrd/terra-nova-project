<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** F26 : historique de MES demandes (liste paginée, filtre par statut, recherche). */
class HistoriqueDemandesTest extends TestCase
{
    use RefreshDatabase;

    private User $citoyen;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 12:00:00');
        Model::preventLazyLoading();
        $this->citoyen = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    private function demande(string $objet, Statut $statut = Statut::Nouvelle, string $cree = '2026-10-05 10:00:00', ?User $user = null): Demande
    {
        return Demande::factory()->statut($statut)->create([
            'user_id' => ($user ?? $this->citoyen)->id, 'objet' => $objet, 'created_at' => $cree, 'updated_at' => $cree,
        ])->refresh();
    }

    private function liste(string $requete = '')
    {
        return $this->actingAs($this->citoyen)->get('/mes-demandes'.$requete);
    }

    // --- Périmètre ---

    public function test_citizen_sees_only_his_own_demandes_never_others_or_imported(): void
    {
        $this->demande('Ma première');
        $this->demande('Celle du voisin', user: User::factory()->create());
        Demande::factory()->importee('F21', 'Marc Importé')->create(['objet' => 'Demande importée']);

        $this->liste()->assertOk()
            ->assertSee('Ma première')
            ->assertDontSee('Celle du voisin')
            ->assertDontSee('Demande importée')
            ->assertDontSee('Marc Importé')
            ->assertSeeInOrder(['Toutes', '(1)', 'Nouvelle', '(1)', 'En cours', '(0)', 'Traitée', '(0)']);
    }

    public function test_sorted_newest_first_with_stable_order_on_ties(): void
    {
        $this->demande('Objet ancien', cree: '2026-10-01 10:00:00');
        $this->demande('Objet egal un', cree: '2026-10-05 10:00:00');
        $this->demande('Objet egal deux', cree: '2026-10-05 10:00:00');
        $this->demande('Objet recent', cree: '2026-10-09 10:00:00');

        $this->liste()->assertSeeInOrder(['Objet recent', 'Objet egal deux', 'Objet egal un', 'Objet ancien']);
    }

    public function test_columns_show_service_status_and_both_dates(): void
    {
        $service = Service::factory()->create(['nom' => 'Voirie et propreté']);
        $d = $this->demande('Lampadaire', Statut::EnCours, '2026-10-02 08:30:00');
        $d->forceFill(['service_id' => $service->id, 'updated_at' => '2026-10-04 16:45:00'])->saveQuietly();

        $this->liste()
            ->assertSee('<caption', false)
            ->assertSee('scope="col"', false)
            ->assertSee($d->reference)
            ->assertSee('Voirie et propreté')
            ->assertSee('En cours')
            ->assertSee('02/10/2026 08:30')
            ->assertSee('04/10/2026 16:45');
    }

    public function test_page_has_one_h1_one_main_and_a_breadcrumb(): void
    {
        $page = $this->liste()->assertOk()->assertSee('Fil d&#039;Ariane', false)->assertSee(route('dashboard'))->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
        $this->assertStringContainsString('for="recherche"', $page);
    }

    public function test_each_row_leads_to_the_detail(): void
    {
        $d = $this->demande('Un objet');

        $this->liste()->assertSee('href="'.route('demandes.show', $d).'"', false);
        $this->actingAs($this->citoyen)->get(route('demandes.show', $d))->assertOk()->assertSee('Suivi de votre demande');
    }

    // --- Filtre ---

    public function test_status_filter_and_exact_counters(): void
    {
        $this->demande('Nouvelle A');
        $this->demande('Nouvelle B');
        $this->demande('En cours A', Statut::EnCours);
        $this->demande('Traitee A', Statut::Traitee);
        $this->demande('Traitee B', Statut::Traitee);
        $this->demande('Traitee C', Statut::Traitee);
        $this->demande('Voisin nouvelle', user: User::factory()->create());

        $page = $this->liste('?statut=traitee')->assertOk()
            ->assertSee('Traitee A')->assertSee('Traitee C')
            ->assertDontSee('Nouvelle A')->assertDontSee('En cours A')->assertDontSee('Voisin')
            ->assertSeeInOrder(['Toutes', '(6)', 'Nouvelle', '(2)', 'En cours', '(1)', 'Traitée', '(3)'])
            ->getContent();

        // Filtre actif : aria-current + classe (gras, souligné) + texte du libellé.
        $this->assertSame(1, substr_count($page, 'aria-current="true"'));
        $this->assertSame(1, substr_count($page, 'filtre-actif'));
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>\s*Traitée/u', $page);
    }

    public function test_unknown_status_values_are_ignored(): void
    {
        $this->demande('Visible A');

        foreach (['?statut=nimporte', '?statut[]=traitee', '?statut=', '?q[]=x'] as $requete) {
            $this->liste($requete)->assertOk()->assertSee('Visible A');
        }
    }

    // --- Recherche ---

    public function test_search_by_reference_and_by_word_of_the_object(): void
    {
        $a = $this->demande('Lampadaire en panne');
        $b = $this->demande('Arrêt de bus déplacé');

        $this->liste('?q='.urlencode($a->reference))->assertSee('Lampadaire en panne')->assertDontSee('Arrêt de bus');
        $this->liste('?q=bus')->assertSee('Arrêt de bus')->assertDontSee('Lampadaire');
        $this->liste('?q='.urlencode(substr($b->reference, -5)))->assertSee('Arrêt de bus');
    }

    public function test_search_never_leaks_other_citizens_demandes(): void
    {
        $autre = $this->demande('Lampadaire du voisin', user: User::factory()->create());

        $this->liste('?q=Lampadaire')->assertOk()->assertDontSee('Lampadaire du voisin');
        $this->liste('?q='.urlencode($autre->reference))->assertOk()->assertDontSee('Lampadaire du voisin');
    }

    public function test_percent_and_underscore_are_literal_characters(): void
    {
        $this->demande('Remise de 50% sur le tarif');
        $this->demande('Fichier mon_dossier perdu');
        $this->demande('Fichier monXdossier perdu');
        $this->demande('Simple objet');

        $this->liste('?q='.urlencode('%'))->assertSee('Remise de 50%')->assertDontSee('Simple objet')->assertDontSee('mon_dossier');
        $this->liste('?q='.urlencode('_'))->assertSee('mon_dossier')->assertDontSee('monXdossier')->assertDontSee('Simple objet');
        $this->liste('?q='.urlencode('mon_dossier'))->assertSee('mon_dossier')->assertDontSee('monXdossier');
        $this->liste('?q='.urlencode('!'))->assertOk()->assertSee('Aucun résultat pour cette recherche.');
    }

    public function test_search_length_is_limited_and_never_breaks(): void
    {
        $this->demande('Objet normal');
        $long = str_repeat('a', 5000);

        $page = $this->liste('?q='.$long)->assertOk()->assertSee('Aucun résultat pour cette recherche.')->getContent();

        $this->assertStringNotContainsString(str_repeat('a', 101), $page);
        $this->assertStringContainsString('maxlength="100"', $page);
    }

    public function test_search_and_filter_combine_and_counters_follow_the_search(): void
    {
        $this->demande('Lampadaire un', Statut::Nouvelle);
        $this->demande('Lampadaire deux', Statut::Traitee);
        $this->demande('Arbre tombé', Statut::Traitee);

        $this->liste('?q=Lampadaire&statut=traitee')->assertOk()
            ->assertSee('Lampadaire deux')->assertDontSee('Lampadaire un')->assertDontSee('Arbre tombé')
            ->assertSeeInOrder(['Toutes', '(2)', 'Nouvelle', '(1)', 'En cours', '(0)', 'Traitée', '(1)'])
            ->assertSee('Effacer la recherche')
            ->assertSee('name="statut" value="traitee"', false);
    }

    // --- Pagination ---

    public function test_pagination_by_ten_keeps_filter_and_search(): void
    {
        foreach (range(1, 25) as $i) {
            $this->demande(sprintf('Panne %02d', $i), Statut::Nouvelle, sprintf('2026-09-%02d 10:00:00', $i));
        }
        $this->demande('Autre sujet', Statut::Nouvelle);
        $this->demande('Panne traitée', Statut::Traitee);

        $page1 = $this->liste('?q=Panne&statut=nouvelle')->assertOk()
            ->assertSee('Panne 25')->assertSee('Panne 16')->assertDontSee('Panne 15')->assertDontSee('Panne traitée')->assertDontSee('Autre sujet')
            ->getContent();

        preg_match('/href="([^"]*page=2[^"]*)"/', $page1, $m);
        $lien = html_entity_decode($m[1] ?? '');
        $this->assertStringContainsString('q=Panne', $lien);
        $this->assertStringContainsString('statut=nouvelle', $lien);

        $this->actingAs($this->citoyen)->get($lien)->assertOk()
            ->assertSee('Panne 15')->assertSee('Panne 06')->assertDontSee('Panne 05')->assertDontSee('Panne traitée');
    }

    // --- États vides ---

    public function test_empty_states_in_french_with_the_useful_link(): void
    {
        $this->liste()->assertOk()
            ->assertSee('Vous n\'avez encore aucune demande', false)
            ->assertSee('Contacter la mairie')
            ->assertSee(route('contact.create'));

        $this->demande('Seule demande');

        $this->liste('?statut=traitee')->assertOk()
            ->assertSee('Aucune demande avec ce statut.')
            ->assertSee('Voir toutes mes demandes');

        $this->liste('?q=zzz')->assertOk()
            ->assertSee('Aucun résultat pour cette recherche.')
            ->assertSee('Effacer la recherche');
    }

    // --- Performance ---

    public function test_no_lazy_loading_and_constant_query_count(): void
    {
        $this->actingAs($this->citoyen);
        $service = Service::factory()->create();
        $this->demande('Une demande')->forceFill(['service_id' => $service->id])->saveQuietly();

        $compter = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $peu = $compter('/mes-demandes');

        foreach (range(1, 30) as $i) {
            Demande::factory()->create(['user_id' => $this->citoyen->id, 'service_id' => Service::factory()->create()->id]);
        }

        $this->assertSame($peu, $compter('/mes-demandes'));
        $this->assertSame($peu, $compter('/mes-demandes?q=a&statut=nouvelle&page=2'));
    }

    // --- Accès ---

    public function test_agent_and_admin_get_403_citizen_gets_200_and_guest_is_redirected(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/mes-demandes')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/mes-demandes')->assertForbidden();
        $this->actingAs($this->citoyen)->get('/mes-demandes')->assertOk();

        auth()->logout();
        $this->get('/mes-demandes')->assertRedirect(route('login'));
    }

    public function test_the_detail_stays_governed_by_the_policy(): void
    {
        $d = $this->demande('Détail');

        $this->actingAs(User::factory()->agent()->create())->get(route('demandes.show', $d))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('demandes.show', $d))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('demandes.show', $d))->assertForbidden();
    }
}
