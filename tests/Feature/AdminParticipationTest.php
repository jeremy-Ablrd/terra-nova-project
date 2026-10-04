<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\StatutContribution;
use App\Models\Contribution;
use App\Models\JournalActivite;
use App\Models\Projet;
use App\Models\User;
use App\Services\TransitionContribution;
use App\Services\TransitionRefusee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Administration de la participation (F65, F66, F67, F68, F76) : /admin/participation (admin seul), transitions
 * reçue → examinée → prise en compte avec réponse de la ville, projets, journal sans texte libre ni donnée personnelle.
 */
class AdminParticipationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading();
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    private function contribution(array $attributs = []): Contribution
    {
        return Contribution::factory()->create($attributs);
    }

    // --- Accès ---

    public function test_only_the_admin_reaches_the_participation_administration(): void
    {
        $c = $this->contribution();
        $projet = Projet::factory()->create();

        $urls = [
            ['get', route('admin.participation.index')], ['get', route('admin.participation.show', $c)],
            ['patch', route('admin.participation.statut', $c)], ['get', route('admin.participation.projets.index')],
            ['get', route('admin.participation.projets.create')], ['post', route('admin.participation.projets.store')],
            ['get', route('admin.participation.projets.edit', $projet)], ['put', route('admin.participation.projets.update', $projet)],
        ];

        foreach ($urls as [$methode, $url]) {
            $this->{$methode}($url)->assertRedirect(route('login'));
        }
        foreach ($urls as [$methode, $url]) {
            $this->actingAs(User::factory()->create())->{$methode}($url)->assertForbidden();
            $this->actingAs(User::factory()->agent()->create())->{$methode}($url)->assertForbidden();
        }
        $this->assertSame(StatutContribution::Recue, $c->fresh()->statut);
    }

    public function test_no_route_deletes_or_edits_free_text_of_a_contribution(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_contains($route->uri(), 'participation') || str_contains($route->uri(), 'contributions')) {
                $this->assertNotContains('DELETE', $route->methods(), $route->uri());
            }
        }
        $this->assertFalse(Route::has('admin.participation.destroy'));
        $this->assertFalse(Route::has('admin.participation.publier'));
    }

    public function test_admin_pages_have_one_main_and_one_h1_and_are_linked(): void
    {
        $c = $this->contribution();
        $projet = Projet::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.index'))->assertSee(route('admin.participation.index'), false);
        $this->actingAs($this->admin)->get(route('admin.participation.index'))->assertSee(route('admin.participation.projets.index'), false);

        foreach ([route('admin.participation.index'), route('admin.participation.show', $c), route('admin.participation.projets.index'),
            route('admin.participation.projets.create'), route('admin.participation.projets.edit', $projet)] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<main'), $url);
            $this->assertSame(1, substr_count($html, '<h1'), $url);
        }
    }

    // --- Liste et lecture ---

    public function test_the_list_filters_by_type_and_status_and_ignores_unknown_values(): void
    {
        $avis = $this->contribution();
        $idee = Contribution::factory()->idee()->create(['titre' => 'Une boîte à livres', 'statut' => StatutContribution::Examinee]);

        $this->actingAs($this->admin)->get(route('admin.participation.index'))->assertSee($avis->reference)->assertSee($idee->reference);
        $this->actingAs($this->admin)->get(route('admin.participation.index', ['type' => 'idee']))->assertSee($idee->reference)->assertDontSee($avis->reference);
        $this->actingAs($this->admin)->get(route('admin.participation.index', ['statut' => 'recue']))->assertSee($avis->reference)->assertDontSee($idee->reference);
        $this->actingAs($this->admin)->get(route('admin.participation.index', ['type' => 'nimporte', 'statut' => ['x']]))->assertOk()->assertSee($avis->reference)->assertSee($idee->reference);
    }

    public function test_the_page_shows_work_counts_per_status_and_no_tally_of_opinions(): void
    {
        Contribution::factory()->count(2)->create();
        Contribution::factory()->statut(StatutContribution::Examinee)->create();

        $html = $this->actingAs($this->admin)->get(route('admin.participation.index'))->getContent();
        $texte = mb_strtolower(strip_tags($html));

        $this->assertStringContainsString('reçue (2)', $texte);
        $this->assertStringContainsString('examinée (1)', $texte);
        foreach (['voter', 'scrutin', 'sondage', 'résultats'] as $mot) {
            $this->assertStringNotContainsString($mot, $texte);
        }
    }

    public function test_the_detail_shows_the_content_but_not_who_wrote_it(): void
    {
        $auteur = User::factory()->create(['name' => 'Zoé Habitante', 'email' => 'zoe@example.test']);
        $c = $this->contribution(['user_id' => $auteur->id, 'message' => 'Un avis sur les bancs de la place.']);

        $this->actingAs($this->admin)->get(route('admin.participation.show', $c))
            ->assertOk()->assertSee('Un avis sur les bancs de la place.')->assertSee($c->reference)
            ->assertDontSee('Zoé Habitante')->assertDontSee('zoe@example.test');
    }

    public function test_an_anonymised_contribution_says_so(): void
    {
        $c = $this->contribution(['user_id' => null, 'message' => '[Contenu supprimé à la demande de l\'habitant]', 'anonymisee_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.participation.show', $c))->assertSee('a supprimé son compte');
    }

    // --- Transitions ---

    public function test_the_admin_moves_a_contribution_through_the_three_statuses_with_a_response(): void
    {
        $c = $this->contribution(['message' => 'Texte secret de l\'habitant']);

        // Examinée : réponse facultative.
        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'recue', 'reponse' => ''])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.participation.show', $c));
        $this->assertSame(StatutContribution::Examinee, $c->fresh()->statut);
        $this->assertNull($c->fresh()->reponse);

        // Prise en compte : la réponse est obligatoire.
        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'examinee', 'reponse' => '   '])
            ->assertSessionHasErrors('transition');
        $this->assertSame(StatutContribution::Examinee, $c->fresh()->statut);

        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'examinee', 'reponse' => 'Merci, la ville installe deux bancs.'])
            ->assertSessionHasNoErrors();

        $c = $c->fresh();
        $this->assertSame(StatutContribution::PriseEnCompte, $c->statut);
        $this->assertSame('Merci, la ville installe deux bancs.', $c->reponse);
        $this->assertNotNull($c->reponse_at);
        $this->assertSame([StatutContribution::Recue, StatutContribution::Examinee, StatutContribution::PriseEnCompte], $c->etapes->pluck('statut')->all());
    }

    public function test_a_response_given_when_examined_is_kept_and_can_be_replaced_when_taken_into_account(): void
    {
        $c = $this->contribution();
        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'recue', 'reponse' => 'Nous étudions votre avis.']);
        $this->assertSame('Nous étudions votre avis.', $c->fresh()->reponse);

        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'examinee', 'reponse' => 'Décision : retenu.']);
        $this->assertSame('Décision : retenu.', $c->fresh()->reponse);
    }

    public function test_no_skip_no_going_back_and_no_change_once_taken_into_account(): void
    {
        $c = $this->contribution();

        // Saut impossible : l'écran montrait « examinée » alors que la contribution est « reçue ».
        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'examinee', 'reponse' => 'Réponse'])->assertSessionHasErrors('transition');
        $this->assertSame(StatutContribution::Recue, $c->fresh()->statut);

        // Statut inconnu.
        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'archivee'])->assertSessionHasErrors('statut_affiche');

        $service = app(TransitionContribution::class);
        $service->passer($c, StatutContribution::Recue, $this->admin);
        $service->passer($c, StatutContribution::Examinee, $this->admin, 'Réponse');

        $this->expectException(TransitionRefusee::class);
        $service->passer($c, StatutContribution::PriseEnCompte, $this->admin, 'Encore');
    }

    public function test_the_status_cannot_be_set_freely_by_a_field(): void
    {
        $c = $this->contribution();

        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'recue', 'statut' => 'prise_en_compte', 'reponse' => 'x']);

        $this->assertSame(StatutContribution::Examinee, $c->fresh()->statut);
    }

    public function test_every_transition_is_journaled_without_any_free_text_or_personal_data(): void
    {
        $auteur = User::factory()->create(['name' => 'Zoé Habitante', 'email' => 'zoe@example.test']);
        $c = $this->contribution(['user_id' => $auteur->id, 'message' => 'Message confidentiel de Zoé']);

        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'recue', 'reponse' => 'Réponse confidentielle de la ville']);
        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'examinee', 'reponse' => 'Autre réponse confidentielle']);

        $entrees = JournalActivite::where('action', ActionJournal::ContributionTraitee->value)->orderBy('id')->get();
        $this->assertCount(2, $entrees);
        $this->assertSame($this->admin->id, $entrees[0]->acteur_id);
        $this->assertSame($c->reference, $entrees[0]->objet_libelle);
        $this->assertSame($c->reference.' : recue → examinee', $entrees[0]->detail);
        $this->assertSame($c->reference.' : examinee → prise_en_compte', $entrees[1]->detail);

        $tout = json_encode($entrees->map->getAttributes()->all(), JSON_UNESCAPED_UNICODE);
        foreach (['confidentiel', 'Zoé', 'zoe@example.test', 'Habitante'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $tout);
        }
    }

    public function test_if_the_journal_fails_the_status_and_the_step_are_not_written(): void
    {
        $c = $this->contribution();
        JournalActivite::creating(fn () => throw new RuntimeException('journal indisponible'));

        try {
            app(TransitionContribution::class)->passer($c, StatutContribution::Recue, $this->admin, 'Réponse');
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException $e) {
            $this->assertSame('journal indisponible', $e->getMessage());
        }

        $this->assertSame(StatutContribution::Recue, $c->fresh()->statut);
        $this->assertNull($c->fresh()->reponse);
        $this->assertSame(1, DB::table('contribution_etapes')->where('contribution_id', $c->id)->count());
    }

    public function test_the_citizen_then_sees_the_response_and_a_notice(): void
    {
        $citoyen = User::factory()->create();
        $c = $this->contribution(['user_id' => $citoyen->id]);
        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'recue', 'reponse' => 'Nous avons bien lu votre avis.']);

        $this->actingAs($citoyen)->get(route('dashboard'))->assertSee('Votre contribution '.$c->reference.' a été examinée par la ville.');
        $this->actingAs($citoyen)->get(route('mes-contributions.show', $c))->assertSee('Réponse de la ville')->assertSee('Nous avons bien lu votre avis.')->assertSee('Examinée');
    }

    // --- Projets ---

    private function donneesProjet(array $surcharge = []): array
    {
        return array_merge([
            'titre' => 'Jardins du port', 'resume' => 'Des jardins devant le port.', 'description' => 'Une description du projet.',
            'consultation_debut_at' => '2026-10-01T08:00', 'consultation_fin_at' => '2026-10-30T18:00', 'bilan' => '', 'publie' => '1',
        ], $surcharge);
    }

    public function test_the_admin_creates_a_published_project_with_a_unique_slug_and_a_journal_entry(): void
    {
        $this->actingAs($this->admin)->post(route('admin.participation.projets.store'), $this->donneesProjet())
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.participation.projets.index'));
        $this->actingAs($this->admin)->post(route('admin.participation.projets.store'), $this->donneesProjet());

        $projets = Projet::orderBy('id')->get();
        $this->assertSame(['jardins-du-port', 'jardins-du-port-2'], $projets->pluck('slug')->all());
        $this->assertNotNull($projets[0]->publie_at);
        $this->assertNull($projets[0]->bilan);

        $entree = JournalActivite::where('action', ActionJournal::ProjetCree->value)->orderBy('id')->first();
        $this->assertSame('Projet n° '.$projets[0]->id, $entree->objet_libelle);
        $this->assertSame('projet publié', $entree->detail);
        $this->assertStringNotContainsString('Jardins du port', json_encode($entree->getAttributes()));

        $this->get(route('projets.show', $projets[0]))->assertOk()->assertSee('Jardins du port');
    }

    public function test_an_unpublished_project_stays_a_draft(): void
    {
        $this->actingAs($this->admin)->post(route('admin.participation.projets.store'), $this->donneesProjet(['publie' => '0']));

        $projet = Projet::firstOrFail();
        $this->assertNull($projet->publie_at);
        $this->get(route('projets.show', $projet))->assertNotFound();
        $this->get(route('projets.index'))->assertDontSee('Jardins du port');
    }

    public function test_project_validation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.participation.projets.store'), $this->donneesProjet(['titre' => '', 'resume' => '', 'description' => '']))
            ->assertSessionHasErrors(['titre', 'resume', 'description']);
        $this->actingAs($this->admin)->post(route('admin.participation.projets.store'), $this->donneesProjet(['consultation_fin_at' => '2026-09-01T08:00']))
            ->assertSessionHasErrors('consultation_fin_at');
        $this->actingAs($this->admin)->post(route('admin.participation.projets.store'), $this->donneesProjet(['titre' => str_repeat('a', 151)]))
            ->assertSessionHasErrors('titre');
        $this->assertSame(0, Projet::count());
    }

    public function test_slug_and_publication_date_cannot_be_forced_from_the_form(): void
    {
        $this->actingAs($this->admin)->post(route('admin.participation.projets.store'), $this->donneesProjet(['slug' => 'pirate', 'publie_at' => '2000-01-01', 'publie' => '0']));

        $projet = Projet::firstOrFail();
        $this->assertSame('jardins-du-port', $projet->slug);
        $this->assertNull($projet->publie_at);
    }

    public function test_updating_a_project_journals_only_field_names_and_keeps_the_slug(): void
    {
        $projet = Projet::factory()->create(['titre' => 'Ancien titre', 'slug' => 'ancien-titre', 'bilan' => null]);

        $this->actingAs($this->admin)->put(route('admin.participation.projets.update', $projet), $this->donneesProjet([
            'titre' => 'Titre confidentiel modifié', 'bilan' => 'Bilan confidentiel', 'consultation_debut_at' => $projet->consultation_debut_at->format('Y-m-d\TH:i'),
            'consultation_fin_at' => $projet->consultation_fin_at->format('Y-m-d\TH:i'), 'resume' => $projet->resume, 'description' => $projet->description, 'publie' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('ancien-titre', $projet->fresh()->slug);
        $entree = JournalActivite::where('action', ActionJournal::ProjetModifie->value)->firstOrFail();
        $this->assertSame('Projet n° '.$projet->id, $entree->objet_libelle);
        $this->assertSame('champs : titre, bilan', $entree->detail);
        $this->assertStringNotContainsString('confidentiel', json_encode($entree->getAttributes(), JSON_UNESCAPED_UNICODE));
    }

    public function test_publishing_and_unpublishing_is_journaled_and_an_unchanged_form_writes_nothing(): void
    {
        $projet = Projet::factory()->brouillon()->create();
        $corps = fn (string $publie) => $this->donneesProjet([
            'titre' => $projet->titre, 'resume' => $projet->resume, 'description' => $projet->description, 'bilan' => '',
            'consultation_debut_at' => $projet->consultation_debut_at->format('Y-m-d\TH:i'),
            'consultation_fin_at' => $projet->consultation_fin_at->format('Y-m-d\TH:i'), 'publie' => $publie,
        ]);

        $this->actingAs($this->admin)->put(route('admin.participation.projets.update', $projet), $corps('1'));
        $this->assertNotNull($projet->fresh()->publie_at);
        $this->get(route('projets.show', $projet))->assertOk();

        $this->actingAs($this->admin)->put(route('admin.participation.projets.update', $projet), $corps('1')); // inchangé
        $this->actingAs($this->admin)->put(route('admin.participation.projets.update', $projet), $corps('0'));
        $this->assertNull($projet->fresh()->publie_at);
        $this->get(route('projets.show', $projet))->assertNotFound();

        $details = JournalActivite::where('action', ActionJournal::ProjetModifie->value)->orderBy('id')->pluck('detail')->all();
        $this->assertSame(['publication : non → oui', 'publication : oui → non'], $details);
    }

    public function test_the_agent_can_read_the_new_actions_in_the_journal(): void
    {
        $c = $this->contribution();
        $this->actingAs($this->admin)->patch(route('admin.participation.statut', $c), ['statut_affiche' => 'recue']);

        $this->actingAs(User::factory()->agent()->create())->get(route('agent.journal.index'))
            ->assertOk()->assertSee('Contribution traitée')->assertSee($c->reference);
    }

    public function test_the_participation_link_is_in_the_admin_navigation_only(): void
    {
        $this->actingAs($this->admin)->get(route('admin.index'))->assertSee('Participation');
        $this->actingAs(User::factory()->agent()->create())->get(route('agent.index'))->assertDontSee(route('admin.participation.index'), false);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee(route('admin.participation.index'), false);
    }
}
