<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\Statut;
use App\Models\Demande;
use App\Models\JournalActivite;
use App\Models\Service;
use App\Models\User;
use App\Services\SuppressionCompte;
use App\Services\SuppressionRefusee;
use App\Services\TransitionDemande;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/** F33 : suppression de son compte en deux étapes, demandes anonymisées, journal sans donnée personnelle. */
class SuppressionCompteTest extends TestCase
{
    use RefreshDatabase;

    private User $habitant;

    private User $voisin;

    private User $agent;

    /** @var list<Demande> */
    private array $demandes = [];

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 12:00:00');
        Model::preventLazyLoading();

        $this->habitant = User::factory()->create(['name' => 'Camille Habitant', 'email' => 'camille@example.test', 'preferences' => ['taille' => 'grand']]);
        $this->voisin = User::factory()->create(['name' => 'Victor Voisin', 'email' => 'victor@example.test']);
        $this->agent = User::factory()->agent()->create(['name' => 'Alex Agent']);
        $this->service = Service::factory()->create(['nom' => 'Transports']);

        foreach ([['Lampadaire cassé', 'Texte privé un'], ['Arrêt déplacé', 'Texte privé deux']] as [$objet, $message]) {
            $this->demandes[] = Demande::factory()->create([
                'user_id' => $this->habitant->id, 'objet' => $objet, 'message' => $message, 'service_id' => $this->service->id,
                'created_at' => '2026-09-01 10:00:00', 'updated_at' => '2026-09-01 10:00:00',
            ])->refresh();
        }
        Carbon::setTestNow('2026-10-02 09:00:00');
        $transition = app(TransitionDemande::class);
        $transition->passer($this->demandes[0], Statut::Nouvelle, $this->agent);
        $transition->passer($this->demandes[0], Statut::EnCours, $this->agent);
        Carbon::setTestNow('2026-10-04 12:00:00');
        JournalActivite::query()->count(); // les entrées de transition existent déjà (2) : on ne les compte pas dans les tests
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    private function sessions(User $user, int $nombre): void
    {
        foreach (range(1, $nombre) as $i) {
            DB::table('sessions')->insert([
                'id' => 'session-'.$user->id.'-'.$i, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
                'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time(),
            ]);
        }
    }

    private function supprimer(array $donnees)
    {
        return $this->actingAs($this->habitant)->delete(route('mes-donnees.suppression.destroy'), $donnees);
    }

    /** @return array<int, array<string, mixed>> */
    private function etatDemandes(): array
    {
        return Demande::whereIn('id', array_map(fn ($d) => $d->id, $this->demandes))->orderBy('id')->get()
            // Valeurs brutes (chaînes) : comparables telles quelles, contrairement à des objets Carbon.
            ->map(fn ($d) => collect(['id', 'reference', 'service_id', 'statut', 'created_at', 'updated_at', 'traitee_at', 'agent_id'])
                ->mapWithKeys(fn ($c) => [$c => $d->getRawOriginal($c)])->all() + ['etapes' => $d->etapes()->count()])
            ->all();
    }

    // --- Parcours en deux étapes, sans JavaScript ---

    public function test_step_one_explains_what_is_deleted_and_kept_with_a_way_back(): void
    {
        $page = $this->actingAs($this->habitant)->get('/mes-donnees/suppression')->assertOk()
            ->assertSee('Étape 1 sur 2')
            ->assertSee('Ce qui sera supprimé')
            ->assertSee('Ce qui sera conservé, sous forme anonyme')
            ->assertSee('Cette action est définitive')
            ->assertSee('Annuler et revenir')
            ->assertSee(route('mes-donnees.index'))
            ->assertSee(route('mes-donnees.suppression.confirmer'))
            ->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
        $this->assertStringNotContainsString('x-data', explode('<main', $page)[1]); // aucune modale JavaScript
    }

