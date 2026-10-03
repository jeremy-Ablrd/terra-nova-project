<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\ApiRequest;
use App\Models\Demande;
use App\Models\User;
use App\Services\ImporteDemandesApi;
use App\Services\NovaTerraApi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportDemandesApiTest extends TestCase
{
    use RefreshDatabase;

    private array $apiBody = [];

    private int $apiStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webcup.key' => 'cle-de-test']);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response($this->apiBody, $this->apiStatus));
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    /** Insère directement une ligne dans api_requests (aucun appel réseau). */
    private function apiRow(string $code, string $type = 'Citoyen', array $overrides = []): ApiRequest
    {
        return ApiRequest::query()->forceCreate(array_merge([
            'request_code' => $code,
            'requester_name' => "Demandeur $code",
            'requester_type' => $type,
            'message_public' => "Message public de la demande $code, assez long pour être lu par un agent.",
            'xp_total' => 100,
            'payload' => ['request_code' => $code],
        ], $overrides));
    }

    private function agent(): User
    {
        return User::factory()->agent()->create();
    }

    public function test_import_creates_demandes_with_all_fields_and_a_generated_reference(): void
    {
        $message = str_repeat('Lampadaire en panne rue des Lilas. ', 8); // > 80 caractères
        $this->apiRow('F21', 'Citoyen', ['message_public' => $message, 'requester_name' => 'Marc Delcourt — Citoyen']);

        $this->artisan('novaterra:import-demandes')
            ->expectsOutput('1 demande citoyenne importée')
            ->assertExitCode(0);

        $demande = Demande::firstOrFail();
        $this->assertSame(Str::limit($message, 80), $demande->objet);
        $this->assertSame($message, $demande->message);
        $this->assertNull($demande->service_id);
        $this->assertNull($demande->user_id);
        $this->assertNull($demande->agent_id);
        $this->assertSame('Marc Delcourt — Citoyen', $demande->demandeur_nom);
        $this->assertSame('F21', $demande->request_code);
        $this->assertSame(Statut::Nouvelle, $demande->statut);
        $this->assertMatchesRegularExpression('/^NT-\d{4}-\d{5}$/', $demande->reference);
        $this->assertTrue($demande->estImportee());
    }

    public function test_two_runs_give_the_same_number_of_rows(): void
    {
        $this->apiRow('F21');
        $this->apiRow('F23');

        $this->artisan('novaterra:import-demandes')->expectsOutput('2 demandes citoyennes importées')->assertExitCode(0);
        $this->artisan('novaterra:import-demandes')->expectsOutput('Aucune nouvelle demande citoyenne importée')->assertExitCode(0);

        $this->assertSame(2, Demande::count());
        $this->assertSame(2, Demande::pluck('reference')->unique()->count());
    }

    public function test_a_modified_status_is_preserved_and_nothing_else_is_overwritten_on_reimport(): void
    {
        $api = $this->apiRow('F21');
        $this->artisan('novaterra:import-demandes')->assertExitCode(0);

        $agent = $this->agent();
        $demande = Demande::firstOrFail();
        $demande->forceFill(['statut' => Statut::EnCours, 'agent_id' => $agent->id])->save();

        // L'API change le texte : la demande locale ne doit pas bouger.
        $api->forceFill(['message_public' => 'Texte modifié côté API.', 'requester_name' => 'Autre nom'])->save();
        $this->artisan('novaterra:import-demandes')->assertExitCode(0);

        $demande->refresh();
        $this->assertSame(1, Demande::count());
        $this->assertSame(Statut::EnCours, $demande->statut);
        $this->assertSame($agent->id, $demande->agent_id);
        $this->assertStringStartsWith('Message public de la demande F21', $demande->message);
        $this->assertSame('Demandeur F21', $demande->demandeur_nom);
    }

    public function test_only_the_exact_citoyen_type_is_imported(): void
    {
        $this->apiRow('F21', 'Citoyen');
        $this->apiRow('D01', 'Institution');
        $this->apiRow('F27', 'Alerte');
        $this->apiRow('F28', 'Alerte sécurité');
        $this->apiRow('F29', 'citoyen');   // casse différente : non importée
        $this->apiRow('F30', 'Citoyen ');  // espace final : non importée

        $this->artisan('novaterra:import-demandes')->assertExitCode(0);

        $this->assertSame(['F21'], Demande::pluck('request_code')->all());
    }

    public function test_rows_without_a_message_are_skipped_without_failing(): void
    {
        $this->apiRow('F21', 'Citoyen', ['message_public' => null]);
        $this->apiRow('F23');

        $this->artisan('novaterra:import-demandes')->assertExitCode(0);

        $this->assertSame(['F23'], Demande::pluck('request_code')->all());
    }

    public function test_request_code_is_unique_in_the_database(): void
    {
        Demande::factory()->importee('F21')->create();

        $this->expectException(QueryException::class);
        Demande::factory()->importee('F21')->create();
    }

    public function test_native_demandes_keep_a_null_request_code_without_conflict(): void
    {
        Demande::factory()->count(3)->create(); // request_code NULL plusieurs fois : autorisé

        $this->assertSame(3, Demande::whereNull('request_code')->count());
    }

    public function test_import_runs_at_the_end_of_the_sync_command_and_shows_its_result(): void
    {
        $this->apiBody = ['requests' => [
            ['request_code' => 'F21', 'requester_type' => 'Citoyen', 'requester_name' => 'Marc', 'message_public' => 'Message citoyen un.'],
            ['request_code' => 'D01', 'requester_type' => 'Institution', 'requester_name' => 'Conseil', 'message_public' => 'Message institution.'],
            ['request_code' => 'F23', 'requester_type' => 'Citoyen', 'requester_name' => 'Jean', 'message_public' => 'Message citoyen deux.'],
        ]];

        $this->artisan('novaterra:sync')
            ->expectsOutput('3 demandes reçues, 3 nouvelles.')
            ->expectsOutput('2 demandes citoyennes importées')
            ->assertExitCode(0);

        $this->assertSame(3, ApiRequest::count());
        $this->assertEqualsCanonicalizing(['F21', 'F23'], Demande::pluck('request_code')->all());
    }

    public function test_a_failed_sync_does_not_prevent_the_import_from_the_database(): void
    {
        $this->apiRow('F21');
        $this->apiRow('D01', 'Institution');
        $this->apiStatus = 403;

        $this->artisan('novaterra:sync')->assertExitCode(1);

        $this->assertSame(['F21'], Demande::pluck('request_code')->all());
        $this->assertStringContainsString('403', Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
        $lock = Cache::lock(NovaTerraApi::LOCK_KEY, 120);
        $this->assertTrue($lock->get(), 'Le verrou doit être libéré.');
        $lock->release();
    }

    public function test_an_import_failure_is_stored_in_last_error_and_the_sync_data_is_kept(): void
    {
        $this->apiStatus = 200;
        $this->apiBody = ['requests' => [['request_code' => 'F21', 'requester_type' => 'Citoyen', 'message_public' => 'Un message.']]];
        $this->app->bind(ImporteDemandesApi::class, fn () => new class extends ImporteDemandesApi
        {
            public function import(): int
            {
                throw new \RuntimeException('import cassé');
            }
        });

        $this->artisan('novaterra:sync')->assertExitCode(1);

        $this->assertSame(1, ApiRequest::count());
        $this->assertSame(NovaTerraApi::unexpectedErrorMessage(), Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
    }

    public function test_admin_button_runs_the_same_code_and_imports(): void
    {
        $this->apiBody = ['requests' => [
            ['request_code' => 'F21', 'requester_type' => 'Citoyen', 'requester_name' => 'Marc', 'message_public' => 'Message citoyen un.'],
        ]];

        $this->actingAs(User::factory()->admin()->create())->followingRedirects()->post('/admin/synchronisation')
            ->assertSee('1 demandes reçues, 1 nouvelles.')
            ->assertSee('1 demande citoyenne importée');

        $this->assertSame(['F21'], Demande::pluck('request_code')->all());
    }

    public function test_no_import_while_the_lock_is_taken(): void
    {
        $this->apiRow('F21');
        $lock = Cache::lock(NovaTerraApi::LOCK_KEY, 120);
        $lock->get();

        $this->artisan('novaterra:import-demandes')
            ->expectsOutput('Une synchronisation est déjà en cours : rien n\'a été lancé.')
            ->assertExitCode(0);
        $this->artisan('novaterra:sync')->assertExitCode(0);

        $this->assertSame(0, Demande::count());
        $lock->release();
    }

    public function test_agent_sees_imported_demandes_without_n_plus_one(): void
    {
        $native = Demande::factory()->for(User::factory()->create(['name' => 'Camille Dupont']))->create(['objet' => 'Demande native']);
        $this->apiRow('F21', 'Citoyen', ['requester_name' => 'Marc Delcourt — Citoyen']);
        $this->apiRow('F23', 'Citoyen', ['requester_name' => 'Jean Morel — Citoyen']);
        $this->artisan('novaterra:import-demandes')->assertExitCode(0);

        Model::preventLazyLoading(); // toute relation chargée à la demande lève une exception

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->agent())->get('/agent/demandes')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $response = $this->actingAs($this->agent())->get('/agent/demandes')
            ->assertOk()
            ->assertSee('Marc Delcourt — Citoyen')
            ->assertSee('Jean Morel — Citoyen')
            ->assertSee('Camille Dupont')
            ->assertSee('Non précisé')
            ->assertSee($native->fresh()->reference)
            ->assertSee(Demande::where('request_code', 'F21')->value('reference'));

        $this->assertSame(1, substr_count($response->getContent(), 'À orienter')); // la demande native sans service

        $few = $count();

        // La page des 15 plus récentes mélange toujours demandes importées et demandes avec compte
        // (la requête des comptes n'est exécutée que si la page en contient) : même forme, plus de lignes.
        foreach (range(30, 37) as $n) {
            Demande::factory()->importee("Z$n", "Habitant $n")->create();
        }
        Demande::factory()->count(7)->create();
        $this->assertSame($few, $count(), 'Le nombre de requêtes ne doit pas dépendre du nombre de demandes.');
    }

    public function test_agent_filter_and_counters_include_imported_demandes(): void
    {
        $this->apiRow('F21');
        $this->apiRow('F23');
        $this->artisan('novaterra:import-demandes')->assertExitCode(0);

        $this->actingAs($this->agent())->get('/agent/demandes?statut=nouvelle')
            ->assertOk()
            ->assertSeeInOrder(['Toutes', '(2)', 'Nouvelle', '(2)'])
            ->assertSee('Demandeur F21');
        $this->actingAs($this->agent())->get('/agent')->assertSee('2 demandes en attente');
    }

    public function test_admin_gets_403_on_imported_demandes(): void
    {
        $demande = Demande::factory()->importee()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/agent/demandes')->assertForbidden();
        $this->actingAs($admin)->get(route('demandes.show', $demande))->assertForbidden();
        $this->assertTrue(Gate::forUser($admin)->denies('view', $demande));
    }

    public function test_citizen_never_sees_imported_demandes(): void
    {
        $citoyen = User::factory()->create();
        $mine = Demande::factory()->for($citoyen)->create(['objet' => 'Ma propre demande']);
        $imported = Demande::factory()->importee('F21', 'Marc Delcourt — Citoyen')->create(['objet' => 'Demande importée visible des agents']);

        $this->actingAs($citoyen)->get('/agent/demandes')->assertForbidden();
        $this->actingAs($citoyen)->get('/mes-demandes')
            ->assertOk()
            ->assertSee('Ma propre demande')
            ->assertDontSee('Demande importée visible des agents')
            ->assertDontSee('Marc Delcourt');
        $this->actingAs($citoyen)->get(route('demandes.show', $imported))->assertForbidden();
        $this->actingAs($citoyen)->get(route('contact.confirmation', $imported))->assertForbidden();

        $this->assertSame([$mine->id], $citoyen->demandes()->pluck('id')->all());
        $this->assertTrue(Gate::forUser($citoyen)->denies('view', $imported));
    }

    public function test_a_null_user_id_never_matches_a_user_in_the_policy(): void
    {
        $imported = Demande::factory()->importee()->create();

        $this->assertNull($imported->user_id);
        $this->assertNull($imported->user);
        $this->assertTrue(Gate::forUser($this->agent())->allows('view', $imported));
        $this->assertTrue(Gate::forUser(User::factory()->create())->denies('view', $imported));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->denies('view', $imported));
        $this->assertSame('Citoyenne anonyme', $imported->nom_demandeur);
    }

    public function test_nom_demandeur_falls_back_gracefully(): void
    {
        $withUser = Demande::factory()->for(User::factory()->create(['name' => 'Camille']))->create();
        $noName = Demande::factory()->importee('F99', 'x')->create(['demandeur_nom' => null]);

        $this->assertSame('Camille', $withUser->nom_demandeur);
        $this->assertSame('Non précisé', $noName->nom_demandeur);
    }
}
