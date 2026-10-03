<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\Statut;
use App\Enums\TypeDemande;
use App\Models\Demande;
use App\Models\DemandeEtape;
use App\Models\JournalActivite;
use App\Models\User;
use App\Services\TransitionDemande;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** D11 (suivi par étapes), F26 (historique), F49 (information de l'habitant), F22 étape 3 (changement de statut). */
class SuiviDemandesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $citoyen;

    private Demande $demande;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create(['name' => 'Agent Dupont']);
        $this->citoyen = User::factory()->create();
        $this->demande = Demande::factory()->create(['user_id' => $this->citoyen->id])->refresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    private function changerStatut(Demande $demande, array $data, ?User $acteur = null)
    {
        return $this->actingAs($acteur ?? $this->agent)->patch(route('agent.demandes.statut', $demande), $data);
    }

    // --- Transitions (F22 étape 3) ---

    public function test_nouvelle_to_en_cours_sets_agent_step_and_journal(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle'])
            ->assertRedirect(route('agent.demandes.show', $this->demande))
            ->assertSessionHas('succes');

        $demande = $this->demande->fresh();
        $this->assertSame(Statut::EnCours, $demande->statut);
        $this->assertSame($this->agent->id, $demande->agent_id);
        $this->assertNull($demande->traitee_at);

        $etape = $demande->etapes()->get()->last();
        $this->assertSame(Statut::EnCours, $etape->statut);
        $this->assertSame($this->agent->id, $etape->agent_id);
        $this->assertSame('Agent Dupont', $etape->agent_nom);
        $this->assertNull($etape->vu_at);

        $entree = JournalActivite::firstOrFail();
        $this->assertSame(ActionJournal::StatutDemandeModifie, $entree->action);
        $this->assertSame($demande->reference.' : nouvelle → en_cours', $entree->detail);
        $this->assertStringNotContainsString($this->demande->message, $entree->detail);
        $this->assertStringNotContainsString($this->citoyen->name, $entree->detail);
    }

    public function test_en_cours_to_traitee_sets_traitee_at_step_and_journal(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);

        Carbon::setTestNow('2026-10-05 14:30:00');
        $this->changerStatut($this->demande, ['statut' => 'en_cours'])->assertSessionHas('succes');

        $demande = $this->demande->fresh();
        $this->assertSame(Statut::Traitee, $demande->statut);
        $this->assertSame('2026-10-05 14:30:00', $demande->traitee_at->format('Y-m-d H:i:s'));
        $this->assertSame([Statut::Nouvelle, Statut::EnCours, Statut::Traitee], $demande->etapes->pluck('statut')->all());
        $this->assertSame(2, JournalActivite::count());
        $this->assertSame($demande->reference.' : en_cours → traitee', JournalActivite::latest('id')->first()->detail);
    }

    public function test_no_going_back_and_no_change_after_traitee(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);
        $this->changerStatut($this->demande, ['statut' => 'en_cours']);

        $this->changerStatut($this->demande, ['statut' => 'traitee'])
            ->assertSessionHas('erreur');

        $this->assertSame(Statut::Traitee, $this->demande->fresh()->statut);
        $this->assertSame(3, $this->demande->etapes()->count());
        $this->assertSame(2, JournalActivite::count());
    }

    public function test_stale_status_is_refused_without_any_write(): void
    {
        // La base est passée en_cours entre-temps, mais le formulaire affichait encore « nouvelle ».
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);
        $etapes = $this->demande->etapes()->count();

        $this->changerStatut($this->demande, ['statut' => 'nouvelle'])
            ->assertSessionHas('erreur', fn ($m) => str_contains($m, 'changé entre-temps'));

        $this->assertSame(Statut::EnCours, $this->demande->fresh()->statut);
        $this->assertSame($etapes, $this->demande->etapes()->count());
        $this->assertSame(1, JournalActivite::count());
    }

    public function test_missing_or_unknown_status_is_refused(): void
    {
        foreach ([[], ['statut' => 'inconnu'], ['statut' => ['traitee']]] as $data) {
            $this->changerStatut($this->demande, $data)->assertSessionHas('erreur');
        }

        $this->assertSame(Statut::Nouvelle, $this->demande->fresh()->statut);
        $this->assertSame(0, JournalActivite::count());
    }

    public function test_extra_fields_are_ignored(): void
    {
        $autre = User::factory()->agent()->create();

        $this->changerStatut($this->demande, [
            'statut' => 'nouvelle', 'cible' => 'traitee', 'agent_id' => $autre->id,
            'traitee_at' => '2020-01-01 00:00:00', 'user_id' => $autre->id, 'reference' => 'NT-PIRATE',
        ]);

        $demande = $this->demande->fresh();
        $this->assertSame(Statut::EnCours, $demande->statut);
        $this->assertSame($this->agent->id, $demande->agent_id);
        $this->assertNull($demande->traitee_at);
        $this->assertSame($this->citoyen->id, $demande->user_id);
        $this->assertNotSame('NT-PIRATE', $demande->reference);
    }

    public function test_journal_failure_rolls_back_the_whole_change(): void
    {
        JournalActivite::creating(fn () => throw new RuntimeException('journal indisponible'));

        try {
            app(TransitionDemande::class)->passer($this->demande, Statut::Nouvelle, $this->agent);
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException $e) {
            $this->assertSame('journal indisponible', $e->getMessage());
        }

        $demande = $this->demande->fresh();
        $this->assertSame(Statut::Nouvelle, $demande->statut);
        $this->assertNull($demande->agent_id);
        $this->assertSame(1, $demande->etapes()->count());
        $this->assertSame(0, DB::table('journal_activites')->count());
    }

    public function test_imported_demande_is_handled_like_the_others(): void
    {
        $importee = Demande::factory()->importee('F21', 'Citoyenne importée')->create()->refresh();

        $this->changerStatut($importee, ['statut' => 'nouvelle'])->assertSessionHas('succes');

        $this->assertSame(Statut::EnCours, $importee->fresh()->statut);
        $this->assertSame(2, $importee->etapes()->count());
        $this->assertStringContainsString('NT-', JournalActivite::firstOrFail()->detail);
        $this->assertStringNotContainsString('Citoyenne importée', JournalActivite::firstOrFail()->detail);
    }

    // --- Permissions ---

    public function test_admin_citizen_and_guest_cannot_change_status_or_open_the_agent_page(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle'], User::factory()->admin()->create())->assertForbidden();
        $this->changerStatut($this->demande, ['statut' => 'nouvelle'], $this->citoyen)->assertForbidden();
        $this->assertSame(Statut::Nouvelle, $this->demande->fresh()->statut);

        $this->actingAs(User::factory()->admin()->create())->get(route('agent.demandes.show', $this->demande))->assertForbidden();
        $this->actingAs($this->citoyen)->get(route('agent.demandes.show', $this->demande))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('agent.demandes.show', $this->demande))->assertRedirect(route('login'));
        $this->patch(route('agent.demandes.statut', $this->demande), ['statut' => 'nouvelle'])->assertRedirect(route('login'));
        $this->assertSame(Statut::Nouvelle, $this->demande->fresh()->statut);
    }

    public function test_agent_cannot_open_or_change_a_hidden_type_demande(): void
    {
        $cachee = Demande::factory()->importee('D01')->type(TypeDemande::Institution)->create()->refresh();

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $cachee))->assertForbidden();
        $this->changerStatut($cachee, ['statut' => 'nouvelle'])->assertForbidden();
        $this->assertSame(Statut::Nouvelle, $cachee->fresh()->statut);
    }

    // --- Historique ---

    public function test_nouvelle_step_is_created_with_every_demande(): void
    {
        $importee = Demande::factory()->importee('F22')->create();

        foreach ([$this->demande, $importee] as $demande) {
            $etapes = $demande->etapes()->get();
            $this->assertCount(1, $etapes);
            $this->assertSame(Statut::Nouvelle, $etapes[0]->statut);
            $this->assertNull($etapes[0]->agent_id);
        }
    }

    public function test_agent_page_shows_details_and_timeline_in_order(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');
        $demande = Demande::factory()->create(['user_id' => $this->citoyen->id, 'objet' => 'Nid-de-poule'])->refresh();
        Carbon::setTestNow('2026-10-02 09:15:00');
        $this->changerStatut($demande, ['statut' => 'nouvelle']);
        Carbon::setTestNow('2026-10-03 16:45:00');
        $this->changerStatut($demande, ['statut' => 'en_cours']);

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $demande))
            ->assertOk()
            ->assertSee($demande->reference)
            ->assertSee('Nid-de-poule')
            ->assertSee($this->citoyen->name)
            ->assertSee('Agent Dupont')
            ->assertSeeInOrder(['01/10/2026 08:00', '02/10/2026 09:15', '03/10/2026 16:45'])
            ->assertSee('Étape actuelle')
            ->assertDontSee('Marquer comme traitée')
            ->assertSee('Cette demande est traitée')
            ->assertSee('Fil d\'Ariane', false);
    }

    public function test_agent_page_offers_only_the_next_status(): void
    {
        $this->actingAs($this->agent)->get(route('agent.demandes.show', $this->demande))
            ->assertSee('Prendre en charge')
            ->assertDontSee('Marquer comme traitée')
            ->assertDontSee('<select', false);

        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $this->demande))
            ->assertSee('Marquer comme traitée')
            ->assertDontSee('Prendre en charge');
    }

    public function test_agent_list_links_to_the_detail_and_counters_follow_the_change(): void
    {
        $this->actingAs($this->agent)->get('/agent/demandes')
            ->assertSee(route('agent.demandes.show', $this->demande), false)
            ->assertSeeInOrder(['Toutes', '(1)', 'Nouvelle', '(1)', 'En cours', '(0)', 'Traitée', '(0)']);

        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);

        $this->actingAs($this->agent)->get('/agent/demandes')
            ->assertSeeInOrder(['Toutes', '(1)', 'Nouvelle', '(0)', 'En cours', '(1)', 'Traitée', '(0)'])
            ->assertSee('Aucune demande en attente');
    }

    public function test_citizen_sees_his_timeline_without_agent_name(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);

        $this->actingAs($this->citoyen)->get(route('demandes.show', $this->demande))
            ->assertOk()
            ->assertSee('Suivi de votre demande')
            ->assertSee('État actuel : En cours')
            ->assertSee('Étape actuelle')
            ->assertSee('Un agent')
            ->assertDontSee('Agent Dupont');
    }

    public function test_citizen_cannot_see_someone_elses_timeline(): void
    {
        $autre = User::factory()->create();

        $this->actingAs($autre)->get(route('demandes.show', $this->demande))->assertForbidden();
        $this->actingAs($autre)->get('/mes-demandes')->assertDontSee($this->demande->reference);
    }

    // --- F49 ---

    public function test_citizen_is_notified_until_he_acknowledges(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);
        $reference = $this->demande->reference;

        foreach (['/espace', '/mes-demandes'] as $page) {
            $this->actingAs($this->citoyen)->get($page)
                ->assertSee("Votre demande {$reference} est maintenant en cours.")
                ->assertSee('role="status"', false)
                ->assertSee('Compris');
        }

        $etape = $this->demande->etapes()->get()->last();
        $this->actingAs($this->citoyen)->post(route('demandes.etapes.vu', $etape))->assertRedirect();

        $this->assertNotNull($etape->fresh()->vu_at);
        $this->actingAs($this->citoyen)->get('/espace')->assertDontSee('est maintenant');
    }

    public function test_each_new_step_has_its_own_notice(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);
        $this->changerStatut($this->demande, ['statut' => 'en_cours']);

        $this->actingAs($this->citoyen)->get('/mes-demandes')
            ->assertSee('est maintenant en cours.')
            ->assertSee('est maintenant traitée.');
    }

    public function test_opening_the_demande_marks_it_as_seen(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);

        $this->actingAs($this->citoyen)->get(route('demandes.show', $this->demande))->assertOk();

        $this->assertSame(0, DemandeEtape::whereNull('vu_at')->where('statut', '!=', 'nouvelle')->count());
    }

    public function test_an_agent_viewing_the_demande_does_not_mark_it_as_seen(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);

        $this->actingAs($this->agent)->get(route('demandes.show', $this->demande))->assertOk();

        $this->assertSame(1, DemandeEtape::whereNull('vu_at')->where('statut', '!=', 'nouvelle')->count());
    }

    public function test_only_the_owner_can_acknowledge(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);
        $etape = $this->demande->etapes()->get()->last();

        $this->actingAs(User::factory()->create())->post(route('demandes.etapes.vu', $etape))->assertForbidden();
        $this->actingAs($this->agent)->post(route('demandes.etapes.vu', $etape))->assertForbidden();
        $this->assertNull($etape->fresh()->vu_at);

        auth()->logout();
        $this->post(route('demandes.etapes.vu', $etape))->assertRedirect(route('login'));
    }

    public function test_imported_demande_never_notifies_anyone(): void
    {
        $importee = Demande::factory()->importee('F21')->create()->refresh();
        $this->changerStatut($importee, ['statut' => 'nouvelle']);

        $this->actingAs($this->citoyen)->get('/espace')->assertDontSee('est maintenant');
        $this->actingAs($this->citoyen)->get('/mes-demandes')->assertDontSee('est maintenant')->assertDontSee('non lu');
    }

    public function test_nav_counter_is_exact_with_screen_reader_text(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);
        $this->changerStatut($this->demande, ['statut' => 'en_cours']);
        $autre = Demande::factory()->create(['user_id' => User::factory()->create()->id])->refresh();
        $this->changerStatut($autre, ['statut' => 'nouvelle']); // ne compte pas pour ce citoyen

        $this->actingAs($this->citoyen)->get(route('services.index'))
            ->assertSee('2 changements non lus');

        $etape = $this->demande->etapes()->get()->last();
        $this->actingAs($this->citoyen)->post(route('demandes.etapes.vu', $etape));

        $this->actingAs($this->citoyen)->get(route('services.index'))
            ->assertSee('1 changement non lu')
            ->assertDontSee('2 changements non lus');
    }

    // --- Requêtes ---

    private function requetesSurEtapes(string $url, ?User $user): int
    {
        if ($user) {
            $this->actingAs($user);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $requetes = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'demande_etapes'))->count();
        DB::disableQueryLog();

        return $requetes;
    }

    public function test_no_step_query_for_agent_admin_and_guest(): void
    {
        $this->assertSame(0, $this->requetesSurEtapes('/agent', $this->agent));
        $this->assertSame(0, $this->requetesSurEtapes('/admin', User::factory()->admin()->create()));

        auth()->logout();
        $this->assertSame(0, $this->requetesSurEtapes('/services', null));
    }

    public function test_citizen_pages_run_a_single_step_query(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);

        $this->assertSame(1, $this->requetesSurEtapes('/espace', $this->citoyen));
        $this->assertSame(1, $this->requetesSurEtapes('/mes-demandes', $this->citoyen));
    }

    public function test_pages_do_not_trigger_lazy_loading(): void
    {
        $this->changerStatut($this->demande, ['statut' => 'nouvelle']);
        $this->changerStatut($this->demande, ['statut' => 'en_cours']);
        Demande::factory()->count(3)->create(['user_id' => $this->citoyen->id]);
        Model::preventLazyLoading();

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $this->demande))->assertOk();
        $this->actingAs($this->agent)->get('/agent/demandes')->assertOk();
        $this->actingAs($this->citoyen)->get(route('demandes.show', $this->demande))->assertOk();
        $this->actingAs($this->citoyen)->get('/mes-demandes')->assertOk();
        $this->actingAs($this->citoyen)->get('/espace')->assertOk();
    }
}
