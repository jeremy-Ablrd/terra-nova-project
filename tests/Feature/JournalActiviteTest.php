<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\Disponibilite;
use App\Enums\Niveau;
use App\Enums\Role;
use App\Models\Alerte;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class JournalActiviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Heure figée : 03/10/2026 10:00 à La Réunion.
        Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    // --- Aides -----------------------------------------------------------------------------------------

    private function admin(string $nom = 'Sacha Admin'): User
    {
        return User::factory()->admin()->create(['name' => $nom]);
    }

    private function agent(string $nom = 'Alex Agent'): User
    {
        return User::factory()->agent()->create(['name' => $nom]);
    }

    private function formulaireService(array $surcharge = []): array
    {
        return array_merge(['disponibilite' => 'disponible', 'prioritaire' => '0', 'urgence' => '0'], $surcharge);
    }

    private function donneesAlerte(array $surcharge = []): array
    {
        return array_merge([
            'titre' => 'Coupure d\'eau',
            'niveau' => 'info',
            'ce_qui_se_passe' => 'Une coupure d\'eau est prévue demain matin.',
            'ce_quil_faut_faire' => 'Faites des réserves d\'eau dès ce soir.',
        ], $surcharge);
    }

    /** Tout le contenu du journal, en une seule chaîne (pour les contrôles de confidentialité). */
    private function contenuDuJournal(): string
    {
        return json_encode(DB::table('journal_activites')->get()->all(), JSON_UNESCAPED_UNICODE);
    }

    private function assertImmuable(callable $tentative, string $message): void
    {
        try {
            $tentative();
        } catch (LogicException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail($message);
    }

    // --- Le service Journal ----------------------------------------------------------------------------

    public function test_an_entry_records_the_actor_a_copy_of_their_name_their_role_the_object_and_the_time(): void
    {
        $admin = $this->admin();
        $service = Service::factory()->create(['nom' => 'Transports']);

        Journal::enregistrer($admin, ActionJournal::ServiceModifie, $service, 'disponibilité : A → B');

        $entree = JournalActivite::firstOrFail();
        $this->assertSame($admin->id, $entree->acteur_id);
        $this->assertSame('Sacha Admin', $entree->acteur_nom);
        $this->assertSame(Role::Admin, $entree->acteur_role);
        $this->assertSame(ActionJournal::ServiceModifie, $entree->action);
        $this->assertSame('service', $entree->objet_type);
        $this->assertSame($service->id, $entree->objet_id);
        $this->assertSame('Transports', $entree->objet_libelle);
        $this->assertSame('disponibilité : A → B', $entree->detail);
        $this->assertTrue($entree->created_at->equalTo(now()));
    }

    public function test_objects_are_described_without_personal_data(): void
    {
        $admin = $this->admin();
        $habitant = User::factory()->create(['name' => 'Camille Secret', 'email' => 'camille@example.com']);
        $demande = Demande::factory()->create();
        $alerte = Alerte::factory()->create(['titre' => 'Montée des eaux']);

        Journal::enregistrer($admin, ActionJournal::StatutDemandeModifie, $demande->fresh());
        Journal::enregistrer($admin, ActionJournal::AlertePubliee, $alerte);
        Journal::enregistrer($admin, ActionJournal::RoleModifie, $habitant, 'rôle : Citoyen → Agent');
        Journal::enregistrer($admin, ActionJournal::SynchronisationLancee);
        Journal::enregistrer($admin, ActionJournal::CompteSupprime);

        [$d, $a, $c, $s, $p] = JournalActivite::orderBy('id')->get()->all();

        $this->assertSame(['demande', $demande->id, $demande->fresh()->reference], [$d->objet_type, $d->objet_id, $d->objet_libelle]);
        $this->assertSame(['alerte', $alerte->id, 'Montée des eaux'], [$a->objet_type, $a->objet_id, $a->objet_libelle]);
        $this->assertSame(['compte', $habitant->id, 'Compte n° '.$habitant->id], [$c->objet_type, $c->objet_id, $c->objet_libelle]);
        $this->assertSame(['synchronisation', null, 'Synchronisation des demandes'], [$s->objet_type, $s->objet_id, $s->objet_libelle]);
        $this->assertSame(['plateforme', null, 'Plateforme'], [$p->objet_type, $p->objet_id, $p->objet_libelle]);
        $this->assertStringNotContainsString('Camille', $this->contenuDuJournal());
        $this->assertStringNotContainsString('camille@example.com', $this->contenuDuJournal());
    }

    public function test_only_agents_and_admins_are_journaled(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            Journal::enregistrer(User::factory()->create(), ActionJournal::ServiceModifie);
        } finally {
            $this->assertSame(0, JournalActivite::count());
        }
    }

    public function test_an_agent_can_be_the_actor(): void
    {
        $agent = $this->agent();

        Journal::enregistrer($agent, ActionJournal::StatutDemandeModifie, Demande::factory()->create()->fresh(), 'statut : Nouvelle → En cours');

        $this->assertSame(Role::Agent, JournalActivite::firstOrFail()->acteur_role);
    }

    public function test_long_labels_and_details_are_truncated_to_fit_the_columns(): void
    {
        Journal::enregistrer($this->admin(), ActionJournal::AlertePubliee, Alerte::factory()->create(['titre' => str_repeat('T', 400)]), str_repeat('d', 400));

        $entree = JournalActivite::firstOrFail();
        $this->assertSame(255, mb_strlen($entree->objet_libelle));
        $this->assertLessThanOrEqual(255, mb_strlen($entree->detail));
        $this->assertStringEndsWith('…', $entree->detail);
    }

    public function test_the_entry_belongs_to_the_surrounding_transaction(): void
    {
        $admin = $this->admin();

        try {
            DB::transaction(function () use ($admin) {
                Journal::enregistrer($admin, ActionJournal::ServiceModifie, Service::factory()->create());
                throw new RuntimeException('l\'action échoue');
            });
        } catch (RuntimeException) {
            // attendu
        }

        $this->assertSame(0, JournalActivite::count());
    }

    public function test_only_the_creation_date_exists(): void
    {
        $this->assertFalse(Schema::hasColumn('journal_activites', 'updated_at'));
        $this->assertTrue(Schema::hasColumn('journal_activites', 'created_at'));

        Journal::enregistrer($this->admin(), ActionJournal::SynchronisationLancee);
        $this->assertArrayNotHasKey('updated_at', JournalActivite::firstOrFail()->getAttributes());
    }

    // --- Lecture seule : ni mise à jour, ni suppression -------------------------------------------------

    public function test_an_entry_cannot_be_updated_or_deleted(): void
    {
        $entree = Journal::enregistrer($this->admin(), ActionJournal::ServiceModifie, Service::factory()->create(['nom' => 'Transports']), 'détail d\'origine');
        $entree = $entree->fresh();

        $this->assertImmuable(fn () => $entree->update(['detail' => 'modifié']), 'update() devrait lever une exception.');
        $this->assertImmuable(fn () => $entree->delete(), 'delete() devrait lever une exception.');
        $this->assertImmuable(fn () => $entree->forceDelete(), 'forceDelete() devrait lever une exception.');
        $this->assertImmuable(fn () => $entree->deleteQuietly(), 'deleteQuietly() devrait lever une exception.');
        $this->assertImmuable(function () use ($entree) {
            $entree->detail = 'modifié';
            $entree->save();
        }, 'save() d\'une entrée existante devrait lever une exception.');
        $this->assertImmuable(fn () => $entree->push(), 'push() d\'une entrée modifiée devrait lever une exception.');

        $this->assertSame(1, JournalActivite::count());
        $this->assertSame('détail d\'origine', JournalActivite::firstOrFail()->detail);
    }

    public function test_the_query_builder_cannot_update_or_delete_in_bulk(): void
    {
        $entree = Journal::enregistrer($this->admin(), ActionJournal::ServiceModifie, Service::factory()->create(), 'origine');

        $this->assertImmuable(fn () => JournalActivite::query()->update(['detail' => 'x']), 'update() en masse.');
        $this->assertImmuable(fn () => JournalActivite::where('id', $entree->id)->delete(), 'delete() en masse.');
        $this->assertImmuable(fn () => JournalActivite::query()->forceDelete(), 'forceDelete() en masse.');
        $this->assertImmuable(fn () => JournalActivite::query()->increment('objet_id'), 'increment() en masse.');
        $this->assertImmuable(fn () => JournalActivite::query()->decrement('objet_id'), 'decrement() en masse.');
        $this->assertImmuable(fn () => JournalActivite::truncate(), 'truncate().');
        $this->assertImmuable(fn () => JournalActivite::upsert([['id' => $entree->id, 'detail' => 'x']], ['id'], ['detail']), 'upsert().');

        $this->assertSame(1, JournalActivite::count());
        $this->assertSame('origine', JournalActivite::firstOrFail()->detail);
    }

    public function test_no_route_can_write_the_journal(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'journal'));

        $this->assertGreaterThanOrEqual(1, $routes->count());
        foreach ($routes as $route) {
            $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $route->methods(), $route->uri().' : seules les lectures sont permises.');
        }
    }

    // --- Actions tracées : exactement une entrée, bon acteur, bon rôle, bon libellé ----------------------------

    public function test_role_change_creates_exactly_one_entry(): void
    {
        $admin = $this->admin();
        $cible = User::factory()->create(['name' => 'Camille Secret', 'email' => 'camille@example.com']);

        $this->actingAs($admin)->post(route('admin.comptes.role', $cible), ['role' => 'agent'])->assertSessionHasNoErrors();

        $this->assertSame(1, JournalActivite::count());
        $entree = JournalActivite::firstOrFail();
        $this->assertSame($admin->id, $entree->acteur_id);
        $this->assertSame('Sacha Admin', $entree->acteur_nom);
        $this->assertSame(Role::Admin, $entree->acteur_role);
        $this->assertSame(ActionJournal::RoleModifie, $entree->action);
        $this->assertSame('compte', $entree->objet_type);
        $this->assertSame($cible->id, $entree->objet_id);
        $this->assertSame('Compte n° '.$cible->id, $entree->objet_libelle);
        $this->assertSame('rôle : Citoyen → Agent', $entree->detail);
        $this->assertSame(Role::Agent, $cible->fresh()->role);
    }

    public function test_role_change_without_a_real_change_or_refused_leaves_no_entry(): void
    {
        $admin = $this->admin();
        $cible = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.comptes.role', $cible), ['role' => 'citoyen'])->assertSessionHasNoErrors(); // inchangé
        $this->actingAs($admin)->post(route('admin.comptes.role', $cible), ['role' => 'superman'])->assertSessionHasErrors('role');
        $this->actingAs($admin)->post(route('admin.comptes.role', $admin), ['role' => 'citoyen'])->assertSessionHasErrors('role');  // se retirer son rôle
        $this->actingAs($this->agent())->post(route('admin.comptes.role', $cible), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($cible)->post(route('admin.comptes.role', $cible), ['role' => 'admin'])->assertForbidden();

        $this->assertSame(0, JournalActivite::count());
    }

    public function test_publishing_an_alert_creates_exactly_one_entry(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/alertes', $this->donneesAlerte(['niveau' => 'urgent', 'titre' => 'Montée des eaux']))
            ->assertSessionHasNoErrors();

        $alerte = Alerte::firstOrFail();
        $this->assertSame(1, JournalActivite::count());
        $entree = JournalActivite::firstOrFail();
        $this->assertSame([$admin->id, 'Sacha Admin', Role::Admin], [$entree->acteur_id, $entree->acteur_nom, $entree->acteur_role]);
        $this->assertSame(ActionJournal::AlertePubliee, $entree->action);
        $this->assertSame(['alerte', $alerte->id, 'Montée des eaux'], [$entree->objet_type, $entree->objet_id, $entree->objet_libelle]);
        $this->assertSame('niveau : Urgent', $entree->detail);
    }

    public function test_a_refused_alert_leaves_no_entry(): void
    {
        $this->actingAs($this->admin())->post('/admin/alertes', $this->donneesAlerte(['niveau' => 'zzz']))->assertSessionHasErrors('niveau');
        $this->actingAs($this->agent())->post('/admin/alertes', $this->donneesAlerte())->assertForbidden();

        $this->assertSame(0, JournalActivite::count());
    }

    public function test_ending_and_cancelling_an_alert_each_create_exactly_one_entry(): void
    {
        $admin = $this->admin();
        $enCours = Alerte::factory()->create(['titre' => 'En cours']);
        $programmee = Alerte::factory()->programmee()->create(['titre' => 'Programmée']);
        $terminee = Alerte::factory()->terminee()->create(['titre' => 'Déjà finie']);

        $this->actingAs($admin)->post(route('admin.alertes.terminer', $enCours))->assertRedirect();
        $this->assertSame(1, JournalActivite::count());
        $this->assertSame([ActionJournal::AlerteTerminee, 'En cours', $enCours->id], [JournalActivite::latest('id')->first()->action, JournalActivite::latest('id')->first()->objet_libelle, JournalActivite::latest('id')->first()->objet_id]);

        $this->actingAs($admin)->post(route('admin.alertes.terminer', $programmee))->assertRedirect();
        $this->assertSame(2, JournalActivite::count());
        $derniere = JournalActivite::latest('id')->first();
        $this->assertSame([ActionJournal::AlerteAnnulee, 'Programmée', Role::Admin, 'Sacha Admin'], [$derniere->action, $derniere->objet_libelle, $derniere->acteur_role, $derniere->acteur_nom]);

        // Déjà terminée : rien ne change, donc rien n'est écrit.
        $this->actingAs($admin)->post(route('admin.alertes.terminer', $terminee))->assertRedirect();
        $this->assertSame(2, JournalActivite::count());
    }

    public function test_modifying_a_service_creates_one_entry_with_before_and_after_for_availability_and_priority(): void
    {
        $admin = $this->admin();
        $service = Service::factory()->create(['nom' => 'Transports']);

        $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaireService([
            'disponibilite' => 'interrompu',
            'prioritaire' => '1',
            'motif_interruption' => 'Motif confidentiel de maintenance.',
            'retour_estime_at' => '2026-10-05T14:30',
            'alternative' => 'Alternative confidentielle.',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, JournalActivite::count());
        $entree = JournalActivite::firstOrFail();
        $this->assertSame([$admin->id, 'Sacha Admin', Role::Admin], [$entree->acteur_id, $entree->acteur_nom, $entree->acteur_role]);
        $this->assertSame(ActionJournal::ServiceModifie, $entree->action);
        $this->assertSame(['service', $service->id, 'Transports'], [$entree->objet_type, $entree->objet_id, $entree->objet_libelle]);
        $this->assertSame(
            'disponibilité : Disponible → Service interrompu ; priorité : non → oui ; autres champs : motif, retour estimé, alternative',
            $entree->detail
        );
        // Les valeurs des autres champs ne sont jamais écrites, seulement leurs noms.
        $this->assertStringNotContainsString('confidentiel', $this->contenuDuJournal());
    }

    public function test_only_changed_service_fields_are_named_and_values_stay_out(): void
    {
        $admin = $this->admin();
        $service = Service::factory()->create();

        $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaireService([
            'adresse' => '1 rue Secrète', 'telephone' => '0262 99 99 99', 'urgence' => '1',
        ]))->assertSessionHasNoErrors();

        $detail = JournalActivite::firstOrFail()->detail;
        $this->assertSame('autres champs : adresse, téléphone, urgence', $detail);
        $this->assertStringNotContainsString('Secrète', $this->contenuDuJournal());
        $this->assertStringNotContainsString('99 99', $this->contenuDuJournal());
    }

    public function test_a_service_update_without_any_change_leaves_no_entry(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaireService())->assertSessionHasNoErrors();
        $this->actingAs($this->agent())->put(route('admin.services.update', $service), $this->formulaireService(['prioritaire' => '1']))->assertForbidden();
        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaireService(['disponibilite' => 'interrompu']))->assertSessionHasErrors('motif_interruption');

        $this->assertSame(0, JournalActivite::count());
    }

    public function test_back_in_service_records_the_availability_change(): void
    {
        $service = Service::factory()->interrompu()->create();

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaireService())->assertSessionHasNoErrors();

        $this->assertSame(
            'disponibilité : Service interrompu → Disponible ; autres champs : motif, retour estimé, alternative',
            JournalActivite::firstOrFail()->detail
        );
    }

    // --- Synchronisation manuelle ----------------------------------------------------------------------

    private function simulerApi(array|callable $reponse, int $statut = 200): void
    {
        config(['services.webcup.key' => 'cle-de-test']);
        Http::preventStrayRequests();
        Http::fake(is_callable($reponse) ? $reponse : fn () => Http::response($reponse, $statut));
    }

    public function test_a_manual_synchronisation_creates_exactly_one_entry(): void
    {
        $this->simulerApi(['requests' => [['request_code' => 'R1'], ['request_code' => 'R2']]]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/synchronisation')->assertRedirect();

        $this->assertSame(1, JournalActivite::count());
        $entree = JournalActivite::firstOrFail();
        $this->assertSame([$admin->id, 'Sacha Admin', Role::Admin], [$entree->acteur_id, $entree->acteur_nom, $entree->acteur_role]);
        $this->assertSame(ActionJournal::SynchronisationLancee, $entree->action);
        $this->assertSame(['synchronisation', null, 'Synchronisation des demandes'], [$entree->objet_type, $entree->objet_id, $entree->objet_libelle]);
        $this->assertSame('2 demandes reçues, 2 nouvelles, 0 importées', $entree->detail);
    }

    public function test_a_failed_or_refused_synchronisation_is_still_traced(): void
    {
        $admin = $this->admin();

        $this->simulerApi(['message' => 'interdit'], 403);
        $this->actingAs($admin)->post('/admin/synchronisation')->assertRedirect();
        $this->assertSame('échec : erreur de l\'API', JournalActivite::latest('id')->firstOrFail()->detail);

        $this->simulerApi(fn () => throw new RuntimeException('boom'));
        $this->actingAs($admin)->post('/admin/synchronisation')->assertRedirect();
        $this->assertSame('échec : erreur inattendue', JournalActivite::latest('id')->firstOrFail()->detail);

        $verrou = Cache::lock(NovaTerraApi::LOCK_KEY, 120);
        $verrou->get();
        $this->actingAs($admin)->post('/admin/synchronisation')->assertRedirect();
        $verrou->release();
        $this->assertSame('non exécutée : une synchronisation était déjà en cours', JournalActivite::latest('id')->firstOrFail()->detail);

        $this->assertSame(3, JournalActivite::count());
        $this->assertStringNotContainsString('interdit', $this->contenuDuJournal());
    }

    public function test_the_command_and_non_admins_do_not_write_the_journal(): void
    {
        $this->simulerApi(['requests' => []]);

        $this->artisan('novaterra:sync')->assertExitCode(0);
        $this->actingAs($this->agent())->post('/admin/synchronisation')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/admin/synchronisation')->assertForbidden();

        $this->assertSame(0, JournalActivite::count());
    }

    // --- Prévu mais pas encore branché -----------------------------------------------------------------

    public function test_status_change_and_account_deletion_are_planned_but_not_wired_yet(): void
    {
        $this->assertSame('Statut de demande modifié', ActionJournal::StatutDemandeModifie->label());
        $this->assertSame('Compte supprimé', ActionJournal::CompteSupprime->label());

        // Parcours d'un habitant (inscription de profil, demande) : aucune entrée, ces actions n'existent pas côté agent/admin.
        $habitant = User::factory()->create();
        $this->actingAs($habitant)->post('/contact', ['objet' => 'Une question', 'message' => 'Un message assez long.'])->assertRedirect();
        $this->actingAs($habitant)->delete('/profile', ['password' => 'password']);

        $this->assertSame(0, JournalActivite::count());
    }

    // --- Même transaction : si la trace échoue, la modification est annulée ------------------------------------

    public function test_if_the_entry_cannot_be_written_the_modification_is_rolled_back(): void
    {
        $admin = $this->admin();
        $cible = User::factory()->create();
        $service = Service::factory()->create();
        $enCours = Alerte::factory()->create();
        $programmee = Alerte::factory()->programmee()->create();

        JournalActivite::creating(function () {
            throw new RuntimeException('journal indisponible');
        });

        $this->actingAs($admin)->post(route('admin.comptes.role', $cible), ['role' => 'agent'])->assertStatus(500);
        $this->assertSame(Role::Citoyen, $cible->fresh()->role);

        $this->actingAs($admin)->post('/admin/alertes', $this->donneesAlerte())->assertStatus(500);
        $this->assertSame(2, Alerte::count()); // aucune alerte publiée sans trace

        $this->actingAs($admin)->post(route('admin.alertes.terminer', $enCours))->assertStatus(500);
        $this->assertNull($enCours->fresh()->ends_at);

        $this->actingAs($admin)->post(route('admin.alertes.terminer', $programmee))->assertStatus(500);
        $this->assertTrue($programmee->fresh()->starts_at->isFuture());

        $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaireService(['prioritaire' => '1']))->assertStatus(500);
        $this->assertFalse($service->fresh()->prioritaire);

        $this->assertSame(0, JournalActivite::count());
    }

    // --- Compte supprimé : la trace reste lisible ------------------------------------------------------

    public function test_the_actor_name_is_kept_after_their_account_is_deleted(): void
    {
        $admin = $this->admin('Sacha Admin');
        Journal::enregistrer($admin, ActionJournal::SynchronisationLancee, null, 'avant la suppression');

        $admin->delete();

        $entree = JournalActivite::firstOrFail();
        $this->assertNull($entree->acteur_id);
        $this->assertSame('Sacha Admin', $entree->acteur_nom);
        $this->assertSame(Role::Admin, $entree->acteur_role);

        $this->actingAs($this->agent())->get('/agent/journal')->assertOk()->assertSee('Sacha Admin')->assertSee('Administrateur');
    }

    // --- Confidentialité -------------------------------------------------------------------------------

    public function test_no_password_email_or_personal_data_ever_reaches_the_journal(): void
    {
        $admin = $this->admin();
        $habitant = User::factory()->create(['name' => 'Camille Secret', 'email' => 'camille@example.com']);
        $service = Service::factory()->create(['nom' => 'Transports']);
        $alerte = Alerte::factory()->create(['titre' => 'Titre public']);

        $this->actingAs($admin)->post(route('admin.comptes.role', $habitant), ['role' => 'agent']);
        $this->actingAs($admin)->post('/admin/alertes', $this->donneesAlerte(['ce_qui_se_passe' => 'Contenu sensible de l\'alerte', 'consignes_vulnerables' => 'Consigne sensible']));
        $this->actingAs($admin)->post(route('admin.alertes.terminer', $alerte));
        $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaireService([
            'disponibilite' => 'interrompu', 'motif_interruption' => 'Motif sensible', 'alternative' => 'Alternative sensible',
            'adresse' => '1 rue Secrète', 'telephone' => '0262 99 99 99',
        ]));
        $this->simulerApi(['requests' => [['request_code' => 'R1', 'message_public' => 'Message sensible de citoyen']]]);
        $this->actingAs($admin)->post('/admin/synchronisation');

        $this->assertGreaterThanOrEqual(5, JournalActivite::count());
        $contenu = $this->contenuDuJournal();

        foreach (['camille@example.com', 'Camille', 'sensible', 'Secrète', '99 99', 'password', '$2y$', $admin->email, 'remember_token'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $contenu, "« $interdit » ne doit jamais figurer dans le journal.");
        }
        $this->assertStringNotContainsString('@', $contenu);
    }

    // --- La page /agent/journal ------------------------------------------------------------------------

    public function test_only_agents_can_read_the_journal(): void
    {
        $this->actingAs($this->agent())->get('/agent/journal')->assertOk();
        $this->actingAs($this->admin())->get('/agent/journal')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/agent/journal')->assertForbidden();

        auth()->logout();
        $this->get('/agent/journal')->assertRedirect('/login');
    }

    public function test_the_page_shows_who_did_what_when_on_which_object(): void
    {
        Journal::enregistrer($this->admin('Sacha Admin'), ActionJournal::ServiceModifie, Service::factory()->create(['nom' => 'Transports']), 'priorité : non → oui');

        $reponse = $this->actingAs($this->agent())->get('/agent/journal')->assertOk()
            ->assertSee('03/10/2026 10:00')
            ->assertSee('Sacha Admin')
            ->assertSee('Administrateur')
            ->assertSee('Service modifié')
            ->assertSee('Transports')
            ->assertSee('priorité : non → oui')
            ->assertDontSee('Aucune activité enregistrée');

        $reponse->assertSee('<caption', false)->assertSee('Journal d&#039;activité, de l&#039;action la plus récente à la plus ancienne', false);
        $this->assertSame(5, substr_count($reponse->getContent(), 'scope="col"'));
        foreach (['Date', 'Acteur', 'Action', 'Objet', 'Détail'] as $colonne) {
            $reponse->assertSee('>'.$colonne.'</th>', false);
        }
    }

    public function test_the_page_follows_the_accessibility_rules(): void
    {
        $reponse = $this->actingAs($this->agent())->get('/agent/journal')->assertOk()
            ->assertSee('<title>Journal d&#039;activité – ', false)
            ->assertSee('aria-label="Fil d&#039;Ariane"', false)
            ->assertSee('Aller au contenu');

        $html = $reponse->getContent();
        $this->assertSame(1, preg_match_all('/<h1\b/', $html));
        $this->assertSame(1, preg_match_all('/<main\b/', $html));
        $this->assertDoesNotMatchRegularExpression('/[0-9.]px\b/i', $html); // aucune taille en pixels dans le HTML produit (les classes px-4 ne comptent pas)
    }

    public function test_the_page_is_read_only_with_no_edit_or_delete_control(): void
    {
        Journal::enregistrer($this->admin(), ActionJournal::SynchronisationLancee);

        $html = $this->actingAs($this->agent())->get('/agent/journal')->getContent();

        $this->assertStringNotContainsString(route('agent.journal.index').'" method', $html);
        foreach (['Supprimer', 'Modifier', 'Effacer', 'name="_method"'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $html);
        }
    }

    public function test_empty_states_are_in_french(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->get('/agent/journal')->assertOk()->assertSee('Aucune activité enregistrée pour le moment.');

        Journal::enregistrer($this->admin(), ActionJournal::SynchronisationLancee);
        $this->actingAs($agent)->get('/agent/journal?action=role_modifie')->assertOk()
            ->assertSee('Aucune activité ne correspond à ces filtres.')
            ->assertSee('Voir tout le journal');
    }

    public function test_entries_are_sorted_from_the_most_recent_to_the_oldest(): void
    {
        $admin = $this->admin();
        foreach (['Premier', 'Deuxième', 'Troisième'] as $nom) {
            Journal::enregistrer($admin, ActionJournal::ServiceModifie, Service::factory()->create(['nom' => "Service $nom"]));
            Carbon::setTestNow(now()->addMinutes(5));
        }
        // Même seconde : l'identifiant départage (le dernier créé d'abord).
        Journal::enregistrer($admin, ActionJournal::ServiceModifie, Service::factory()->create(['nom' => 'Service Quatrième']));
        Journal::enregistrer($admin, ActionJournal::ServiceModifie, Service::factory()->create(['nom' => 'Service Cinquième']));

        $this->actingAs($this->agent())->get('/agent/journal')
            ->assertSeeInOrder(['Service Cinquième', 'Service Quatrième', 'Service Troisième', 'Service Deuxième', 'Service Premier']);
    }

    /** 9 entrées : admin = 2 rôles + 1 alerte publiée + 3 services ; agent = 2 statuts + 1 alerte terminée. */
    private function donneesDeFiltres(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $service = Service::factory()->create();
        $alerte = Alerte::factory()->create();
        $habitant = User::factory()->create();

        foreach (range(1, 2) as $i) {
            Journal::enregistrer($admin, ActionJournal::RoleModifie, $habitant, "rôle $i");
        }
        Journal::enregistrer($admin, ActionJournal::AlertePubliee, $alerte);
        foreach (range(1, 3) as $i) {
            Journal::enregistrer($admin, ActionJournal::ServiceModifie, $service, "service $i");
        }
        foreach (range(1, 2) as $i) {
            Journal::enregistrer($agent, ActionJournal::StatutDemandeModifie, Demande::factory()->create()->fresh(), "statut $i");
        }
        Journal::enregistrer($agent, ActionJournal::AlerteTerminee, $alerte);
    }

    public function test_filters_and_counters_are_exact_and_only_show_actions_that_exist(): void
    {
        $this->donneesDeFiltres();
        $agent = User::where('name', 'Alex Agent')->firstOrFail();

        // Sans filtre : 9 entrées ; les actions à 0 (annulée, synchronisation, compte supprimé) n'ont pas de lien.
        $page = $this->actingAs($agent)->get('/agent/journal');
        $page->assertSeeInOrder(['Toutes les actions', '(9)', 'Rôle modifié', '(2)', 'Alerte publiée', '(1)', 'Alerte terminée', '(1)', 'Service modifié', '(3)', 'Statut de demande modifié', '(2)'])
            ->assertSeeInOrder(['Tous les rôles', '(9)', 'Agent', '(3)', 'Administrateur', '(6)']);
        foreach (['Alerte annulée', 'Synchronisation lancée', 'Compte supprimé'] as $absente) {
            $page->assertDontSee($absente);
        }

        // Filtre de rôle : les compteurs d'action en tiennent compte, ceux de rôle non.
        $this->actingAs($agent)->get('/agent/journal?role=admin')
            ->assertSeeInOrder(['Toutes les actions', '(6)', 'Rôle modifié', '(2)', 'Alerte publiée', '(1)', 'Service modifié', '(3)'])
            ->assertSeeInOrder(['Tous les rôles', '(9)', 'Agent', '(3)', 'Administrateur', '(6)'])
            ->assertDontSee('Statut de demande modifié');

        // Filtre d'action : les compteurs de rôle en tiennent compte, ceux d'action non.
        $this->actingAs($agent)->get('/agent/journal?action=statut_demande_modifie')
            ->assertSeeInOrder(['Toutes les actions', '(9)', 'Rôle modifié', '(2)'])
            ->assertSeeInOrder(['Tous les rôles', '(2)', 'Agent', '(2)']);
    }

    public function test_filters_list_only_matching_entries_and_combine(): void
    {
        $this->donneesDeFiltres();
        $agent = User::where('name', 'Alex Agent')->firstOrFail();

        $this->actingAs($agent)->get('/agent/journal?action=service_modifie')->assertSee('service 1')->assertSee('service 3')->assertDontSee('rôle 1')->assertDontSee('statut 1');
        $this->actingAs($agent)->get('/agent/journal?role=agent')->assertSee('statut 1')->assertDontSee('service 1')->assertDontSee('rôle 1');
        $this->actingAs($agent)->get('/agent/journal?role=admin&action=role_modifie')->assertSee('rôle 1')->assertDontSee('service 1')->assertDontSee('statut 1');
        $this->actingAs($agent)->get('/agent/journal?role=agent&action=service_modifie')->assertSee('Aucune activité ne correspond à ces filtres.');
    }

    public function test_each_filter_keeps_the_other_in_its_links(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        $service = Service::factory()->create();
        Journal::enregistrer($admin, ActionJournal::ServiceModifie, $service);
        Journal::enregistrer($agent, ActionJournal::ServiceModifie, $service);
        Journal::enregistrer($admin, ActionJournal::RoleModifie, User::factory()->create());

        $html = $this->actingAs($agent)->get('/agent/journal?action=service_modifie&role=admin')->getContent();

        $this->assertStringContainsString('action=role_modifie&amp;role=admin', $html);   // lien d'action : garde le rôle
        $this->assertStringContainsString('action=service_modifie&amp;role=agent', $html); // lien de rôle : garde l'action
        $this->assertStringContainsString('action=service_modifie"', $html);                // « Tous les rôles » : retire le rôle seulement
        $this->assertStringContainsString('role=admin"', $html);                            // « Toutes les actions » : retire l'action seulement
    }

    public function test_the_active_filter_is_announced_by_aria_current_and_text(): void
    {
        $this->donneesDeFiltres();
        $agent = User::where('name', 'Alex Agent')->firstOrFail();

        $html = $this->actingAs($agent)->get('/agent/journal')->getContent();
        $this->assertSame(2, substr_count($html, 'aria-current="true"')); // « Toutes les actions » et « Tous les rôles »

        $html = $this->actingAs($agent)->get('/agent/journal?action=service_modifie&role=admin')->getContent();
        $this->assertSame(2, substr_count($html, 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/class="[^"]*filtre-actif[^"]*"\s+aria-current="true"\s*>\s*Service modifié/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*filtre-actif[^"]*"\s+aria-current="true"\s*>\s*Administrateur/', $html);
        $this->assertStringContainsString('action : Service modifié', preg_replace('/\s+/', ' ', $html)); // légende du tableau : l'état est aussi dit en texte
    }

    public function test_unknown_filter_values_are_ignored_and_return_the_whole_journal(): void
    {
        $this->donneesDeFiltres();
        $agent = User::where('name', 'Alex Agent')->firstOrFail();

        foreach (['?action=nimporte', '?role=superman', '?role=citoyen', '?action=', '?action[]=role_modifie', '?role[a]=admin', '?action=ROLE_MODIFIE'] as $query) {
            $this->actingAs($agent)->get('/agent/journal'.$query)->assertOk()
                ->assertSee('rôle 1')->assertSee('service 1')->assertSee('statut 1');
        }
    }

    public function test_pagination_shows_twenty_five_entries_and_keeps_the_filters(): void
    {
        $admin = $this->admin();
        $service = Service::factory()->create();
        foreach (range(1, 26) as $i) {
            Journal::enregistrer($admin, ActionJournal::ServiceModifie, $service);
        }
        Journal::enregistrer($admin, ActionJournal::SynchronisationLancee);
        $agent = $this->agent();

        $page1 = $this->actingAs($agent)->get('/agent/journal?action=service_modifie&role=admin')->assertOk();
        $this->assertSame(25, substr_count($page1->getContent(), '<tr class=') + substr_count($page1->getContent(), '<tr>') - 1); // lignes du corps (l'en-tête compte pour 1)
        $this->assertStringContainsString('action=service_modifie&amp;role=admin&amp;page=2', $page1->getContent());

        $page2 = $this->actingAs($agent)->get('/agent/journal?action=service_modifie&role=admin&page=2')->assertOk();
        $this->assertSame(1, substr_count($page2->getContent(), '<tr>') - 1);
        $page2->assertDontSee('Synchronisation lancée</td>', false);
    }

    public function test_query_count_is_constant_and_counters_use_one_grouped_query(): void
    {
        $admin = $this->admin();
        $agent = $this->agent();
        Model::preventLazyLoading(); // toute relation chargée à la demande lève une exception

        $compter = function () use ($agent): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($agent)->get('/agent/journal?role=admin')->assertOk();
            DB::disableQueryLog();
            $requetes = collect(DB::getQueryLog())->pluck('query');

            return [$requetes->count(), $requetes->filter(fn ($q) => str_contains($q, 'group by'))->count()];
        };

        foreach (range(1, 3) as $i) {
            Journal::enregistrer($admin, ActionJournal::SynchronisationLancee);
        }
        [$peu, $groupees] = $compter();

        foreach (range(1, 40) as $i) {
            Journal::enregistrer($admin, ActionJournal::ServiceModifie, Service::factory()->create());
            Journal::enregistrer($this->agent("Agent $i"), ActionJournal::StatutDemandeModifie, Demande::factory()->create()->fresh());
        }
        [$beaucoup, $groupeesApres] = $compter();

        $this->assertSame(1, $groupees);
        $this->assertSame(1, $groupeesApres);
        $this->assertSame($peu, $beaucoup, 'Le nombre de requêtes ne doit pas dépendre du nombre d\'entrées.');
    }

    public function test_the_journal_link_is_in_the_agent_navigation_and_home_only(): void
    {
        $url = route('agent.journal.index');

        $agent = $this->agent();
        $this->actingAs($agent)->get('/agent')
            ->assertSee($url)
            ->assertSee('Suivi de l&#039;activité', false);
        $this->actingAs($agent)->get('/agent/demandes')->assertSee($url);
        $this->assertSame(2, preg_match_all('/<a [^>]*href="'.preg_quote($url, '/').'"[^>]*>\s*Journal d&#039;activité/', $this->actingAs($agent)->get('/agent/demandes')->getContent())); // bureau + mobile

        $this->actingAs($this->admin())->get('/admin')->assertDontSee($url);
        $this->actingAs(User::factory()->create())->get('/espace')->assertDontSee($url);
        auth()->logout();
        $this->get('/')->assertDontSee($url);
    }

    public function test_the_journal_link_on_the_agent_nav_is_the_current_page_when_open(): void
    {
        $html = $this->actingAs($this->agent())->get('/agent/journal')->getContent();

        $this->assertSame(2, preg_match_all('/<a [^>]*href="[^"]*\/agent\/journal"[^>]*aria-current="page"/', $html));
    }
}
