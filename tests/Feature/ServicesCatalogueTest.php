<?php

namespace Tests\Feature;

use App\Enums\CategorieService;
use App\Enums\Disponibilite;
use App\Models\Demande;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ServicesCatalogueTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** @return array<string, array{0: ?User, 1: string}> */
    private function visiteurs(string $url): array
    {
        return [
            'invité' => [null, $url],
            'citoyen' => [User::factory()->create(), $url],
            'agent' => [User::factory()->agent()->create(), $url],
            'admin' => [User::factory()->admin()->create(), $url],
        ];
    }

    private function visit(?User $user, string $url)
    {
        return ($user ? $this->actingAs($user) : $this)->get($url);
    }

    /** Données d'un formulaire de gestion. */
    private function formulaire(array $surcharge = []): array
    {
        return array_merge([
            'disponibilite' => 'disponible',
            'prioritaire' => '0',
        ], $surcharge);
    }

    // --- Liste publique --------------------------------------------------------------------------------

    public function test_list_is_visible_to_everyone_and_only_shows_active_services(): void
    {
        Service::factory()->create(['nom' => 'Service ouvert', 'resume' => 'Un résumé lisible.']);
        Service::factory()->create(['nom' => 'Service caché', 'actif' => false]);

        foreach ($this->visiteurs('/services') as [$user, $url]) {
            $this->visit($user, $url)->assertOk()
                ->assertSee('Services municipaux')
                ->assertSee('Service ouvert')
                ->assertSee('Un résumé lisible.')
                ->assertDontSee('Service caché')
                ->assertSee('Voir la fiche');
        }
    }

    public function test_priority_services_come_first_whatever_the_catalogue_order(): void
    {
        Service::factory()->create(['nom' => 'Alpha ordinaire', 'ordre' => 1]);
        Service::factory()->create(['nom' => 'Bravo ordinaire', 'ordre' => 2]);
        Service::factory()->prioritaire()->create(['nom' => 'Zulu prioritaire', 'ordre' => 9]);

        $this->get('/services')
            ->assertSeeInOrder(['Zulu prioritaire', 'Alpha ordinaire', 'Bravo ordinaire'])
            ->assertSee('Service prioritaire');
    }

    public function test_priority_services_stay_first_inside_a_category_filter(): void
    {
        Service::factory()->categorie(CategorieService::Sante)->create(['nom' => 'Santé ordinaire', 'ordre' => 1]);
        Service::factory()->categorie(CategorieService::Sante)->prioritaire()->create(['nom' => 'Santé prioritaire', 'ordre' => 5]);

        $this->get('/services?categorie=sante')->assertSeeInOrder(['Santé prioritaire', 'Santé ordinaire']);
    }

    public function test_category_filter_lists_only_that_category(): void
    {
        Service::factory()->categorie(CategorieService::Sante)->create(['nom' => 'Centre santé test']);
        Service::factory()->categorie(CategorieService::Administratif)->create(['nom' => 'Guichet test']);
        Service::factory()->categorie(CategorieService::Transport)->create(['nom' => 'Bus test']);
        Service::factory()->create(['nom' => 'Divers test']);

        $this->get('/services?categorie=sante')->assertOk()
            ->assertSee('Centre santé test')
            ->assertDontSee('Guichet test')->assertDontSee('Bus test')->assertDontSee('Divers test');

        $this->get('/services?categorie=transport')->assertOk()
            ->assertSee('Bus test')->assertDontSee('Centre santé test');
    }

    public function test_unknown_category_values_are_ignored_and_return_the_whole_catalogue(): void
    {
        Service::factory()->categorie(CategorieService::Sante)->create(['nom' => 'Centre santé test']);
        Service::factory()->create(['nom' => 'Divers test']);

        foreach (['?categorie=nimporte', '?categorie=', '?categorie[]=sante', '?categorie=SANTE'] as $query) {
            $this->get('/services'.$query)->assertOk()->assertSee('Centre santé test')->assertSee('Divers test');
        }
    }

    public function test_category_counters_are_exact_and_independent_of_the_filter(): void
    {
        Service::factory()->count(2)->categorie(CategorieService::Sante)->create();
        Service::factory()->categorie(CategorieService::Administratif)->create();
        Service::factory()->count(3)->create();
        Service::factory()->categorie(CategorieService::Sante)->create(['actif' => false]); // ne compte pas

        foreach (['', '?categorie=sante', '?categorie=nimporte'] as $query) {
            $this->get('/services'.$query)
                ->assertSeeInOrder(['Tous', '(6)', 'Santé', '(2)', 'Administratif', '(1)', 'Transport', '(0)', 'Autre', '(3)']);
        }
    }

    public function test_active_category_link_has_aria_current_and_a_non_color_style(): void
    {
        Service::factory()->create();

        $all = $this->get('/services')->getContent();
        $this->assertSame(1, substr_count($all, 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/class="[^"]*filtre-actif[^"]*"\s+aria-current="true"\s*>\s*Tous/', $all);

        $sante = $this->get('/services?categorie=sante')->getContent();
        $this->assertSame(1, substr_count($sante, 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/class="[^"]*filtre-actif[^"]*"\s+aria-current="true"\s*>\s*Santé/', $sante);
    }

    public function test_empty_category_shows_a_message_and_a_link_to_everything(): void
    {
        Service::factory()->create();

        $this->get('/services?categorie=sante')->assertOk()
            ->assertSee('Aucun service dans cette catégorie.')
            ->assertSee('Voir tous les services');
    }

    public function test_list_is_a_single_h1_page_with_a_breadcrumb(): void
    {
        $response = $this->get('/services')->assertOk()
            ->assertSee('aria-label="Fil d&#039;Ariane"', false)
            ->assertSee('<title>Services municipaux – ', false);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    // --- Disponibilité visible avant une démarche ------------------------------------------------------

    public function test_list_shows_availability_in_text_for_each_service(): void
    {
        Service::factory()->create(['nom' => 'Service ouvert']);
        Service::factory()->interrompu('Panne du serveur.', 'Venez au guichet.')->create(['nom' => 'Service coupé']);

        $this->get('/services')->assertOk()
            ->assertSee('Disponible')
            ->assertSee('Service interrompu')
            ->assertSee('Retour estimé :')
            ->assertSee('04/10/2026 10:00')   // demain, 10:00 (relatif à l'heure figée)
            ->assertDontSee('Panne du serveur.'); // le motif est sur la fiche
    }

    public function test_page_of_an_interrupted_service_shows_reason_return_and_alternative_to_guests(): void
    {
        $service = Service::factory()->interrompu('Incident technique sur le serveur.', 'Utilisez le guichet du centre-ville.')
            ->create(['nom' => 'Service coupé', 'slug' => 'service-coupe']);

        foreach ($this->visiteurs('/services/service-coupe') as [$user, $url]) {
            $this->visit($user, $url)->assertOk()
                ->assertSee('Service interrompu')
                ->assertSee('Motif :')
                ->assertSee('Incident technique sur le serveur.')
                ->assertSee('Retour estimé :')
                ->assertSee('04/10/2026 10:00')
                ->assertSee('À faire en attendant :')
                ->assertSee('Utilisez le guichet du centre-ville.')
                ->assertSee('role="status"', false);
        }
    }

    public function test_interrupted_service_without_estimated_return_says_so(): void
    {
        Service::factory()->interrompu()->create(['slug' => 'sans-retour', 'retour_estime_at' => null]);

        $this->get('/services/sans-retour')->assertOk()->assertSee('non communiqué');
    }

    public function test_page_of_an_available_service_shows_no_interruption_details(): void
    {
        Service::factory()->create([
            'slug' => 'ouvert', 'nom' => 'Service ouvert', 'description' => 'Une description complète.',
            'horaires' => 'Du lundi au vendredi', 'lieu' => 'Hôtel de ville', 'contact' => 'contact@novaterra.test',
        ]);

        $this->get('/services/ouvert')->assertOk()
            ->assertSee('Disponible')
            ->assertSee('Une description complète.')
            ->assertSee('Du lundi au vendredi')->assertSee('Hôtel de ville')->assertSee('contact@novaterra.test')
            ->assertDontSee('Motif :')->assertDontSee('Retour estimé :')->assertDontSee('À faire en attendant');
    }

    public function test_service_page_has_breadcrumb_single_h1_and_404_for_unknown_or_inactive(): void
    {
        $service = Service::factory()->create(['slug' => 'ouvert', 'nom' => 'Service ouvert']);
        Service::factory()->create(['slug' => 'ferme', 'actif' => false]);

        $response = $this->get('/services/ouvert')->assertOk()
            ->assertSee('aria-label="Fil d&#039;Ariane"', false)
            ->assertSee('<title>Service ouvert – ', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));

        $this->get('/services/ferme')->assertNotFound();
        $this->get('/services/inconnu')->assertNotFound();
        $this->get('/services/'.$service->id)->assertNotFound(); // l'URL utilise le slug, pas l'id
    }

    public function test_service_page_offers_a_request_link_to_citizens_and_a_login_link_to_guests(): void
    {
        $service = Service::factory()->create(['slug' => 'ouvert']);

        $this->get('/services/ouvert')->assertSee('Se connecter pour faire une demande')->assertDontSee('Faire une demande à ce service');
        $this->actingAs(User::factory()->create())->get('/services/ouvert')
            ->assertSee('Faire une demande à ce service')
            ->assertSee('service_id='.$service->id, false);
        $this->actingAs(User::factory()->agent()->create())->get('/services/ouvert')
            ->assertDontSee('Faire une demande à ce service')->assertDontSee('Se connecter pour faire une demande');
    }

    public function test_contact_form_mentions_interrupted_services_and_stays_usable(): void
    {
        $coupe = Service::factory()->interrompu('Maintenance du réseau.', 'Utilisez les lignes 2 et 8.')->create(['nom' => 'Transports test']);
        $ouvert = Service::factory()->create(['nom' => 'Culture test']);
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->get('/contact')->assertOk()
            ->assertSee('Services actuellement interrompus')
            ->assertSee('Transports test — service interrompu')   // dans la liste déroulante
            ->assertSee('Maintenance du réseau.')
            ->assertSee('Retour estimé :')
            ->assertSee('04/10/2026 10:00')
            ->assertSee('Utilisez les lignes 2 et 8.')
            ->assertSee('Vous pouvez quand même envoyer votre demande')
            ->assertSee('aria-describedby="services_interrompus"', false)
            ->assertDontSee('Culture test — service interrompu');

        // Le formulaire reste utilisable : une demande vers le service interrompu est bien enregistrée.
        $this->actingAs($citoyen)->post('/contact', [
            'service_id' => $coupe->id,
            'objet' => 'Question sur un abonnement',
            'message' => 'Je voudrais renouveler mon abonnement de bus.',
        ])->assertRedirect();

        $this->assertSame($coupe->id, Demande::firstOrFail()->service_id);
    }

    public function test_contact_form_has_no_interruption_block_when_everything_is_available(): void
    {
        Service::factory()->create();

        $this->actingAs(User::factory()->create())->get('/contact')->assertOk()
            ->assertDontSee('Services actuellement interrompus')
            ->assertDontSee('services_interrompus');
    }

    public function test_contact_form_preselects_the_service_from_the_query_string(): void
    {
        $service = Service::factory()->create(['nom' => 'Service cible']);

        $this->actingAs(User::factory()->create())->get('/contact?service_id='.$service->id)
            ->assertSee('value="'.$service->id.'" selected', false);
    }

    // --- Gestion par l'admin ---------------------------------------------------------------------------

    public function test_admin_manages_services_but_agent_and_citizen_get_403_and_guests_are_redirected(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin())->get('/admin/services')->assertOk()->assertSee($service->nom);
        $this->actingAs($this->admin())->get(route('admin.services.edit', $service))->assertOk();

        foreach ([User::factory()->agent()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get('/admin/services')->assertForbidden();
            $this->actingAs($user)->get(route('admin.services.edit', $service))->assertForbidden();
            $this->actingAs($user)->put(route('admin.services.update', $service), $this->formulaire([
                'disponibilite' => 'interrompu', 'motif_interruption' => 'Tentative.',
            ]))->assertForbidden();
        }
        $this->assertSame(Disponibilite::Disponible, $service->fresh()->disponibilite);

        auth()->logout();
        $this->get('/admin/services')->assertRedirect('/login');
        $this->get(route('admin.services.edit', $service))->assertRedirect('/login');
        $this->put(route('admin.services.update', $service), $this->formulaire())->assertRedirect('/login');
    }

    public function test_service_policy_is_reserved_to_the_admin(): void
    {
        $service = Service::factory()->create();

        $this->assertTrue(Gate::forUser($this->admin())->allows('update', $service));
        $this->assertTrue(Gate::forUser($this->admin())->allows('viewAny', Service::class));
        foreach ([User::factory()->agent()->create(), User::factory()->create()] as $user) {
            $this->assertTrue(Gate::forUser($user)->denies('update', $service));
            $this->assertTrue(Gate::forUser($user)->denies('viewAny', Service::class));
        }
    }

    public function test_admin_interrupts_a_service_and_the_public_sees_it_immediately(): void
    {
        $service = Service::factory()->create(['slug' => 'ouvert', 'nom' => 'Service ouvert']);

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaire([
            'disponibilite' => 'interrompu',
            'motif_interruption' => 'Travaux dans le bâtiment.',
            'retour_estime_at' => '2026-10-05T14:30',
            'alternative' => 'Rendez-vous au guichet nord.',
            'prioritaire' => '1',
        ]))->assertRedirect(route('admin.services.index'))->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertTrue($service->estInterrompu());
        $this->assertTrue($service->prioritaire);
        $this->assertSame('2026-10-05 14:30:00', $service->retour_estime_at->format('Y-m-d H:i:s'));

        $this->get('/services/ouvert')
            ->assertSee('Travaux dans le bâtiment.')
            ->assertSee('05/10/2026 14:30')
            ->assertSee('Rendez-vous au guichet nord.');
    }

    public function test_putting_a_service_back_in_service_clears_the_interruption_details(): void
    {
        $service = Service::factory()->interrompu()->prioritaire()->create(['slug' => 'revenu']);

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaire([
            'disponibilite' => 'disponible',
            'motif_interruption' => 'Ce motif doit disparaître.',
            'retour_estime_at' => '2026-10-05T14:30',
            'alternative' => 'Cette alternative aussi.',
            'prioritaire' => '0',
        ]))->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame(Disponibilite::Disponible, $service->disponibilite);
        $this->assertNull($service->motif_interruption);
        $this->assertNull($service->retour_estime_at);
        $this->assertNull($service->alternative);
        $this->assertFalse($service->prioritaire);

        $this->get('/services/revenu')->assertSee('Disponible')->assertDontSee('Ce motif doit disparaître.');
    }

    public function test_toggling_priority_reorders_the_catalogue(): void
    {
        $a = Service::factory()->create(['nom' => 'Alpha', 'ordre' => 1]);
        Service::factory()->create(['nom' => 'Bravo', 'ordre' => 2]);

        $this->get('/services')->assertSeeInOrder(['Alpha', 'Bravo']);

        $bravo = Service::where('nom', 'Bravo')->firstOrFail();
        $this->actingAs($this->admin())->put(route('admin.services.update', $bravo), $this->formulaire(['prioritaire' => '1']))
            ->assertSessionHasNoErrors();
        auth()->logout();

        $this->get('/services')->assertSeeInOrder(['Bravo', 'Alpha']);
        $this->assertNotNull($a->fresh());
    }

    public function test_a_reason_is_required_when_the_service_is_interrupted(): void
    {
        $service = Service::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaire(['disponibilite' => 'interrompu']))
            ->assertSessionHasErrors('motif_interruption');
        $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaire(['disponibilite' => 'interrompu', 'motif_interruption' => '']))
            ->assertSessionHasErrors('motif_interruption');
        $this->assertSame(Disponibilite::Disponible, $service->fresh()->disponibilite);

        // Pas de motif exigé pour un service disponible.
        $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaire())->assertSessionHasNoErrors();
    }

    public function test_the_estimated_return_must_be_in_the_future_down_to_the_hour(): void
    {
        $service = Service::factory()->create();
        $admin = $this->admin();
        $interrompu = fn (string $retour) => $this->formulaire(['disponibilite' => 'interrompu', 'motif_interruption' => 'Maintenance.', 'retour_estime_at' => $retour]);

        $this->actingAs($admin)->put(route('admin.services.update', $service), $interrompu('2026-10-02T10:00'))->assertSessionHasErrors('retour_estime_at'); // hier
        $this->actingAs($admin)->put(route('admin.services.update', $service), $interrompu('2026-10-03T09:00'))->assertSessionHasErrors('retour_estime_at'); // ce matin (passé)
        $this->actingAs($admin)->put(route('admin.services.update', $service), $interrompu('2026-10-03T10:00'))->assertSessionHasErrors('retour_estime_at'); // pile maintenant
        $this->assertSame(Disponibilite::Disponible, $service->fresh()->disponibilite);

        $this->actingAs($admin)->put(route('admin.services.update', $service), $interrompu('2026-10-03T10:01'))->assertSessionHasNoErrors();
        $this->assertTrue($service->fresh()->estInterrompu());
    }

    public function test_the_estimated_return_is_optional(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaire([
            'disponibilite' => 'interrompu', 'motif_interruption' => 'Incident.', 'retour_estime_at' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($service->fresh()->retour_estime_at);
    }

    public function test_availability_and_text_lengths_are_validated(): void
    {
        $service = Service::factory()->create();
        $admin = $this->admin();

        foreach ([
            ['disponibilite' => 'ferme'],
            ['disponibilite' => ''],
            ['disponibilite' => 'interrompu', 'motif_interruption' => str_repeat('a', 501)],
            ['disponibilite' => 'interrompu', 'motif_interruption' => 'Ok.', 'alternative' => str_repeat('a', 501)],
            ['disponibilite' => 'interrompu', 'motif_interruption' => 'Ok.', 'retour_estime_at' => 'pas une date'],
        ] as $surcharge) {
            $champ = array_key_exists('alternative', $surcharge) ? 'alternative' : (array_key_exists('retour_estime_at', $surcharge) ? 'retour_estime_at' : (array_key_first($surcharge) === 'disponibilite' && count($surcharge) === 1 ? 'disponibilite' : 'motif_interruption'));
            $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaire($surcharge))->assertSessionHasErrors($champ);
        }

        $this->assertSame(Disponibilite::Disponible, $service->fresh()->disponibilite);
    }

    public function test_validation_errors_are_in_french_and_input_is_kept(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin())->from(route('admin.services.edit', $service))->followingRedirects()
            ->put(route('admin.services.update', $service), $this->formulaire([
                'disponibilite' => 'interrompu', 'alternative' => 'Alternative conservée.', 'retour_estime_at' => '2026-10-01T10:00',
            ]))
            ->assertSee('Indiquez le motif de l&#039;interruption.', false)
            ->assertSee('Le retour estimé doit être dans le futur.')
            ->assertSee('Alternative conservée.');
    }

    public function test_extra_fields_cannot_change_the_catalogue_identity(): void
    {
        $service = Service::factory()->create(['nom' => 'Nom d\'origine', 'slug' => 'slug-origine', 'ordre' => 4, 'actif' => true, 'categorie' => CategorieService::Autre]);

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaire([
            'nom' => 'Piraté', 'slug' => 'pirate', 'ordre' => 99, 'actif' => '0', 'categorie' => 'sante', 'description' => 'Piraté', 'id' => 999,
        ]))->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame('Nom d\'origine', $service->nom);
        $this->assertSame('slug-origine', $service->slug);
        $this->assertSame(4, $service->ordre);
        $this->assertTrue($service->actif);
        $this->assertSame(CategorieService::Autre, $service->categorie);
        $this->assertNotSame('Piraté', $service->description);
    }

    public function test_admin_list_and_edit_pages_show_state_in_text_and_local_dates(): void
    {
        $service = Service::factory()->interrompu('Maintenance.', 'Guichet nord.')->prioritaire()->create(['nom' => 'Service coupé']);

        $this->actingAs($this->admin())->get('/admin/services')
            ->assertSee('Service coupé')->assertSee('Service interrompu')->assertSee('Oui')->assertSee('04/10/2026 10:00');

        $this->actingAs($this->admin())->get(route('admin.services.edit', $service))
            ->assertSee('value="2026-10-04T10:00"', false)
            ->assertSee('Maintenance.')->assertSee('Guichet nord.')
            ->assertSee('for="disponibilite"', false)->assertSee('for="motif_interruption"', false);
    }

    // --- Requêtes --------------------------------------------------------------------------------------

    public function test_the_list_does_not_run_n_plus_one_queries(): void
    {
        Model::preventLazyLoading();
        $compter = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/services')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        Service::factory()->count(3)->create();
        $peu = $compter();

        Service::factory()->count(12)->categorie(CategorieService::Sante)->interrompu()->create();
        Service::factory()->count(5)->prioritaire()->create();

        $this->assertSame($peu, $compter(), 'Le nombre de requêtes ne doit pas dépendre du nombre de services.');
        $this->assertLessThanOrEqual(2, $peu); // services + bandeau d'alertes
    }

    // --- Navigation ------------------------------------------------------------------------------------

    public function test_services_link_is_in_the_public_navigation_and_the_citizen_navigation(): void
    {
        $url = route('services.index');

        $this->get('/')->assertSee($url);                         // accueil
        $this->get('/alertes')->assertSee($url);                   // en-tête des visiteurs
        $this->actingAs(User::factory()->create())->get('/espace')->assertSee($url);
        $this->actingAs(User::factory()->agent()->create())->get('/agent')->assertDontSee($url);
    }

    public function test_admin_navigation_links_to_the_catalogue_management_only(): void
    {
        $this->actingAs($this->admin())->get('/admin')
            ->assertSee(route('admin.services.index'))
            ->assertDontSee(route('services.index'));
        $this->actingAs(User::factory()->agent()->create())->get('/agent')->assertDontSee(route('admin.services.index'));
        $this->actingAs(User::factory()->create())->get('/espace')->assertDontSee(route('admin.services.index'));
    }

    // --- Seeder ----------------------------------------------------------------------------------------

    public function test_seeder_provides_health_services_a_priority_service_and_an_interrupted_one_with_relative_dates(): void
    {
        $this->seed(ServiceSeeder::class);

        $this->assertSame(8, Service::count());
        $this->assertGreaterThanOrEqual(2, Service::where('categorie', 'sante')->count());
        $this->assertGreaterThanOrEqual(1, Service::where('prioritaire', true)->count());
        $this->assertTrue(Service::where('categorie', 'sante')->where('prioritaire', true)->exists());

        $coupe = Service::where('disponibilite', 'interrompu')->firstOrFail();
        $this->assertNotEmpty($coupe->motif_interruption);
        $this->assertNotEmpty($coupe->alternative);
        $this->assertTrue($coupe->retour_estime_at->isFuture());
        $this->assertTrue($coupe->retour_estime_at->equalTo(now()->addDay()));   // relatif à maintenant
        $this->assertSame(1, Service::where('disponibilite', 'interrompu')->count());
    }

    public function test_seeder_is_rerunnable_and_keeps_the_estimated_return_in_the_future(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(ServiceSeeder::class);
        $this->assertSame(8, Service::count());

        Carbon::setTestNow(now()->addMonth());
        $this->assertTrue(Service::where('disponibilite', 'interrompu')->firstOrFail()->retour_estime_at->isPast());

        $this->seed(ServiceSeeder::class);

        $this->assertSame(8, Service::count());
        $this->assertTrue(Service::where('disponibilite', 'interrompu')->firstOrFail()->retour_estime_at->isFuture());
    }

    public function test_seeded_catalogue_is_usable_end_to_end(): void
    {
        $this->seed(ServiceSeeder::class);

        $this->get('/services')->assertOk()
            ->assertSeeInOrder(['État civil', 'Centre de santé municipal', 'Urbanisme'])   // les 2 prioritaires avant les autres
            ->assertSee('Service interrompu');
        $this->get('/services?categorie=sante')->assertSee('Centre de santé municipal')->assertSee('Prévention et vaccination')->assertDontSee('Urbanisme');
        $this->get('/services/transports')->assertSee('Maintenance du réseau de bus')->assertSee('Utilisez les lignes 2 et 8');
    }
}