    public function test_step_two_has_a_password_a_checkbox_and_labels_and_changes_nothing_by_itself(): void
    {
        $page = $this->actingAs($this->habitant)->get('/mes-donnees/suppression/confirmer')->assertOk()
            ->assertSee('Étape 2 sur 2')
            ->assertSee('Supprimer définitivement mon compte')
            ->assertSee('Je comprends que cette action est définitive')
            ->getContent();

        $this->assertStringContainsString('for="password"', $page);
        $this->assertStringContainsString('for="comprends"', $page);
        $this->assertStringContainsString('name="_method" value="DELETE"', $page);
        $this->assertNotNull($this->habitant->fresh());
    }

    // --- Refus : rien n'est modifié ---

    public function test_wrong_password_is_refused_without_any_change(): void
    {
        $this->sessions($this->habitant, 2);
        $avant = $this->etatDemandes();
        $journal = JournalActivite::count();

        $this->supprimer(['password' => 'mauvais', 'comprends' => '1'])
            ->assertRedirect(route('mes-donnees.suppression.confirmer'))
            ->assertSessionHasErrors(['password' => 'Le mot de passe est incorrect.']);

        $this->assertNotNull($this->habitant->fresh());
        $this->assertSame($avant, $this->etatDemandes());
        $this->assertSame('Lampadaire cassé', $this->demandes[0]->fresh()->objet);
        $this->assertSame($this->habitant->id, $this->demandes[0]->fresh()->user_id);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $this->habitant->id)->count());
        $this->assertSame($journal, JournalActivite::count());
        $this->assertSame(['taille' => 'grand'], $this->habitant->fresh()->preferences);
    }

    public function test_unchecked_box_or_missing_password_is_refused_without_any_change(): void
    {
        $this->supprimer(['password' => 'password'])->assertSessionHasErrors('comprends');
        $this->supprimer(['password' => 'password', 'comprends' => '0'])->assertSessionHasErrors('comprends');
        $this->supprimer(['comprends' => '1'])->assertSessionHasErrors('password');

        $this->assertNotNull($this->habitant->fresh());
        $this->assertSame('Lampadaire cassé', $this->demandes[0]->fresh()->objet);
    }

    public function test_the_confirmation_page_shows_errors_linked_to_their_fields(): void
    {
        $this->actingAs($this->habitant)->followingRedirects()
            ->delete(route('mes-donnees.suppression.destroy'), ['password' => 'mauvais'])
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('Votre compte n&#039;a pas été supprimé', false)
            ->assertSee('Le mot de passe est incorrect.')
            ->assertSee('Cochez la case pour confirmer que vous avez compris.')
            ->assertSee('aria-describedby="password-erreur"', false)
            ->assertSee('aria-describedby="comprends-erreur"', false);
    }

    public function test_deletion_attempts_are_throttled_five_per_minute(): void
    {
        $this->actingAs($this->habitant);
        foreach (range(1, 5) as $i) {
            $this->delete(route('mes-donnees.suppression.destroy'), ['password' => 'mauvais', 'comprends' => '1'])->assertSessionHasErrors('password');
        }
        $this->delete(route('mes-donnees.suppression.destroy'), ['password' => 'password', 'comprends' => '1'])->assertStatus(429);

        $this->assertNotNull($this->habitant->fresh());
    }

    // --- Succès ---

    public function test_success_deletes_the_account_sessions_and_preferences_and_anonymises_the_demandes(): void
    {
        $this->sessions($this->habitant, 3);
        $this->sessions($this->voisin, 1);
        DB::table('password_reset_tokens')->insert(['email' => 'camille@example.test', 'token' => 'x', 'created_at' => now()]);
        $autreDemande = Demande::factory()->create(['user_id' => $this->voisin->id, 'objet' => 'Demande du voisin', 'message' => 'Secret du voisin']);

        $avant = $this->etatDemandes();
        $id = $this->habitant->id;

        $this->supprimer(['password' => 'password', 'comprends' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('compte-supprime'));

        // Compte, sessions de tous les appareils, jeton de réinitialisation : supprimés.
        $this->assertGuest();
        $this->assertNull(User::find($id));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $this->voisin->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'camille@example.test')->count());

        // Demandes anonymisées, jamais supprimées : référence, service, statuts, dates, étapes conservés.
        $apres = $this->etatDemandes();
        $this->assertSame($avant, $apres);
        foreach ($this->demandes as $d) {
            $d = $d->fresh();
            $this->assertNull($d->user_id);
            $this->assertSame(SuppressionCompte::TEXTE_SUPPRIME, $d->objet);
            $this->assertSame(SuppressionCompte::TEXTE_SUPPRIME, $d->message);
            $this->assertNotNull($d->anonymisee_at);
            $this->assertTrue($d->estAnonymisee());
            $this->assertFalse($d->estImportee());
            $this->assertSame('Demandeur supprimé', $d->nom_demandeur);
        }
        $this->assertSame(3, $this->demandes[0]->etapes()->count()); // nouvelle, en cours, traitée
        $this->assertSame($this->service->id, $this->demandes[1]->fresh()->service_id);

        // Les demandes du voisin ne sont pas touchées.
        $this->assertSame('Demande du voisin', $autreDemande->fresh()->objet);
        $this->assertSame($this->voisin->id, $autreDemande->fresh()->user_id);
        $this->assertNull($autreDemande->fresh()->anonymisee_at);
    }

    public function test_journal_entry_has_only_the_account_number(): void
    {
        $id = $this->habitant->id;
        $avant = JournalActivite::count();

        $this->supprimer(['password' => 'password', 'comprends' => '1']);

        $this->assertSame($avant + 1, JournalActivite::count());
        $entree = JournalActivite::latest('id')->firstOrFail();
        $this->assertSame(ActionJournal::CompteSupprime, $entree->action);
        $this->assertNull($entree->acteur_id);
        $this->assertSame('Compte n° '.$id, $entree->acteur_nom);
        $this->assertSame('Compte n° '.$id, $entree->objet_libelle);
        $this->assertSame('Compte n° '.$id.' supprimé par son titulaire', $entree->detail);

        $contenu = JournalActivite::all()->toJson(JSON_UNESCAPED_UNICODE);
        foreach (['Camille', 'camille@example.test', 'Texte privé', 'Lampadaire'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $contenu, $interdit);
        }
    }

    public function test_after_deletion_the_session_is_closed_and_the_public_confirmation_page_is_shown(): void
    {
        $this->supprimer(['password' => 'password', 'comprends' => '1']);

        $this->assertGuest();
        $this->get('/mes-donnees')->assertRedirect(route('login'));

        auth()->logout();
        $page = $this->get('/compte-supprime')->assertOk()
            ->assertSee('Votre compte a été supprimé')
            ->assertSee('Ce qui a été conservé')
            ->assertSee('sous forme anonyme')
            ->getContent();
        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
    }

    public function test_the_old_account_cannot_log_in_again(): void
    {
        $this->supprimer(['password' => 'password', 'comprends' => '1']);
        auth()->logout();

        $this->post('/login', ['email' => 'camille@example.test', 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_anonymised_demandes_stay_visible_to_agents_as_deleted_requester(): void
    {
        $this->supprimer(['password' => 'password', 'comprends' => '1']);
        auth()->logout();

        $this->actingAs($this->agent)->get('/agent/demandes')->assertOk()
            ->assertSee($this->demandes[0]->reference)
            ->assertSee($this->demandes[1]->reference)
            ->assertSee('Demandeur supprimé')
            ->assertDontSee('Camille')
            ->assertDontSee('Texte privé')
            ->assertSeeInOrder(['Toutes', '(2)']);

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $this->demandes[0]))->assertOk()
            ->assertSee('Demandeur supprimé')
            ->assertSee('Transports')
            ->assertSee('[Contenu supprimé à la demande de l&#039;habitant]', false);

        // Un agent peut encore faire avancer une demande anonymisée.
        $this->actingAs($this->agent)->patch(route('agent.demandes.statut', $this->demandes[1]), ['statut' => 'nouvelle'])->assertSessionHas('succes');
        $this->assertSame(Statut::EnCours, $this->demandes[1]->fresh()->statut);
    }

    public function test_a_demande_anonymised_without_service_is_not_taken_for_an_imported_one(): void
    {
        $sans = Demande::factory()->create(['user_id' => $this->habitant->id, 'service_id' => null])->refresh();

        $this->supprimer(['password' => 'password', 'comprends' => '1']);
        auth()->logout();

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $sans))->assertOk()
            ->assertSee('À orienter')
            ->assertDontSee('Non précisé');
    }

    // --- Transaction ---

    public function test_if_the_journal_fails_nothing_is_deleted(): void
    {
        $this->sessions($this->habitant, 2);
        $avant = $this->etatDemandes();
        JournalActivite::creating(fn () => throw new RuntimeException('journal indisponible'));

        try {
            app(SuppressionCompte::class)->supprimer($this->habitant);
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException $e) {
            $this->assertSame('journal indisponible', $e->getMessage());
        }

        $this->assertNotNull($this->habitant->fresh());
        $this->assertSame($avant, $this->etatDemandes());
        $this->assertSame('Lampadaire cassé', $this->demandes[0]->fresh()->objet);
        $this->assertNull($this->demandes[0]->fresh()->anonymisee_at);
        $this->assertSame($this->habitant->id, $this->demandes[0]->fresh()->user_id);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $this->habitant->id)->count());
    }

    // --- Le service ---

    public function test_the_service_refuses_agents_and_admins_and_the_last_admin(): void
    {
        $admin = User::factory()->admin()->create(); // seul admin
        $this->assertSame(1, User::where('role', 'admin')->count());

        foreach ([$this->agent, $admin] as $compte) {
            try {
                app(SuppressionCompte::class)->supprimer($compte);
                $this->fail('Le service aurait dû refuser ce compte.');
            } catch (SuppressionRefusee $e) {
                $this->assertSame('Seul un compte citoyen peut être supprimé par ce parcours.', $e->getMessage());
            }
            $this->assertNotNull($compte->fresh());
        }
        $this->assertSame(0, JournalActivite::where('action', ActionJournal::CompteSupprime->value)->count());
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_the_service_can_be_used_by_an_agent_on_a_citizen_account(): void
    {
        app(SuppressionCompte::class)->supprimer($this->habitant, $this->agent);

        $this->assertNull($this->habitant->fresh());
        $entree = JournalActivite::latest('id')->firstOrFail();
        $this->assertSame($this->agent->id, $entree->acteur_id);
        $this->assertSame('Compte n° '.$this->habitant->id.' supprimé par un agent', $entree->detail);
        $this->assertTrue($this->demandes[0]->fresh()->estAnonymisee());
    }

    public function test_a_citizen_cannot_delete_another_citizen_through_the_service(): void
    {
        try {
            app(SuppressionCompte::class)->supprimer($this->voisin, $this->habitant);
            $this->fail('Le journal aurait dû refuser cet acteur.');
        } catch (InvalidArgumentException) {
        }

        $this->assertNotNull($this->voisin->fresh());
    }

    // --- Accès ---

    public function test_agent_and_admin_cannot_delete_their_account_by_the_route(): void
    {
        foreach ([$this->agent, User::factory()->admin()->create()] as $compte) {
            $this->actingAs($compte)->delete(route('mes-donnees.suppression.destroy'), ['password' => 'password', 'comprends' => '1'])->assertForbidden();
            $this->assertNotNull($compte->fresh());
        }
    }

    public function test_the_old_profile_route_no_longer_exists(): void
    {
        $this->actingAs($this->habitant)->delete('/profile', ['password' => 'password'])->assertStatus(405);

        $this->assertNotNull($this->habitant->fresh());
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('profile.destroy'));
    }
}
