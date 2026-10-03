<?php

namespace Tests\Feature;

use App\Models\JournalActivite;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\HorairesServicesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** F74 : horaires d'ouverture et lieu d'un service (association partenaire comprise), état « ouvert / fermé » en texte. */
class HorairesServicesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        $this->admin = User::factory()->admin()->create();
        $this->a('2026-10-05 10:00:00'); // un lundi
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    /** Fige l'heure locale de La Réunion (APP_TIMEZONE = Indian/Reunion, UTC+4 sans heure d'été). */
    private function a(string $heureLocale): void
    {
        Carbon::setTestNow(Carbon::parse($heureLocale, 'Indian/Reunion'));
    }

    /** Lundi au vendredi : 08:30-12:00 et 14:00-17:00 ; samedi : 09:00-12:00 ; dimanche : fermé. */
    private function semaine(): array
    {
        $ouvre = [['08:30', '12:00'], ['14:00', '17:00']];

        return ['lundi' => $ouvre, 'mardi' => $ouvre, 'mercredi' => $ouvre, 'jeudi' => $ouvre, 'vendredi' => $ouvre, 'samedi' => [['09:00', '12:00']], 'dimanche' => []];
    }

    private function service(array $surcharge = []): Service
    {
        return Service::factory()->create(array_merge([
            'nom' => 'Guichet test', 'horaires' => 'Ancien texte : du lundi au vendredi',
            'horaires_semaine' => $this->semaine(), 'adresse' => '12 rue des Tests', 'quartier' => 'Quartier Centre', 'repere' => 'Près de la fontaine',
        ], $surcharge));
    }

    // --- État ouvert / fermé (heure figée, Indian/Reunion) ---

    public function test_the_open_closed_state_is_written_in_text_for_every_situation(): void
    {
        $service = $this->service();

        $cas = [
            ['2026-10-05 10:00:00', true, 'Ouvert maintenant, jusqu\'à 12:00'],                     // lundi, matin
            ['2026-10-05 08:30:00', true, 'Ouvert maintenant, jusqu\'à 12:00'],                     // à l'heure pile de l'ouverture
            ['2026-10-05 07:00:00', false, 'Fermé pour le moment, ouvre aujourd\'hui à 08:30'],     // avant l'ouverture
            ['2026-10-05 12:00:00', false, 'Fermé pour le moment (pause), rouvre aujourd\'hui à 14:00'], // la fermeture est exclue
            ['2026-10-05 13:00:00', false, 'Fermé pour le moment (pause), rouvre aujourd\'hui à 14:00'], // pause entre deux plages
            ['2026-10-05 15:00:00', true, 'Ouvert maintenant, jusqu\'à 17:00'],                     // deuxième plage
            ['2026-10-05 17:00:00', false, 'Fermé pour le moment, rouvre demain à 08:30'],          // après la dernière plage
            ['2026-10-05 22:00:00', false, 'Fermé pour le moment, rouvre demain à 08:30'],
            ['2026-10-09 18:00:00', false, 'Fermé pour le moment, rouvre demain à 09:00'],          // vendredi soir → samedi
            ['2026-10-10 13:00:00', false, 'Fermé pour le moment, rouvre lundi à 08:30'],           // samedi après-midi : dimanche fermé
            ['2026-10-04 12:00:00', false, 'Fermé aujourd\'hui, rouvre demain à 08:30'],            // dimanche : jour fermé
        ];

        foreach ($cas as [$heure, $ouvert, $texte]) {
            $this->a($heure);
            $etat = $service->etatOuverture();
            $this->assertSame($ouvert, $etat['ouvert'], $heure);
            $this->assertSame($texte, $etat['texte'], $heure);
        }
    }

    public function test_a_closed_day_followed_by_a_closed_day_names_the_next_opening_day(): void
    {
        $service = $this->service(['horaires_semaine' => ['lundi' => [], 'mardi' => [], 'mercredi' => [['10:00', '11:00']]] + array_fill_keys(['jeudi', 'vendredi', 'samedi', 'dimanche'], [])]);

        $this->a('2026-10-05 09:00:00'); // lundi, fermé toute la journée
        $this->assertSame('Fermé aujourd\'hui, rouvre mercredi à 10:00', $service->etatOuverture()['texte']);

        $this->a('2026-10-07 11:30:00'); // mercredi après la plage : la semaine suivante
        $this->assertSame('Fermé pour le moment, rouvre mercredi à 10:00', $service->etatOuverture()['texte']);
    }

    public function test_the_state_uses_the_application_timezone_not_the_clock_of_the_instant(): void
    {
        $service = $this->service();

        // 04:00 UTC = 08:00 à La Réunion : pas encore ouvert ; 04:45 UTC = 08:45 : ouvert.
        Carbon::setTestNow(Carbon::parse('2026-10-05 04:00:00', 'UTC'));
        $this->assertSame('Fermé pour le moment, ouvre aujourd\'hui à 08:30', $service->etatOuverture()['texte']);

        Carbon::setTestNow(Carbon::parse('2026-10-05 04:45:00', 'UTC'));
        $this->assertTrue($service->etatOuverture()['ouvert']);

        // 21:00 UTC lundi = 01:00 mardi à La Réunion : le jour est celui de La Réunion.
        Carbon::setTestNow(Carbon::parse('2026-10-05 21:00:00', 'UTC'));
        $this->assertSame('Fermé pour le moment, ouvre aujourd\'hui à 08:30', $service->etatOuverture()['texte']);
        $this->assertSame('Mardi', collect($service->lignesHoraires())->firstWhere('aujourdhui', true)['jour']);
    }

    public function test_without_structured_hours_there_is_no_state_and_a_service_without_any_plage_is_closed(): void
    {
        $this->assertNull(Service::factory()->create(['horaires' => 'Texte seul'])->etatOuverture());

        $vide = Service::factory()->make(['horaires_semaine' => array_fill_keys(\App\Models\Service::JOURS, [])]);
        $this->assertSame('Fermé : aucun horaire d\'ouverture renseigné', $vide->etatOuverture()['texte']);
    }

    public function test_the_state_is_computed_at_every_request_and_never_cached(): void
    {
        $service = $this->service();

        $this->a('2026-10-05 10:00:00');
        $this->get(route('services.show', $service))->assertSee('Ouvert maintenant, jusqu&#039;à 12:00', false);

        $this->a('2026-10-05 18:00:00');
        $reponse = $this->get(route('services.show', $service))->assertSee('Fermé pour le moment, rouvre demain à 08:30')->assertDontSee('Ouvert maintenant');
        $this->assertStringNotContainsString('public', $reponse->headers->get('Cache-Control'));
    }

    // --- Affichage : liste et fiche ---

    public function test_the_list_and_the_sheet_show_the_state_and_the_address(): void
    {
        $service = $this->service();

        $this->get('/services')->assertOk()
            ->assertSee('Ouvert maintenant, jusqu\'à 12:00')
            ->assertSee('12 rue des Tests — Quartier Centre');

        $fiche = $this->get(route('services.show', $service))->assertOk()
            ->assertSee('Ouvert maintenant, jusqu\'à 12:00')
            ->assertSee('12 rue des Tests')->assertSee('Quartier Centre')->assertSee('Près de la fontaine')
            ->getContent();

        $this->assertSame(1, substr_count($fiche, '<h1'));
        $this->assertStringContainsString('<caption', $fiche);
        $this->assertStringContainsString('Horaires d&#039;ouverture : Guichet test', $fiche);
        $this->assertSame(2, substr_count($fiche, 'scope="col"'));
        $this->assertSame(7, substr_count($fiche, 'scope="row"'));
        $this->assertStringContainsString('08:30 – 12:00, 14:00 – 17:00', $fiche);
        $this->assertStringContainsString('Fermé</td>', $fiche);              // le dimanche
        $this->assertMatchesRegularExpression('/Lundi\s*<span[^>]*>\(aujourd&#039;hui\)<\/span>/', $fiche); // jour courant marqué en texte
    }

    public function test_a_service_without_structured_hours_keeps_the_old_text(): void
    {
        $service = Service::factory()->create(['nom' => 'Ancien service', 'horaires' => 'Du lundi au vendredi, 8h - 16h', 'lieu' => 'Hôtel de ville']);

        $this->get('/services')->assertSee('Horaires : Du lundi au vendredi, 8h - 16h')->assertSee('Hôtel de ville')->assertDontSee('Ouvert maintenant')->assertDontSee('Fermé pour le moment');

        $this->get(route('services.show', $service))->assertOk()
            ->assertSee('Du lundi au vendredi, 8h - 16h')
            ->assertDontSee('Horaires d&#039;ouverture : Ancien service', false)
            ->assertDontSee('<caption', false);
    }

    public function test_the_partner_organisation_is_named_in_text_on_the_list_and_the_sheet(): void
    {
        $service = $this->service(['nom' => 'Entraide de quartier', 'organisme' => 'Association partenaire de la ville']);

        $this->get('/services')->assertSee('Association partenaire de la ville');
        $this->get(route('services.show', $service))->assertSee('Association partenaire de la ville');
    }

    // --- Formulaire admin ---

    private function modifier(Service $service, array $horaires = [], array $plus = [])
    {
        return $this->actingAs($this->admin)->put(route('admin.services.update', $service), array_merge(
            ['disponibilite' => 'disponible', 'prioritaire' => '0', 'horaires' => $horaires], $plus));
    }

    public function test_the_admin_form_groups_each_day_in_a_fieldset_with_a_legend_and_explains_the_rules(): void
    {
        $service = $this->service();

        $page = $this->actingAs($this->admin)->get(route('admin.services.edit', $service))->assertOk()
            ->assertSee('Horaires d&#039;ouverture', false)
            ->assertSee('Une plage ne peut pas passer minuit')
            ->assertSee('23:59')
            ->assertSee('Organisme')
            ->getContent();

        $this->assertSame(7, substr_count($page, '<fieldset class="border'));
        foreach (['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'] as $jour) {
            $this->assertMatchesRegularExpression('/<legend[^>]*>\s*'.$jour.'\s*<\/legend>/', $page);
        }
        $this->assertSame(28, substr_count($page, 'type="time"'));                      // 7 jours × 2 plages × (ouverture, fermeture)
        $this->assertStringContainsString('for="horaires-lundi-0-ouverture"', $page);   // chaque champ a son label
        $this->assertStringContainsString('name="horaires[lundi][0][ouverture]" type="time"', $page);
        $this->assertMatchesRegularExpression('/id="horaires-lundi-0-ouverture"[^>]*value="08:30"/', $page); // valeurs actuelles préremplies
    }

    public function test_the_form_refuses_a_closing_before_the_opening_and_other_invalid_plages(): void
    {
        $service = $this->service();

        $invalides = [
            'fermeture avant ouverture' => [['ouverture' => '12:00', 'fermeture' => '09:00']],
            'fermeture égale à l\'ouverture' => [['ouverture' => '09:00', 'fermeture' => '09:00']],
            'plage qui passe minuit' => [['ouverture' => '22:00', 'fermeture' => '02:00']],
            'plage incomplète' => [['ouverture' => '09:00', 'fermeture' => '']],
            'deuxième plage avant la fin de la première' => [['ouverture' => '08:00', 'fermeture' => '12:00'], ['ouverture' => '11:00', 'fermeture' => '17:00']],
        ];

        foreach ($invalides as $nom => $plages) {
            $this->modifier($service, ['lundi' => $plages])->assertSessionHasErrors('horaires.lundi');
            $this->assertSame($this->semaine(), $service->fresh()->horaires_semaine, $nom); // rien n'est enregistré
        }

        $this->modifier($service, ['lundi' => [['ouverture' => '22:00', 'fermeture' => '02:00']]])
            ->assertSessionHasErrors(['horaires.lundi' => 'Le lundi : la fermeture de la plage 1 doit être après son ouverture (une plage ne peut pas passer minuit).']);
        $this->modifier($service, ['lundi' => [['ouverture' => '9h', 'fermeture' => '17h']]])->assertSessionHasErrors('horaires.lundi.0.ouverture');
        $this->modifier($service, ['lundi' => [[], [], ['ouverture' => '09:00', 'fermeture' => '10:00']]])->assertSessionHasErrors('horaires.lundi');
        $this->modifier($service, ['jeudi-saint' => [['ouverture' => '09:00', 'fermeture' => '10:00']]])->assertSessionHasErrors('horaires');
    }

    public function test_a_valid_form_stores_normalised_hours_and_the_organisation(): void
    {
        $service = Service::factory()->create();

        $this->modifier($service, [
            'lundi' => [['ouverture' => '14:00', 'fermeture' => '17:00'], ['ouverture' => '08:30', 'fermeture' => '12:00']], // dans le désordre
            'mardi' => [['ouverture' => '09:00', 'fermeture' => '10:00'], ['ouverture' => '', 'fermeture' => '']],        // deuxième plage vide
        ], ['organisme' => 'Association partenaire'])->assertSessionHasNoErrors()->assertRedirect(route('admin.services.index'));

        $service->refresh();
        $this->assertSame([['08:30', '12:00'], ['14:00', '17:00']], $service->horaires_semaine['lundi']);
        $this->assertSame([['09:00', '10:00']], $service->horaires_semaine['mardi']);
        $this->assertSame([], $service->horaires_semaine['dimanche']);
        $this->assertSame(7, count($service->horaires_semaine));
        $this->assertSame('Association partenaire', $service->organisme);

        // Tout vider : retour au texte d'origine (aucun horaire structuré).
        $this->modifier($service, ['lundi' => [['ouverture' => '', 'fermeture' => '']]])->assertSessionHasNoErrors();
        $this->assertNull($service->fresh()->horaires_semaine);
    }

    public function test_the_journal_only_contains_the_field_names_never_a_value(): void
    {
        $service = Service::factory()->create();

        $this->modifier($service, ['lundi' => [['ouverture' => '08:30', 'fermeture' => '17:00']]], ['organisme' => 'Association Secrète']);

        $entree = JournalActivite::firstOrFail();
        $this->assertStringContainsString('horaires d\'ouverture', $entree->detail);
        $this->assertStringContainsString('organisme', $entree->detail);
        foreach (['08:30', '17:00', 'Association Secrète', $this->admin->email, $this->admin->name] as $interdit) {
            $this->assertStringNotContainsString($interdit, $entree->detail.$entree->objet_libelle, $interdit);
        }
    }

    public function test_the_emergency_cut_does_not_touch_the_hours(): void
    {
        $service = $this->service();

        $this->actingAs($this->admin)->post(route('admin.services.desactiver', $service), ['motif_interruption' => 'Panne']);
        $this->actingAs($this->admin)->post(route('admin.services.reactiver', $service));

        $this->assertSame($this->semaine(), $service->fresh()->horaires_semaine);
    }

    public function test_only_the_admin_edits_hours(): void
    {
        $service = $this->service();

        foreach ([User::factory()->agent()->create(), User::factory()->create()] as $autre) {
            $this->actingAs($autre)->put(route('admin.services.update', $service), ['disponibilite' => 'disponible', 'horaires' => ['lundi' => [['ouverture' => '01:00', 'fermeture' => '02:00']]]])->assertForbidden();
        }
        $this->assertSame($this->semaine(), $service->fresh()->horaires_semaine);
    }

    // --- Seeder séparé ---

    public function test_the_hours_seeder_fills_only_services_without_structured_hours_and_is_idempotent(): void
    {
        $vide = Service::factory()->create(['slug' => 'etat-civil']);
        $urgence = Service::factory()->create(['slug' => 'hopital-nova-terra']);
        $saisi = Service::factory()->create(['slug' => 'urbanisme', 'horaires_semaine' => ['lundi' => [['10:00', '11:00']]] + array_fill_keys(['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'], [])]);
        $autre = Service::factory()->create(['slug' => 'sans-rapport']);

        $this->seed(HorairesServicesSeeder::class);

        $this->assertSame([['08:30', '17:00']], $vide->fresh()->horaires_semaine['lundi']);
        $this->assertSame([], $vide->fresh()->horaires_semaine['samedi']);
        $this->assertSame([['00:00', '23:59']], $urgence->fresh()->horaires_semaine['dimanche']);
        $this->assertSame([['10:00', '11:00']], $saisi->fresh()->horaires_semaine['lundi']);   // saisi par l'admin : intact
        $this->assertNull($autre->fresh()->horaires_semaine);

        $this->a('2026-10-06 10:00:00');
        $avant = $vide->fresh()->toArray();
        $this->seed(HorairesServicesSeeder::class);
        $this->assertSame($avant, $vide->fresh()->toArray());                                   // deuxième passage : aucun changement
    }

    // --- Requêtes ---

    public function test_pages_do_not_lazy_load_and_run_a_constant_number_of_queries(): void
    {
        $service = $this->service();
        $compter = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $urls = ['/services', route('services.show', $service)];
        $avant = array_map($compter, $urls);

        Service::factory()->count(20)->create(['horaires_semaine' => $this->semaine(), 'organisme' => 'Association']);

        $this->assertSame($avant, array_map($compter, $urls));
    }
}
