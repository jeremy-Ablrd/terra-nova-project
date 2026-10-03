<?php

namespace Tests\Feature;

use App\Enums\Niveau;
use App\Models\Alerte;
use App\Models\User;
use Database\Seeders\AlerteSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlertesTest extends TestCase
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

    /** Pages d'un visiteur non connecté, d'un citoyen, d'un agent et d'un admin, toutes dans le layout commun. */
    private function pages(): array
    {
        return [
            'invité (accueil)' => [null, '/'],
            'invité (connexion)' => [null, '/login'],
            'citoyen' => [User::factory()->create(), '/espace'],
            'agent' => [User::factory()->agent()->create(), '/agent'],
            'admin' => [User::factory()->admin()->create(), '/admin'],
        ];
    }

    private function visit(?User $user, string $url)
    {
        return ($user ? $this->actingAs($user) : $this)->get($url);
    }

    private function donnees(array $surcharge = []): array
    {
        return array_merge([
            'titre' => 'Coupure d\'eau',
            'niveau' => 'info',
            'ce_qui_se_passe' => 'Une coupure d\'eau est prévue demain matin.',
            'ce_quil_faut_faire' => 'Faites des réserves d\'eau dès ce soir.',
        ], $surcharge);
    }

    // --- Affichage du bandeau --------------------------------------------------------------------------

    public function test_active_alert_is_displayed_to_guests_citizens_agents_and_admins(): void
    {
        Alerte::factory()->urgente()->create([
            'titre' => 'Montée des eaux',
            'ce_qui_se_passe' => 'Le niveau de l\'eau monte au sud.',
            'ce_quil_faut_faire' => 'Évitez les rues basses.',
        ]);

        foreach ($this->pages() as $nom => [$user, $url]) {
            $this->visit($user, $url)
                ->assertOk()
                ->assertSee('Montée des eaux')
                ->assertSee('Urgent')
                ->assertSee('Le niveau de l&#039;eau monte au sud.', false)
                ->assertSee('Évitez les rues basses.')
                ->assertSee('Voir le détail');
        }
    }

    public function test_future_alert_is_not_displayed(): void
    {
        Alerte::factory()->programmee()->create(['titre' => 'Alerte du futur']);

        foreach ($this->pages() as [$user, $url]) {
            $this->visit($user, $url)->assertOk()->assertDontSee('Alerte du futur');
        }
    }

    public function test_expired_alert_is_not_displayed(): void
    {
        Alerte::factory()->terminee()->create(['titre' => 'Alerte expirée']);

        foreach ($this->pages() as [$user, $url]) {
            $this->visit($user, $url)->assertOk()->assertDontSee('Alerte expirée');
        }
    }

    public function test_alert_follows_the_clock_start_and_end_boundaries(): void
    {
        Alerte::factory()->create(['titre' => 'Fenêtre', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(3)]);

        $this->get('/')->assertDontSee('Fenêtre');

        Carbon::setTestNow(now()->addHour());           // début : pile maintenant → affichée
        $this->get('/')->assertSee('Fenêtre');

        Carbon::setTestNow(now()->addHours(2)->subSecond());
        $this->get('/')->assertSee('Fenêtre');

        Carbon::setTestNow(now()->addSecond());         // fin : pile maintenant → retirée
        $this->get('/')->assertDontSee('Fenêtre');
    }

    public function test_terminer_maintenant_removes_the_alert(): void
    {
        $alerte = Alerte::factory()->urgente()->create(['titre' => 'À terminer']);
        $admin = $this->admin();

        $this->get('/')->assertSee('À terminer');
        $this->actingAs($admin)->get('/admin/alertes')->assertSee('Terminer maintenant');

        $this->actingAs($admin)->post(route('admin.alertes.terminer', $alerte))
            ->assertRedirect(route('admin.alertes.index'));

        $this->assertTrue($alerte->fresh()->ends_at->equalTo(now()));
        $this->assertSame(Alerte::ETAT_TERMINEE, $alerte->fresh()->etat());
        $this->get('/')->assertDontSee('À terminer');
        $this->actingAs($admin)->get('/admin/alertes')->assertSee('Terminée')->assertDontSee('Terminer maintenant');
    }

    public function test_terminer_on_a_scheduled_alert_cancels_it_without_breaking_the_window(): void
    {
        $alerte = Alerte::factory()->programmee()->create();

        $this->actingAs($this->admin())->post(route('admin.alertes.terminer', $alerte))->assertRedirect();

        $alerte->refresh();
        $this->assertFalse($alerte->ends_at->lessThan($alerte->starts_at));
        $this->assertSame(Alerte::ETAT_TERMINEE, $alerte->etat());
    }

    public function test_role_alert_is_only_used_for_urgent_alerts(): void
    {
        Alerte::factory()->create(['titre' => 'Info seule']);
        $this->get('/')->assertSee('role="status"', false)->assertDontSee('role="alert"', false);

        Alerte::factory()->vigilance()->create(['titre' => 'Vigilance seule']);
        $this->get('/')->assertSee('role="status"', false)->assertDontSee('role="alert"', false);

        Alerte::factory()->urgente()->create(['titre' => 'Urgence']);
        $content = $this->get('/')->getContent();

        $this->assertSame(1, substr_count($content, 'role="alert"'));
        $this->assertSame(2, substr_count($content, 'role="status"'));
        $this->assertMatchesRegularExpression('/role="alert"[^>]*aria-labelledby="alerte-\d+"[^>]*>\s*<p[^>]*>\s*<span[^>]*>Urgent<\/span>/', $content);
    }

    public function test_level_is_written_as_text(): void
    {
        Alerte::factory()->urgente()->create(['titre' => 'A']);
        Alerte::factory()->vigilance()->create(['titre' => 'B']);
        Alerte::factory()->create(['titre' => 'C']);

        $this->get('/')
            ->assertSeeInOrder(['Urgent', 'A', 'Vigilance', 'B', 'Information', 'C']);
    }

    public function test_banner_orders_by_level_then_most_recent(): void
    {
        Alerte::factory()->create(['titre' => 'Info récente', 'starts_at' => now()->subMinutes(5)]);
        Alerte::factory()->vigilance()->create(['titre' => 'Vigilance ancienne', 'starts_at' => now()->subDays(1)]);
        Alerte::factory()->urgente()->create(['titre' => 'Urgent ancien', 'starts_at' => now()->subDays(2)]);

        // Le bandeau montre les 3 premières : l'urgent d'abord, même s'il est le plus ancien.
        $this->get('/')->assertSeeInOrder(['Urgent ancien', 'Vigilance ancienne', 'Info récente']);
    }

    public function test_list_page_orders_by_level_then_most_recent(): void
    {
        Alerte::factory()->create(['titre' => 'Info récente', 'starts_at' => now()->subMinutes(5)]);
        Alerte::factory()->vigilance()->create(['titre' => 'Vigilance ancienne', 'starts_at' => now()->subDays(1)]);
        Alerte::factory()->urgente()->create(['titre' => 'Urgent ancien', 'starts_at' => now()->subDays(2)]);
        Alerte::factory()->vigilance()->create(['titre' => 'Vigilance récente', 'starts_at' => now()->subHour()]);

        $this->get('/alertes')->assertSeeInOrder(['Urgent ancien', 'Vigilance récente', 'Vigilance ancienne', 'Info récente']);
    }

    public function test_banner_shows_at_most_three_alerts_with_a_link_to_all(): void
    {
        Alerte::factory()->count(5)->create();

        $content = $this->get('/')->getContent();
        $this->assertSame(3, substr_count($content, 'class="alerte '));
        $this->assertStringContainsString('Voir toutes les alertes', $content);
        $this->assertStringContainsString('(5)', $content);
    }

    public function test_banner_mentions_vulnerable_guidelines_when_present(): void
    {
        Alerte::factory()->avecConsignesVulnerables()->create();

        $this->get('/')->assertSee('Des consignes pour les personnes vulnérables sont disponibles dans le détail.');
    }

    public function test_banner_does_nothing_and_adds_no_markup_without_alerts(): void
    {
        $this->get('/')->assertOk()->assertDontSee('<aside', false)->assertDontSee('class="alerte ', false);
    }

    // --- Requêtes --------------------------------------------------------------------------------------

    public function test_banner_uses_a_single_query_without_n_plus_one(): void
    {
        Model::preventLazyLoading();
        $count = function (): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/')->assertOk();
            DB::disableQueryLog();
            $queries = collect(DB::getQueryLog())->pluck('query');

            return [$queries->count(), $queries->filter(fn ($q) => str_contains($q, '"alertes"'))->count()];
        };

        [$vide, $videAlertes] = $count();
        $this->assertSame(1, $videAlertes);   // une requête même sans rien à afficher
        $this->assertSame(1, $vide);          // et aucune autre

        Alerte::factory()->count(6)->create(['user_id' => User::factory()->admin()->create()->id]);
        [$plein, $pleinAlertes] = $count();

        $this->assertSame(1, $pleinAlertes);
        $this->assertSame($vide, $plein, 'Le nombre de requêtes ne doit pas dépendre du nombre d\'alertes.');
    }

    public function test_alert_list_page_reuses_the_single_query(): void
    {
        Alerte::factory()->count(4)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/alertes')->assertOk();
        DB::disableQueryLog();

        $this->assertSame(1, collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, '"alertes"'))->count());
    }

    // --- Pages publiques -------------------------------------------------------------------------------

    public function test_public_list_shows_only_active_alerts_to_everyone_with_breadcrumb_and_single_h1(): void
    {
        Alerte::factory()->urgente()->create(['titre' => 'Active un']);
        Alerte::factory()->create(['titre' => 'Active deux']);
        Alerte::factory()->programmee()->create(['titre' => 'Future']);
        Alerte::factory()->terminee()->create(['titre' => 'Expirée']);

        foreach ($this->pages() as [$user, ]) {
            $response = $this->visit($user, '/alertes')->assertOk()
                ->assertSee('Active un')->assertSee('Active deux')
                ->assertDontSee('Future')->assertDontSee('Expirée');

            $this->assertSame(1, substr_count($response->getContent(), '<h1'));
            $response->assertSee('aria-label="Fil d&#039;Ariane"', false)->assertSee('Alertes en cours');
        }
    }

    public function test_public_list_has_an_empty_state_and_no_duplicate_banner(): void
    {
        $this->get('/alertes')->assertOk()->assertSee('Aucune alerte en cours.');

        Alerte::factory()->create(['titre' => 'Une alerte']);
        $content = $this->get('/alertes')->getContent();
        $this->assertStringNotContainsString('<aside', $content);   // le bandeau est masqué : la page liste déjà tout
        $this->assertSame(1, substr_count($content, 'Voir le détail<'));
    }

    public function test_detail_page_shows_everything_and_vulnerable_guidelines_when_present(): void
    {
        $alerte = Alerte::factory()->vigilance()->avecConsignesVulnerables('Consigne spéciale pour les personnes âgées.')->create([
            'titre' => 'Canicule',
            'secteur' => 'Quartier nord',
            'ce_qui_se_passe' => 'Il fait très chaud.',
            'ce_quil_faut_faire' => 'Buvez de l\'eau.',
            'starts_at' => Carbon::parse('2026-10-03 06:30:00'),
        ]);

        foreach ($this->pages() as [$user, ]) {
            $this->visit($user, route('alertes.show', $alerte, false))->assertOk()
                ->assertSee('Canicule')
                ->assertSee('Vigilance')
                ->assertSee('Quartier nord')
                ->assertSee('Il fait très chaud.')
                ->assertSee('Buvez de l&#039;eau.', false)
                ->assertSee('Consignes pour les personnes vulnérables')
                ->assertSee('Consigne spéciale pour les personnes âgées.')
                ->assertSee('03/10/2026 06:30');
        }
    }

    public function test_detail_page_hides_the_vulnerable_section_when_empty_and_has_breadcrumb_and_h1(): void
    {
        $alerte = Alerte::factory()->create(['titre' => 'Sans consignes']);

        $response = $this->get(route('alertes.show', $alerte))->assertOk()
            ->assertDontSee('Consignes pour les personnes vulnérables')
            ->assertSee('aria-label="Fil d&#039;Ariane"', false)
            ->assertSee('<title>Sans consignes – ', false);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_detail_of_a_scheduled_alert_is_404_and_of_an_ended_one_shows_a_notice(): void
    {
        $this->get(route('alertes.show', Alerte::factory()->programmee()->create()))->assertNotFound();

        $this->get(route('alertes.show', Alerte::factory()->terminee()->create(['titre' => 'Passée'])))
            ->assertOk()
            ->assertSee('Alerte terminée')
            ->assertSee('Passée');
    }

    // --- Gestion par l'admin ---------------------------------------------------------------------------

    public function test_admin_manages_alerts_but_agent_and_citizen_get_403_and_guests_are_redirected(): void
    {
        $alerte = Alerte::factory()->create();

        $this->actingAs($this->admin())->get('/admin/alertes')->assertOk();
        $this->actingAs($this->admin())->get('/admin/alertes/nouvelle')->assertOk();

        foreach ([User::factory()->agent()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get('/admin/alertes')->assertForbidden();
            $this->actingAs($user)->get('/admin/alertes/nouvelle')->assertForbidden();
            $this->actingAs($user)->post('/admin/alertes', $this->donnees())->assertForbidden();
            $this->actingAs($user)->post(route('admin.alertes.terminer', $alerte))->assertForbidden();
        }
        $this->assertSame(1, Alerte::count());
        $this->assertNull($alerte->fresh()->ends_at);

        auth()->logout();
        $this->get('/admin/alertes')->assertRedirect('/login');
        $this->get('/admin/alertes/nouvelle')->assertRedirect('/login');
        $this->post('/admin/alertes', $this->donnees())->assertRedirect('/login');
        $this->post(route('admin.alertes.terminer', $alerte))->assertRedirect('/login');
    }

    public function test_alert_link_is_in_the_admin_navigation_only(): void
    {
        $url = route('admin.alertes.index');

        $this->actingAs($this->admin())->get('/admin')->assertSee($url);
        $this->actingAs(User::factory()->agent()->create())->get('/agent')->assertDontSee($url);
        $this->actingAs(User::factory()->create())->get('/espace')->assertDontSee($url);
        $this->get('/')->assertDontSee($url);
    }

    public function test_admin_creates_an_alert_published_by_them_and_it_is_immediately_visible(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/alertes', $this->donnees([
            'niveau' => 'urgent',
            'secteur' => 'Quartier sud',
            'consignes_vulnerables' => 'Aidez vos voisins fragiles.',
        ]))->assertRedirect(route('admin.alertes.index'))->assertSessionHasNoErrors();

        $alerte = Alerte::firstOrFail();
        $this->assertSame($admin->id, $alerte->user_id);
        $this->assertSame(Niveau::Urgent, $alerte->niveau);
        $this->assertSame('Quartier sud', $alerte->secteur);
        $this->assertTrue($alerte->starts_at->equalTo(now()));   // début vide = maintenant
        $this->assertNull($alerte->ends_at);

        $this->get('/')->assertSee('Coupure d&#039;eau', false)->assertSee('Urgent');
    }

    public function test_dates_entered_in_local_time_are_stored_and_displayed_in_local_time(): void
    {
        $this->actingAs($this->admin())->post('/admin/alertes', $this->donnees([
            'starts_at' => '2026-10-04T08:30',
            'ends_at' => '2026-10-04T18:00',
        ]))->assertSessionHasNoErrors();

        $alerte = Alerte::firstOrFail();
        $this->assertSame('2026-10-04 08:30:00', $alerte->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(Alerte::ETAT_PROGRAMMEE, $alerte->etat());

        $this->actingAs($this->admin())->get('/admin/alertes')->assertSee('04/10/2026 08:30')->assertSee('04/10/2026 18:00')->assertSee('Programmée');
    }

    public function test_end_must_be_after_start(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/alertes', $this->donnees(['starts_at' => '2026-10-05T10:00', 'ends_at' => '2026-10-05T09:00']))
            ->assertSessionHasErrors('ends_at');
        $this->actingAs($admin)->post('/admin/alertes', $this->donnees(['starts_at' => '2026-10-05T10:00', 'ends_at' => '2026-10-05T10:00']))
            ->assertSessionHasErrors('ends_at');
        // Début vide (= maintenant) : une fin dans le passé est refusée.
        $this->actingAs($admin)->post('/admin/alertes', $this->donnees(['ends_at' => '2026-10-03T09:00']))
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, Alerte::count());

        $this->actingAs($admin)->post('/admin/alertes', $this->donnees(['starts_at' => '2026-10-05T10:00', 'ends_at' => '2026-10-05T10:01']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Alerte::count());
    }

    public function test_validation_rules_on_level_and_text_lengths(): void
    {
        $admin = $this->admin();

        foreach ([
            ['niveau' => 'catastrophe'],
            ['niveau' => ''],
            ['titre' => ''],
            ['titre' => str_repeat('a', 151)],
            ['ce_qui_se_passe' => ''],
            ['ce_qui_se_passe' => str_repeat('a', 1001)],
            ['ce_quil_faut_faire' => str_repeat('a', 1001)],
            ['secteur' => str_repeat('a', 101)],
            ['consignes_vulnerables' => str_repeat('a', 1001)],
            ['starts_at' => 'pas une date'],
        ] as $surcharge) {
            $champ = array_key_first($surcharge);
            $this->actingAs($admin)->post('/admin/alertes', $this->donnees($surcharge))->assertSessionHasErrors($champ);
        }

        $this->assertSame(0, Alerte::count());

        // Limites acceptées.
        $this->actingAs($admin)->post('/admin/alertes', $this->donnees([
            'titre' => str_repeat('a', 150),
            'ce_qui_se_passe' => str_repeat('b', 1000),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, Alerte::count());
    }

    public function test_validation_errors_are_in_french_and_input_is_kept(): void
    {
        $this->actingAs($this->admin())->from('/admin/alertes/nouvelle')->followingRedirects()
            ->post('/admin/alertes', $this->donnees(['titre' => '', 'niveau' => 'zzz', 'ce_qui_se_passe' => 'Texte conservé']))
            ->assertSee('Le champ titre est obligatoire.')
            ->assertSee('La valeur choisie pour niveau est invalide.')
            ->assertSee('Texte conservé');
    }

    public function test_extra_fields_are_ignored(): void
    {
        $admin = $this->admin();
        $autre = User::factory()->create();

        $this->actingAs($admin)->post('/admin/alertes', $this->donnees([
            'user_id' => $autre->id,
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
            'updated_at' => '2000-01-01 00:00:00',
            'is_admin' => true,
        ]))->assertSessionHasNoErrors();

        $alerte = Alerte::firstOrFail();
        $this->assertSame($admin->id, $alerte->user_id);
        $this->assertNotSame(999, $alerte->id);
        $this->assertSame(now()->year, $alerte->created_at->year);
    }

    public function test_the_publisher_is_kept_as_null_if_their_account_is_deleted(): void
    {
        $admin = $this->admin();
        $alerte = Alerte::factory()->create(['user_id' => $admin->id]);

        $admin->delete();

        $this->assertNull($alerte->fresh()->user_id);
        $this->assertSame(1, Alerte::count());
    }

    public function test_scope_active_matches_the_definition(): void
    {
        $active = Alerte::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => null]);
        $activeAvecFin = Alerte::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addMinute()]);
        Alerte::factory()->create(['starts_at' => now()->addMinute(), 'ends_at' => null]);
        Alerte::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()]);          // finit pile maintenant
        Alerte::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->subSecond()]);

        $this->assertEqualsCanonicalizing([$active->id, $activeAvecFin->id], Alerte::active()->pluck('id')->all());
    }

    // --- Seeder ----------------------------------------------------------------------------------------

    public function test_seeder_creates_a_water_rise_and_a_heat_wave_alert_with_relative_dates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Alerte::active()->count());

        $eau = Alerte::where('titre', 'like', 'Montée%')->firstOrFail();
        $chaleur = Alerte::where('titre', 'like', 'Vague de chaleur%')->firstOrFail();

        $this->assertSame(Niveau::Urgent, $eau->niveau);
        $this->assertNull($eau->consignes_vulnerables);
        $this->assertSame(Niveau::Vigilance, $chaleur->niveau);
        $this->assertNotEmpty($chaleur->consignes_vulnerables);
        $this->assertSame(User::where('email', 'admin@novaterra.test')->value('id'), $eau->user_id);

        $this->get('/')->assertSee('Montée du niveau de l&#039;eau', false)->assertSee('Vague de chaleur extrême');
    }

    public function test_seeder_is_rerunnable_without_duplicates_and_dates_never_expire(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(AlerteSeeder::class);
        $this->assertSame(2, Alerte::count());

        // Un mois plus tard, relancer le seeder remet les alertes « en cours » (dates relatives à maintenant).
        Carbon::setTestNow(now()->addMonth());
        $this->assertSame(0, Alerte::active()->count());
        $this->seed(AlerteSeeder::class);

        $this->assertSame(2, Alerte::count());
        $this->assertSame(2, Alerte::active()->count());
    }
}
