<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\Priorite;
use App\Enums\Statut;
use App\Enums\TypeDemande;
use App\Models\Demande;
use App\Models\JournalActivite;
use App\Models\User;
use App\Services\PrioriteDemande;
use App\Services\TransitionRefusee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** F80 : priorité des demandes (liste agent triée, filtre « Prioritaires », boutons du détail), F86 côté agent (badge, compteur). */
class PrioriteDemandesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading();
        $this->agent = User::factory()->agent()->create();
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    private function demande(Priorite $priorite, int $joursEcoules, array $extra = []): Demande
    {
        return Demande::factory()->priorite($priorite)->create(array_merge(['created_at' => now()->subDays($joursEcoules)], $extra));
    }

    /** @return list<string> références dans l'ordre d'affichage de la liste agent */
    private function ordre(string $url = '/agent/demandes'): array
    {
        $texte = strip_tags($this->actingAs($this->agent)->get($url)->assertOk()->getContent());
        preg_match_all('/NT-\d{4}-\d{5}/', $texte, $m);

        return array_values(array_unique($m[0]));
    }

    // --- Tri ---

    public function test_the_list_is_sorted_by_medical_emergency_then_priority_then_most_recent(): void
    {
        $normaleRecente = $this->demande(Priorite::Normale, 1);
        $normaleAncienne = $this->demande(Priorite::Normale, 9);
        $prioritaire = $this->demande(Priorite::Prioritaire, 6);
        $urgenceAncienne = $this->demande(Priorite::UrgenceMedicale, 8);
        $urgenceRecente = $this->demande(Priorite::UrgenceMedicale, 2);
        $prioritaireRecente = $this->demande(Priorite::Prioritaire, 3);

        $this->assertSame(
            array_map(fn (Demande $d) => $d->reference, [$urgenceRecente, $urgenceAncienne, $prioritaireRecente, $prioritaire, $normaleRecente, $normaleAncienne]),
            $this->ordre()
        );
    }

    public function test_the_order_is_stable_for_equal_dates_and_across_pages(): void
    {
        $date = now()->subDay();
        $ids = [];
        foreach (range(1, 10) as $i) {
            $ids[] = Demande::factory()->priorite(Priorite::Normale)->create(['created_at' => $date])->id;
        }
        foreach (range(1, 8) as $i) {
            $ids[] = Demande::factory()->priorite(Priorite::UrgenceMedicale)->create(['created_at' => $date])->id;
        }

        $page1 = $this->ordre('/agent/demandes');
        $page2 = $this->ordre('/agent/demandes?page=2');

        $this->assertCount(15, $page1);
        $this->assertCount(3, $page2);
        $this->assertSame(array_unique(array_merge($page1, $page2)), array_merge($page1, $page2), 'aucun doublon entre les pages');
        $urgences = Demande::where('priorite', 'urgence_medicale')->pluck('reference')->all();
        $this->assertEqualsCanonicalizing($urgences, array_slice($page1, 0, 8), 'les 8 urgences sont en tête de la première page');
        // Égalité de date : l'identifiant le plus récent d'abord, toujours dans le même ordre.
        $this->assertSame($page1, $this->ordre('/agent/demandes'));
    }

    public function test_the_badge_is_written_in_text_and_the_normal_priority_has_none(): void
    {
        $urgence = $this->demande(Priorite::UrgenceMedicale, 1);
        $prioritaire = $this->demande(Priorite::Prioritaire, 2);
        $normale = $this->demande(Priorite::Normale, 3);

        $html = $this->actingAs($this->agent)->get('/agent/demandes')->getContent();

        $this->assertSame(1, preg_match_all('/<span class="tn-badge tn-badge--danger[^"]*">\s*<span aria-hidden="true">✚<\/span>Urgence médicale\s*<\/span>/', $html));
        $this->assertSame(1, preg_match_all('/<span class="tn-badge tn-badge--warn[^"]*">\s*<span aria-hidden="true">▲<\/span>Prioritaire\s*<\/span>/', $html));
        $this->assertSame(0, substr_count($html, '○'));
        $this->assertStringContainsString('urgences médicales et demandes prioritaires en tête', $html);
        $this->assertNotNull($normale);
    }

    // --- Filtre ---

    public function test_the_priority_filter_keeps_priority_and_emergency_requests_and_ignores_unknown_values(): void
    {
        $normale = $this->demande(Priorite::Normale, 1);
        $prioritaire = $this->demande(Priorite::Prioritaire, 2);
        $urgence = $this->demande(Priorite::UrgenceMedicale, 3);

        $this->assertEqualsCanonicalizing([$urgence->reference, $prioritaire->reference], $this->ordre('/agent/demandes?priorite=prioritaires'));
        $this->assertSame([$urgence->reference], $this->ordre('/agent/demandes?priorite=urgence_medicale'));
        $this->assertCount(3, $this->ordre('/agent/demandes?priorite=nimporte'));
        $this->assertCount(3, $this->ordre('/agent/demandes?priorite[]=x'));
        $this->assertNotNull($normale);
    }

    public function test_the_priority_filter_combines_with_the_status_filter_and_keeps_it_in_links(): void
    {
        $this->demande(Priorite::Prioritaire, 1);
        $enCours = $this->demande(Priorite::Prioritaire, 2, ['statut' => Statut::EnCours]);
        $this->demande(Priorite::Normale, 3, ['statut' => Statut::EnCours]);

        $page = $this->actingAs($this->agent)->get('/agent/demandes?priorite=prioritaires&statut=en_cours');

        $this->assertSame([$enCours->reference], $this->ordre('/agent/demandes?priorite=prioritaires&statut=en_cours'));
        $page->assertSee(e(route('agent.demandes.index', ['statut' => 'nouvelle', 'priorite' => 'prioritaires'])), false)
            ->assertSee(e(route('agent.demandes.index', ['statut' => 'en_cours', 'priorite' => 'urgence_medicale'])), false);
    }

    public function test_the_filter_counters_are_exact_and_the_list_still_uses_a_single_grouped_query(): void
    {
        $this->demande(Priorite::Prioritaire, 1);
        $this->demande(Priorite::UrgenceMedicale, 2);
        $this->demande(Priorite::UrgenceMedicale, 3, ['statut' => Statut::Traitee]);
        $this->demande(Priorite::Normale, 4);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $html = $this->actingAs($this->agent)->get('/agent/demandes')->getContent();
        $groupees = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'group by'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $groupees);
        $this->assertMatchesRegularExpression('/Prioritaires\s*<span>\(3\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Urgences médicales\s*<span>\(2\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Toutes\s*<span>\(4\)<\/span>/', $html);
    }

    public function test_exactly_one_filter_link_is_marked_current_in_each_group(): void
    {
        $this->demande(Priorite::Prioritaire, 1);

        $html = $this->actingAs($this->agent)->get('/agent/demandes?priorite=prioritaires')->getContent();

        $this->assertSame(2, substr_count($html, 'aria-current="true"'));       // un par groupe nommé : statut « Toutes », priorité « Prioritaires »
        $this->assertSame(0, substr_count($html, 'aria-current="location"'));
        $this->assertMatchesRegularExpression('/filtre-actif[^"]*"\s+aria-current="true"\s*>\s*Prioritaires/', $html);
        $this->assertSame(1, substr_count($html, '<nav aria-label="Filtrer par statut">'));
        $this->assertStringContainsString('aria-label="Filtrer par priorité"', $html);
    }

    public function test_an_empty_priority_filter_says_so_with_a_way_back(): void
    {
        $this->demande(Priorite::Normale, 1);

        $this->actingAs($this->agent)->get('/agent/demandes?priorite=urgence_medicale')->assertSee('Aucune demande avec cette priorité.')->assertSee('Voir toutes les demandes');
    }

    // --- Détail : boutons ---

    public function test_the_detail_offers_the_two_other_levels_as_post_buttons(): void
    {
        $demande = $this->demande(Priorite::Normale, 1);

        $html = $this->actingAs($this->agent)->get(route('agent.demandes.show', $demande))->assertOk()->getContent();

        $this->assertStringContainsString('Priorité actuelle :', $html);
        $this->assertStringContainsString('Passer en « Prioritaire »', $html);
        $this->assertStringContainsString('Passer en « Urgence médicale »', $html);
        $this->assertStringNotContainsString('Passer en « Normale »', $html);
        $this->assertStringContainsString('name="priorite_affichee" value="normale"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_the_agent_sets_the_priority_and_it_is_journaled_with_levels_only(): void
    {
        $demande = $this->demande(Priorite::Normale, 1, ['message' => 'Texte très confidentiel de la demande', 'objet' => 'Objet confidentiel']);

        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $demande), ['priorite' => 'prioritaire', 'priorite_affichee' => 'normale'])
            ->assertRedirect(route('agent.demandes.show', $demande))
            ->assertSessionHas('succes');

        $this->assertSame(Priorite::Prioritaire, $demande->fresh()->priorite);
        $entree = JournalActivite::where('action', ActionJournal::PrioriteModifiee->value)->firstOrFail();
        $this->assertSame($this->agent->id, $entree->acteur_id);
        $this->assertSame($demande->reference, $entree->objet_libelle);
        $this->assertSame('priorité : normale → prioritaire', $entree->detail);
        $this->assertStringNotContainsString('confidentiel', json_encode($entree->getAttributes(), JSON_UNESCAPED_UNICODE));

        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $demande), ['priorite' => 'urgence_medicale', 'priorite_affichee' => 'prioritaire']);
        $this->assertSame(Priorite::UrgenceMedicale, $demande->fresh()->priorite);
        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $demande), ['priorite' => 'normale', 'priorite_affichee' => 'urgence_medicale']);
        $this->assertSame(Priorite::Normale, $demande->fresh()->priorite);   // une urgence cochée par erreur peut être ramenée
        $this->assertSame(3, JournalActivite::where('action', 'priorite_modifiee')->count());
    }

    public function test_a_stale_or_pointless_or_invalid_change_is_refused_without_trace(): void
    {
        $demande = $this->demande(Priorite::Prioritaire, 1);

        // L'écran montrait « normale » alors que la demande est « prioritaire ».
        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $demande), ['priorite' => 'urgence_medicale', 'priorite_affichee' => 'normale'])->assertSessionHas('erreur');
        // Déjà à ce niveau.
        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $demande), ['priorite' => 'prioritaire', 'priorite_affichee' => 'prioritaire'])->assertSessionHas('erreur');
        // Valeurs inconnues ou absentes.
        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $demande), ['priorite' => 'maximale', 'priorite_affichee' => 'prioritaire'])->assertSessionHas('erreur');
        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $demande), [])->assertSessionHas('erreur');
        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $demande), ['priorite' => ['x'], 'priorite_affichee' => ['y']])->assertSessionHas('erreur');

        $this->assertSame(Priorite::Prioritaire, $demande->fresh()->priorite);
        $this->assertSame(0, JournalActivite::where('action', 'priorite_modifiee')->count());
    }

    public function test_if_the_journal_fails_the_priority_is_not_changed(): void
    {
        $demande = $this->demande(Priorite::Normale, 1);
        JournalActivite::creating(fn () => throw new RuntimeException('journal indisponible'));

        try {
            app(PrioriteDemande::class)->definir($demande, Priorite::Normale, Priorite::Prioritaire, $this->agent);
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException $e) {
            $this->assertSame('journal indisponible', $e->getMessage());
        }

        $this->assertSame(Priorite::Normale, $demande->fresh()->priorite);
    }

    public function test_the_service_refuses_a_second_change_to_the_same_level(): void
    {
        $demande = $this->demande(Priorite::Normale, 1);
        app(PrioriteDemande::class)->definir($demande, Priorite::Normale, Priorite::Prioritaire, $this->agent);

        $this->expectException(TransitionRefusee::class);
        app(PrioriteDemande::class)->definir($demande, Priorite::Normale, Priorite::Prioritaire, $this->agent);
    }

    // --- Accès ---

    public function test_only_the_agent_changes_a_priority(): void
    {
        $demande = $this->demande(Priorite::Normale, 1);
        $corps = ['priorite' => 'urgence_medicale', 'priorite_affichee' => 'normale'];

        $this->post(route('agent.demandes.priorite', $demande), $corps)->assertRedirect(route('login'));
        foreach ([User::factory()->create(), User::factory()->admin()->create(), $demande->user] as $autre) {
            $this->actingAs($autre)->post(route('agent.demandes.priorite', $demande), $corps)->assertForbidden();
        }
        $this->assertSame(Priorite::Normale, $demande->fresh()->priorite);

        // Une demande qui n'est pas citoyenne reste hors de portée de l'agent.
        $institution = Demande::factory()->type(TypeDemande::Institution)->create();
        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $institution), $corps)->assertForbidden();
    }

    public function test_an_imported_request_can_have_a_priority(): void
    {
        $importee = Demande::factory()->importee()->priorite(Priorite::Normale)->create();

        $this->actingAs($this->agent)->post(route('agent.demandes.priorite', $importee), ['priorite' => 'prioritaire', 'priorite_affichee' => 'normale'])->assertSessionHas('succes');
        $this->assertSame(Priorite::Prioritaire, $importee->fresh()->priorite);
    }

    public function test_the_admin_has_no_access_to_the_list_with_priorities(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/agent/demandes?priorite=prioritaires')->assertForbidden();
    }

    // --- Tableau de bord ---

    public function test_the_agent_home_counts_open_medical_emergencies_and_links_to_them(): void
    {
        $this->demande(Priorite::UrgenceMedicale, 1);
        $this->demande(Priorite::UrgenceMedicale, 2, ['statut' => Statut::EnCours]);
        $this->demande(Priorite::UrgenceMedicale, 3, ['statut' => Statut::Traitee]);   // déjà traitée : plus à traiter
        $this->demande(Priorite::Prioritaire, 4);

        $this->actingAs($this->agent)->get('/agent')->assertOk()
            ->assertSee('2 urgences médicales à traiter')
            ->assertSee(route('agent.demandes.index', ['priorite' => 'urgence_medicale']), false);
    }

    public function test_the_agent_home_says_so_without_emergency_and_does_not_add_a_query(): void
    {
        $requetes = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->agent)->get('/agent')->assertOk();

            return count(DB::getQueryLog());
        };

        $this->demande(Priorite::Normale, 1);
        $sans = $requetes();
        $this->actingAs($this->agent)->get('/agent')->assertSee('Aucune urgence médicale à traiter');

        $this->demande(Priorite::UrgenceMedicale, 1);
        $this->assertSame($sans, $requetes());
    }

    public function test_the_default_priority_is_normal_for_existing_and_imported_requests(): void
    {
        $importee = Demande::factory()->importee()->create();
        DB::table('demandes')->where('id', $importee->id)->update(['priorite' => 'normale']);

        $this->assertSame(Priorite::Normale, $importee->fresh()->priorite);
        // Une ligne insérée sans priorité (ancien code, import) reçoit « normale » de la base.
        $id = DB::table('demandes')->insertGetId(['objet' => 'x', 'message' => 'y', 'user_id' => $this->agent->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('normale', DB::table('demandes')->where('id', $id)->value('priorite'));
    }
}
