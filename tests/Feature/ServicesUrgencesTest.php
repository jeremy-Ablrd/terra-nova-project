<?php

namespace Tests\Feature;

use App\Enums\CategorieService;
use App\Enums\Disponibilite;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UrgenceSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServicesUrgencesTest extends TestCase
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

    private function formulaire(array $surcharge = []): array
    {
        return array_merge(['disponibilite' => 'disponible', 'prioritaire' => '0', 'urgence' => '0'], $surcharge);
    }

    private function hopital(array $surcharge = []): Service
    {
        return Service::factory()->categorie(CategorieService::Sante)->localise(
            '45 boulevard de l\'Hôpital', 'Quartier sud', 'Face au parc des Flamboyants', '0262 55 01 15'
        )->create(array_merge(['nom' => 'Hôpital test', 'horaires' => 'Accueil 24h/24'], $surcharge));
    }

    // --- Page /urgences --------------------------------------------------------------------------------

    public function test_urgences_page_is_visible_to_everyone_with_address_district_landmark_phone_and_availability(): void
    {
        $this->hopital();
        Service::factory()->urgence()->categorie(CategorieService::Sante)->localise('8 avenue des Alizés', 'Quartier est', 'Près du phare', '15')
            ->create(['nom' => 'Urgences test']);

        foreach ($this->visiteurs('/urgences') as [$user, $url]) {
            $this->visit($user, $url)->assertOk()
                ->assertSee('Urgences et hôpitaux')
                ->assertSee('Hôpital test')
                ->assertSee('45 boulevard de l&#039;Hôpital', false)
                ->assertSee('Quartier sud')
                ->assertSee('Face au parc des Flamboyants')
                ->assertSee('0262 55 01 15')
                ->assertSee('Accueil 24h/24')
                ->assertSee('Disponible')
                ->assertSee('Urgences test')
                ->assertSee('8 avenue des Alizés')
                ->assertSee('Quartier est')
                ->assertSee('Près du phare')
                ->assertSee('Service d&#039;urgence', false);
        }
    }

    public function test_an_interrupted_emergency_service_shows_reason_return_and_alternative_on_the_page_itself(): void
    {
        $this->hopital()->forceFill([
            'disponibilite' => Disponibilite::Interrompu,
            'motif_interruption' => 'Panne électrique.',
            'retour_estime_at' => now()->addHours(5),
            'alternative' => 'Rendez-vous aux urgences de l\'est.',
        ])->save();

        $this->get('/urgences')->assertOk()
            ->assertSee('Service interrompu')
            ->assertSee('Panne électrique.')
            ->assertSee('03/10/2026 15:00')
            ->assertSee('Rendez-vous aux urgences de l&#039;est.', false);
    }

    public function test_only_emergency_or_health_services_are_listed(): void
    {
        $this->hopital(['nom' => 'Santé test']);
        Service::factory()->urgence()->create(['nom' => 'Urgence hors santé']);        // urgence = vrai, catégorie autre
        Service::factory()->categorie(CategorieService::Administratif)->create(['nom' => 'Guichet administratif']);
        Service::factory()->categorie(CategorieService::Transport)->localise()->create(['nom' => 'Bus test']);
        Service::factory()->create(['nom' => 'Divers test']);
        Service::factory()->categorie(CategorieService::Sante)->create(['nom' => 'Santé cachée', 'actif' => false]);

        $this->get('/urgences')->assertOk()
            ->assertSee('Santé test')
            ->assertSee('Urgence hors santé')
            ->assertDontSee('Guichet administratif')
            ->assertDontSee('Bus test')
            ->assertDontSee('Divers test')
            ->assertDontSee('Santé cachée');
    }

    public function test_a_service_that_is_neither_urgent_nor_health_never_appears(): void
    {
        Service::factory()->localise()->create(['nom' => 'Service ordinaire localisé', 'urgence' => false]);

        $this->get('/urgences')->assertOk()
            ->assertDontSee('Service ordinaire localisé')
            ->assertSee('Aucun service d&#039;urgence ou de santé n&#039;est renseigné pour le moment.', false);
    }

    public function test_emergency_services_come_before_other_health_services(): void
    {
        $this->hopital(['nom' => 'Hôpital ordre un', 'ordre' => 1]);
        Service::factory()->urgence()->categorie(CategorieService::Sante)->create(['nom' => 'Urgences ordre neuf', 'ordre' => 9]);

        $this->get('/urgences')->assertSeeInOrder(['Urgences ordre neuf', 'Hôpital ordre un']);
    }

    public function test_the_legacy_place_is_shown_when_no_address_is_filled_in(): void
    {
        Service::factory()->categorie(CategorieService::Sante)->create(['nom' => 'Centre ancien', 'lieu' => 'Centre de santé, 12 avenue de la Santé']);

        $this->get('/urgences')->assertSee('Centre de santé, 12 avenue de la Santé')->assertSee('Non précisé');
    }

    public function test_urgences_page_has_a_breadcrumb_a_single_h1_and_a_title(): void
    {
        $this->hopital();

        $response = $this->get('/urgences')->assertOk()
            ->assertSee('aria-label="Fil d&#039;Ariane"', false)
            ->assertSee('<title>Urgences et hôpitaux – ', false);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_urgences_page_does_not_run_n_plus_one_queries(): void
    {
        Model::preventLazyLoading();
        $compter = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/urgences')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        Service::factory()->count(2)->urgence()->create();
        $peu = $compter();

        Service::factory()->count(15)->urgence()->categorie(CategorieService::Sante)->interrompu()->create();
        Service::factory()->count(5)->categorie(CategorieService::Sante)->create();

        $this->assertSame($peu, $compter(), 'Le nombre de requêtes ne doit pas dépendre du nombre de services.');
        $this->assertLessThanOrEqual(2, $peu); // services + bandeau d'alertes
    }

    // --- Téléphone -------------------------------------------------------------------------------------

    public function test_phone_is_a_tel_link_with_an_accessible_name(): void
    {
        $this->hopital(['telephone' => '0262 55 01 15']);
        Service::factory()->urgence()->create(['nom' => 'Urgences test', 'telephone' => '+262 692 12 34 56']);
        Service::factory()->urgence()->create(['nom' => 'Urgences courtes', 'telephone' => '15']);

        $content = $this->get('/urgences')->getContent();

        $this->assertStringContainsString('href="tel:0262550115"', $content);
        $this->assertStringContainsString('href="tel:+262692123456"', $content);
        $this->assertStringContainsString('href="tel:15"', $content);
        // Nom accessible : « Appeler <service> au <numéro lisible> », qui contient le numéro affiché.
        $this->assertStringContainsString('aria-label="Appeler Hôpital test au 0262 55 01 15"', $content);
        $this->assertStringContainsString('>0262 55 01 15</a>', $content);
        $this->assertStringContainsString('aria-label="Appeler Urgences courtes au 15"', $content);
    }

    public function test_service_without_phone_has_no_tel_link(): void
    {
        Service::factory()->urgence()->create(['telephone' => null]);

        $content = $this->get('/urgences')->getContent();
        $this->assertStringNotContainsString('href="tel:', $content);
        $this->assertStringContainsString('Téléphone', $content);
    }

    // --- Liste et fiche des services -------------------------------------------------------------------

    public function test_list_and_page_of_a_service_show_address_and_district_when_they_exist(): void
    {
        $service = Service::factory()->localise('3 rue des Tests', 'Quartier ouest', 'Près de la poste', '0262 11 22 33')
            ->create(['nom' => 'Service localisé', 'slug' => 'localise']);
        Service::factory()->create(['nom' => 'Service sans lieu', 'slug' => 'sans-lieu']);

        $this->get('/services')->assertOk()->assertSee('3 rue des Tests — Quartier ouest');

        $this->get('/services/localise')->assertOk()
            ->assertSee('3 rue des Tests')->assertSee('Quartier ouest')->assertSee('Près de la poste')
            ->assertSee('href="tel:0262112233"', false);

        $vide = $this->get('/services/sans-lieu')->assertOk();
        $vide->assertDontSee('Quartier ouest')->assertDontSee('tel:', false);
        $this->assertSame(0, substr_count($vide->getContent(), '<dt class="text-gray-500">Adresse'));
        $this->assertNotNull($service);
    }

    // --- Navigation ------------------------------------------------------------------------------------

    public function test_urgences_link_is_in_the_visitor_header_the_home_page_and_the_citizen_navigation(): void
    {
        $url = route('urgences.index');

        $this->get('/')->assertSee($url);
        $this->get('/alertes')->assertSee($url);
        $this->actingAs(User::factory()->create())->get('/espace')->assertSee($url);
        $this->actingAs(User::factory()->agent()->create())->get('/agent')->assertDontSee($url);
        $this->actingAs($this->admin())->get('/admin')->assertDontSee($url);
    }

    // --- Gestion par l'admin ---------------------------------------------------------------------------

    public function test_admin_edits_location_fields_and_the_emergency_flag(): void
    {
        $service = Service::factory()->categorie(CategorieService::Autre)->create(['nom' => 'Service modifié', 'slug' => 'modifie']);

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaire([
            'adresse' => '1 place de la Mairie',
            'quartier' => 'Centre-ville',
            'repere' => 'Derrière la fontaine',
            'telephone' => '+262 262 00 11 22',
            'urgence' => '1',
        ]))->assertRedirect(route('admin.services.index'))->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame('1 place de la Mairie', $service->adresse);
        $this->assertSame('Centre-ville', $service->quartier);
        $this->assertSame('Derrière la fontaine', $service->repere);
        $this->assertSame('+262 262 00 11 22', $service->telephone);
        $this->assertTrue($service->urgence);

        $this->get('/urgences')->assertSee('id="urgence-'.$service->id.'"', false)->assertSee('Centre-ville')->assertSee('tel:+262262001122', false);

        // Décocher la case retire le service de la page (il n'est ni urgent ni de santé).
        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaire(['urgence' => '0']))->assertSessionHasNoErrors();
        $this->assertFalse($service->fresh()->urgence);
        // (le message de confirmation de la session cite le nom : on vérifie la carte du service, pas le texte seul)
        $this->get('/urgences')->assertDontSee('id="urgence-'.$service->id.'"', false);
    }

    public function test_clearing_location_fields_resets_them_to_null(): void
    {
        $service = Service::factory()->localise()->create();

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), $this->formulaire([
            'adresse' => '', 'quartier' => '', 'repere' => '', 'telephone' => '',
        ]))->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertNull($service->adresse);
        $this->assertNull($service->quartier);
        $this->assertNull($service->repere);
        $this->assertNull($service->telephone);
    }

    public function test_location_fields_have_limited_lengths(): void
    {
        $service = Service::factory()->create();
        $admin = $this->admin();

        foreach ([
            ['adresse', str_repeat('a', 256)],
            ['quartier', str_repeat('a', 101)],
            ['repere', str_repeat('a', 151)],
            ['telephone', str_repeat('1', 31)],
        ] as [$champ, $valeur]) {
            $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaire([$champ => $valeur]))->assertSessionHasErrors($champ);
        }

        // Limites acceptées.
        $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaire([
            'adresse' => str_repeat('a', 255), 'quartier' => str_repeat('b', 100), 'repere' => str_repeat('c', 150), 'telephone' => str_repeat('1', 30),
        ]))->assertSessionHasNoErrors();
    }

    public function test_the_phone_format_is_validated(): void
    {
        $service = Service::factory()->create();
        $admin = $this->admin();

        foreach (['abc', 'appelez-moi', '0262-ab-12', '1', '(+262) 12', 'tel:0262', '0262<script>', '++262 12'] as $mauvais) {
            $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaire(['telephone' => $mauvais]))
                ->assertSessionHasErrors('telephone');
        }
        foreach (['0262 55 01 15', '0262.55.01.15', '02-62-55-01-15', '+262 692 12 34 56', '15', '112', '(0262) 55 01 15'] as $bon) {
            $this->actingAs($admin)->put(route('admin.services.update', $service), $this->formulaire(['telephone' => $bon]))
                ->assertSessionHasNoErrors();
        }
    }

    public function test_location_validation_errors_are_in_french_and_input_is_kept(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->admin())->from(route('admin.services.edit', $service))->followingRedirects()
            ->put(route('admin.services.update', $service), $this->formulaire([
                'telephone' => 'pas un numéro', 'quartier' => str_repeat('q', 101), 'adresse' => 'Adresse conservée',
            ]))
            ->assertSee('Le téléphone ne peut contenir que des chiffres')
            ->assertSee('Le champ quartier ne doit pas dépasser 100 caractères.')
            ->assertSee('Adresse conservée');
    }

    public function test_edit_form_shows_the_new_fields_with_labels_and_current_values(): void
    {
        $service = Service::factory()->urgence()->create();

        $this->actingAs($this->admin())->get(route('admin.services.edit', $service))->assertOk()
            ->assertSee('for="adresse"', false)->assertSee('for="quartier"', false)->assertSee('for="repere"', false)
            ->assertSee('for="telephone"', false)->assertSee('for="urgence"', false)
            ->assertSee('value="12 rue des Tests"', false)
            ->assertSee('value="Quartier test"', false)
            ->assertSee('value="0262 12 34 56"', false)
            ->assertSee('maxlength="255"', false)->assertSee('maxlength="100"', false)->assertSee('maxlength="150"', false)->assertSee('maxlength="30"', false);
    }

    public function test_agent_and_citizen_get_403_on_editing_location_fields(): void
    {
        $service = Service::factory()->create(['adresse' => 'Adresse d\'origine', 'urgence' => false]);

        foreach ([User::factory()->agent()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.services.edit', $service))->assertForbidden();
            $this->actingAs($user)->put(route('admin.services.update', $service), $this->formulaire([
                'adresse' => 'Adresse piratée', 'telephone' => '0262 00 00 00', 'urgence' => '1',
            ]))->assertForbidden();
        }

        $service->refresh();
        $this->assertSame('Adresse d\'origine', $service->adresse);
        $this->assertNull($service->telephone);
        $this->assertFalse($service->urgence);

        auth()->logout();
        $this->put(route('admin.services.update', $service), $this->formulaire(['adresse' => 'Invité']))->assertRedirect('/login');
    }

    // --- Seeder ----------------------------------------------------------------------------------------

    public function test_seeder_creates_one_hospital_south_and_one_emergency_service_east_with_all_details(): void
    {
        $this->seed(UrgenceSeeder::class);

        $this->assertSame(2, Service::count());

        $hopital = Service::where('slug', 'hopital-nova-terra')->firstOrFail();
        $this->assertSame(CategorieService::Sante, $hopital->categorie);
        $this->assertFalse($hopital->urgence);
        $this->assertSame('Quartier sud', $hopital->quartier);

        $urgences = Service::where('slug', 'urgences-nova-terra')->firstOrFail();
        $this->assertTrue($urgences->urgence);
        $this->assertSame('Quartier est', $urgences->quartier);

        foreach ([$hopital, $urgences] as $service) {
            $this->assertNotEmpty($service->adresse);
            $this->assertNotEmpty($service->repere);
            $this->assertNotEmpty($service->telephone);
            $this->assertNotNull($service->telephoneHref());
            $this->assertSame(Disponibilite::Disponible, $service->disponibilite);
        }

        $this->get('/urgences')->assertOk()
            ->assertSee('Hôpital de Terra Nova')->assertSee('Urgences de Terra Nova')
            ->assertSee('Quartier sud')->assertSee('Quartier est')
            ->assertSee('href="tel:15"', false);
    }

    public function test_seeder_is_rerunnable_and_never_changes_the_state_of_other_services(): void
    {
        $autre = Service::factory()->interrompu('Motif d\'origine.', 'Alternative d\'origine.')->prioritaire()
            ->create(['nom' => 'Autre service', 'slug' => 'autre', 'ordre' => 3]);
        $avant = $autre->fresh()->getAttributes();

        $this->seed(UrgenceSeeder::class);
        $this->seed(UrgenceSeeder::class);

        $this->assertSame(3, Service::count());   // 1 autre + 2 services d'urgence, sans doublon
        $this->assertSame($avant, $autre->fresh()->getAttributes());
    }

    public function test_full_seeding_adds_the_emergency_services_to_the_catalogue(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(10, Service::count());
        $this->assertSame(4, Service::where('categorie', 'sante')->count());
        $this->get('/urgences')->assertSee('Hôpital de Terra Nova')->assertSee('Centre de santé municipal')->assertDontSee('Urbanisme');
    }
}
