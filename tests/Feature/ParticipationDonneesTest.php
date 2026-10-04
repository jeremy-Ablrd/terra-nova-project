<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\EtatConsultation;
use App\Enums\StatutContribution;
use App\Enums\TypeContribution;
use App\Models\Contribution;
use App\Models\ContributionEtape;
use App\Models\JournalActivite;
use App\Models\Projet;
use App\Models\Service;
use App\Models\User;
use App\Services\SuppressionCompte;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ParticipationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Participation et données personnelles : anonymisation à la suppression du compte (jamais de suppression), « Mon dossier »,
 * et ParticipationSeeder (idempotent, dates relatives, séparé de ServiceSeeder).
 */
class ParticipationDonneesTest extends TestCase
{
    use RefreshDatabase;

    private User $habitant;

    private User $voisin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->habitant = User::factory()->create(['name' => 'Camille Habitante', 'email' => 'camille@example.test']);
        $this->voisin = User::factory()->create();
    }

    private function etape(Contribution $c, StatutContribution $statut): void
    {
        $e = new ContributionEtape;
        $e->contribution_id = $c->id;
        $e->statut = $statut;
        $e->created_at = now();
        $e->save();
    }

    /** @return array<int, Contribution> avis, idée et commentaire de l'habitant, la dernière prise en compte avec réponse */
    private function contributionsHabitant(): array
    {
        $avis = Contribution::factory()->create(['user_id' => $this->habitant->id, 'message' => 'Mon avis personnel sur la place.']);
        $idee = Contribution::factory()->idee()->create(['user_id' => $this->habitant->id, 'titre' => 'Mon idée de boîte à livres', 'message' => 'Le texte de mon idée, assez long.']);
        $commentaire = Contribution::factory()->commentaire()->create(['user_id' => $this->habitant->id, 'message' => 'Mon commentaire sur le service.',
            'statut' => StatutContribution::PriseEnCompte, 'reponse' => 'Merci pour votre retour.', 'reponse_at' => now()]);
        $this->etape($commentaire, StatutContribution::Examinee);
        $this->etape($commentaire, StatutContribution::PriseEnCompte);

        return [$avis, $idee, $commentaire];
    }

    private function etat(): array
    {
        return Contribution::orderBy('id')->get(['id', 'reference', 'type', 'projet_id', 'service_id', 'statut', 'reponse', 'created_at'])->map->getAttributes()->all();
    }

    // --- Anonymisation ---

    public function test_deleting_the_account_anonymises_contributions_and_never_deletes_them(): void
    {
        [$avis, $idee, $commentaire] = $this->contributionsHabitant();
        $voisine = Contribution::factory()->idee()->create(['user_id' => $this->voisin->id, 'titre' => 'Idée du voisin', 'message' => 'Message du voisin, bien long.']);
        $avant = $this->etat();

        app(SuppressionCompte::class)->supprimer($this->habitant);

        $this->assertNull(User::find($this->habitant->id));
        $this->assertSame(4, Contribution::count());
        $this->assertSame($avant, $this->etat()); // référence, type, projet, service, statut, réponse, date : conservés

        foreach ([$avis, $idee, $commentaire] as $c) {
            $c = $c->fresh();
            $this->assertNull($c->user_id);
            $this->assertSame(SuppressionCompte::TEXTE_SUPPRIME, $c->message);
            $this->assertNull($c->titre);
            $this->assertNotNull($c->anonymisee_at);
            $this->assertTrue($c->estAnonymisee());
        }
        $this->assertSame('Merci pour votre retour.', $commentaire->fresh()->reponse);
        $this->assertSame(3, $commentaire->etapes()->count());
        $this->assertSame('Idée', $idee->fresh()->intitule());   // plus de titre

        // Le voisin n'est pas touché.
        $this->assertSame($this->voisin->id, $voisine->fresh()->user_id);
        $this->assertSame('Idée du voisin', $voisine->fresh()->titre);
        $this->assertNull($voisine->fresh()->anonymisee_at);
    }

    public function test_the_updated_at_of_anonymised_contributions_is_kept(): void
    {
        [$avis] = $this->contributionsHabitant();
        $avis->forceFill(['updated_at' => now()->subDays(9)])->saveQuietly();
        $avant = $avis->fresh()->updated_at->toDateTimeString();

        app(SuppressionCompte::class)->supprimer($this->habitant);

        $this->assertSame($avant, $avis->fresh()->updated_at->toDateTimeString());
    }

    public function test_the_journal_entry_of_the_deletion_is_unchanged_and_has_no_contribution_content(): void
    {
        $this->contributionsHabitant();

        app(SuppressionCompte::class)->supprimer($this->habitant);

        $entree = JournalActivite::where('action', ActionJournal::CompteSupprime->value)->firstOrFail();
        $this->assertSame('Compte n° '.$this->habitant->id.' supprimé par son titulaire', $entree->detail);
        $tout = json_encode($entree->getAttributes(), JSON_UNESCAPED_UNICODE);
        foreach (['Mon avis personnel', 'Mon idée', 'Camille', 'camille@example.test', 'PA-'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $tout);
        }
    }

    public function test_if_the_journal_fails_contributions_are_not_anonymised(): void
    {
        $this->contributionsHabitant();
        JournalActivite::creating(fn () => throw new RuntimeException('journal indisponible'));

        try {
            app(SuppressionCompte::class)->supprimer($this->habitant);
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException) {
        }

        $this->assertNotNull($this->habitant->fresh());
        $this->assertSame(3, Contribution::where('user_id', $this->habitant->id)->whereNull('anonymisee_at')->count());
        $this->assertSame('Mon avis personnel sur la place.', Contribution::where('type', 'avis')->firstOrFail()->message);
    }

    public function test_an_anonymised_contribution_belongs_to_nobody_and_stays_visible_to_the_admin(): void
    {
        [$avis] = $this->contributionsHabitant();
        app(SuppressionCompte::class)->supprimer($this->habitant);

        $this->actingAs($this->voisin)->get(route('mes-contributions.show', $avis))->assertForbidden();
        $this->actingAs($this->voisin)->get(route('mes-contributions.index'))->assertDontSee($avis->reference);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.participation.show', $avis))
            ->assertOk()->assertSee($avis->reference)->assertSee('a supprimé son compte')->assertDontSee('Mon avis personnel');
    }

    public function test_the_deletion_information_page_mentions_contributions(): void
    {
        $this->actingAs($this->habitant)->get(route('mes-donnees.suppression'))
            ->assertSee('Le titre et le texte de vos contributions')->assertSee('Vos contributions, sans votre nom ni votre texte');
    }

    // --- Mon dossier ---

    public function test_the_dossier_lists_my_contributions_only(): void
    {
        [$avis, $idee, $commentaire] = $this->contributionsHabitant();
        $autre = Contribution::factory()->idee()->create(['user_id' => $this->voisin->id, 'titre' => 'Idée du voisin', 'message' => 'Message secret du voisin.']);

        $html = $this->actingAs($this->habitant)->get(route('mes-donnees.dossier'))->assertOk()->getContent();

        foreach ([$avis->reference, $idee->reference, $commentaire->reference, 'Mon avis personnel sur la place.', 'Merci pour votre retour.',
            'Vos contributions', 'Vous avez déposé 3 contributions.', '1 est prise en compte par la ville.'] as $texte) {
            $this->assertStringContainsString($texte, $html);
        }
        $this->assertStringNotContainsString($autre->reference, $html);
        $this->assertStringNotContainsString('Message secret du voisin.', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_the_dossier_says_so_without_contributions_and_downloads_the_same_content(): void
    {
        $this->actingAs($this->habitant)->get(route('mes-donnees.dossier'))->assertSee('Vous n\'avez encore déposé aucune contribution');

        $avis = Contribution::factory()->create(['user_id' => $this->habitant->id, 'message' => 'Un avis à garder dans le dossier.']);
        $reponse = $this->actingAs($this->habitant)->get(route('mes-donnees.dossier.telecharger'));
        $reponse->assertOk()->assertSee($avis->reference)->assertSee('Un avis à garder dans le dossier.');
        $this->assertStringContainsString('no-store', (string) $reponse->headers->get('Cache-Control'));
    }

    public function test_the_conservation_table_and_the_deletion_paragraph_mention_contributions(): void
    {
        $this->actingAs($this->habitant)->get(route('mes-donnees.dossier'))
            ->assertSee('Vos contributions : avis sur un projet, idées et commentaires sur un service')
            ->assertSee('sont anonymisées de la même façon');
    }

    public function test_the_dossier_does_not_depend_on_the_number_of_contributions_for_queries(): void
    {
        $mesure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->habitant)->get(route('mes-donnees.dossier'))->assertOk();

            return count(DB::getQueryLog());
        };
        // Un de chaque type d'abord (le chargement du projet et du service ne se fait que s'il y en a), puis beaucoup plus.
        Contribution::factory()->create(['user_id' => $this->habitant->id]);
        Contribution::factory()->idee()->create(['user_id' => $this->habitant->id]);
        Contribution::factory()->commentaire()->create(['user_id' => $this->habitant->id]);
        $deux = $mesure();
        Contribution::factory()->idee()->count(5)->create(['user_id' => $this->habitant->id]);
        Contribution::factory()->commentaire()->count(3)->create(['user_id' => $this->habitant->id]);

        $this->assertSame($deux, $mesure());
    }

    // --- Seeder ---

    private function demoCitoyen(): User
    {
        return User::factory()->create(['email' => 'citoyen@novaterra.test']);
    }

    public function test_the_seeder_creates_three_projects_in_three_states_with_relative_dates(): void
    {
        $this->seed(ParticipationSeeder::class);

        $etats = Projet::orderBy('id')->get()->mapWithKeys(fn (Projet $p) => [$p->slug => $p->etatConsultation()]);
        $this->assertSame(EtatConsultation::Ouverte, $etats['amenagement-place-du-port']);
        $this->assertSame(EtatConsultation::AVenir, $etats['nouveau-reseau-de-bus']);
        $this->assertSame(EtatConsultation::Close, $etats['jardins-partages']);
        $this->assertTrue(Projet::publies()->count() === 3);
        $this->assertNotNull(Projet::where('slug', 'jardins-partages')->value('bilan'));
    }

    public function test_the_seeder_creates_demo_contributions_with_references_and_coherent_steps(): void
    {
        $citoyen = $this->demoCitoyen();

        $this->seed(ParticipationSeeder::class);

        $this->assertSame(3, Contribution::where('user_id', $citoyen->id)->count()); // pas de service « etat-civil » dans ce test
        foreach (Contribution::all() as $c) {
            $this->assertMatchesRegularExpression('/^PA-\d{4}-\d{5}$/', $c->reference);
            $this->assertSame($c->statut, $c->etapes()->get()->last()->statut, 'la dernière étape est le statut actuel');
            $this->assertSame(0, $c->etapes()->whereNull('vu_at')->where('statut', '!=', 'recue')->count());
        }
        $prise = Contribution::where('statut', 'prise_en_compte')->firstOrFail();
        $this->assertSame(['recue', 'examinee', 'prise_en_compte'], $prise->etapes->map(fn ($e) => $e->statut->value)->all());
        $this->assertNotNull($prise->reponse);
        $this->assertEqualsCanonicalizing(['avis', 'idee'], Contribution::pluck('type')->map->value->unique()->values()->all());
        $this->assertTrue(Contribution::where('type', TypeContribution::Idee->value)->whereNull('projet_id')->exists());
    }

    public function test_the_seeder_is_idempotent_and_never_overwrites_existing_projects(): void
    {
        $this->demoCitoyen();
        Service::factory()->create(['slug' => 'etat-civil', 'nom' => 'État civil', 'actif' => true]);
        Projet::factory()->create(['slug' => 'jardins-partages', 'titre' => 'Titre modifié par l\'admin']);

        $this->seed(ParticipationSeeder::class);
        $projets = Projet::count();
        $contributions = Contribution::count();
        $etapes = ContributionEtape::count();

        $this->seed(ParticipationSeeder::class);

        $this->assertSame($projets, Projet::count());
        $this->assertSame($contributions, Contribution::count());
        $this->assertSame($etapes, ContributionEtape::count());
        $this->assertSame(4, $contributions); // 3 + le commentaire sur « état civil »
        $this->assertSame('Titre modifié par l\'admin', Projet::where('slug', 'jardins-partages')->value('titre'));
    }

    public function test_the_seeder_works_without_the_demo_citizen_and_only_creates_projects(): void
    {
        $this->seed(ParticipationSeeder::class);

        $this->assertSame(3, Projet::count());
        $this->assertSame(0, Contribution::count());
    }

    public function test_the_demo_data_does_not_trigger_a_notice_and_reads_well_on_the_pages(): void
    {
        $citoyen = $this->demoCitoyen();
        $this->seed(ParticipationSeeder::class);

        $this->actingAs($citoyen)->get(route('dashboard'))->assertDontSee('a été examinée par la ville')->assertDontSee('est prise en compte : la ville vous a répondu');
        $this->actingAs($citoyen)->get(route('mes-contributions.index'))->assertSee('Prise en compte')->assertSee('Examinée')->assertSee('Reçue');
        $this->get(route('projets.index'))->assertSee('Aménagement de la place du Port')->assertSee('Consultation ouverte');
    }

    public function test_the_database_seeder_calls_it_for_fresh_installs_and_it_is_separate_from_the_service_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Projet::count());
        $this->assertGreaterThanOrEqual(3, Contribution::count());
        $this->assertSame(4, Contribution::where('user_id', User::where('email', 'citoyen@novaterra.test')->value('id'))->count());

        $source = file_get_contents(base_path('database/seeders/ParticipationSeeder.php'));
        $this->assertStringNotContainsString('ServiceSeeder', str_replace('ServiceSeeder n\'est pas relancé', '', $source));
    }
}
