<?php

namespace Tests\Feature;

use App\Enums\TypeDemande;
use App\Models\Demande;
use App\Models\EvenementSecurite;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Journal de sécurité côté admin, page publique /securite, purge, refus 403 tracés (F70), ComptePolicy. */
class SecuriteAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $citoyen;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 12:00:00');
        Cache::flush();
        Model::preventLazyLoading();

        $this->admin = User::factory()->admin()->create();
        $this->agent = User::factory()->agent()->create();
        $this->citoyen = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    private function evenement(string $type, string $cree, ?string $email = 'c***@e***.test', string $route = 'login'): EvenementSecurite
    {
        return EvenementSecurite::forceCreate(['type' => $type, 'email_masque' => $email, 'ip' => '10.0.0.1', 'route' => $route, 'created_at' => $cree]);
    }

    // --- Page admin ---

    public function test_only_the_admin_can_open_the_security_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/securite')->assertOk();
        $this->actingAs($this->agent)->get('/admin/securite')->assertForbidden();
        $this->actingAs($this->citoyen)->get('/admin/securite')->assertForbidden();

        auth()->logout();
        $this->get('/admin/securite')->assertRedirect(route('login'));
    }

    public function test_the_last_24_hours_are_counted_exactly(): void
    {
        foreach (['2026-10-04 11:00:00', '2026-10-03 13:00:00', '2026-10-03 11:00:00'] as $date) { // le dernier a plus de 24 h
            $this->evenement('echec_connexion', $date);
        }
        $this->evenement('blocage', '2026-10-04 11:30:00');
        $this->evenement('nouvel_appareil', '2026-10-04 09:00:00');
        $this->evenement('nouvel_appareil', '2026-10-04 10:00:00');
        $this->evenement('acces_refuse', '2026-10-04 08:00:00', null, 'agent.index');

        $page = $this->actingAs($this->admin)->get('/admin/securite')->assertOk()
            ->assertSee('2 échecs de connexion')
            ->assertSee('1 blocage')
            ->assertSee('2 nouveaux appareils')
            ->assertSee('1 accès refusé')
            ->assertSeeInOrder(['Tous', '(7)', 'Échec de connexion', '(3)', 'Blocage temporaire', '(1)', 'Nouvel appareil', '(2)', 'Accès refusé', '(1)'])
            ->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
        $this->assertStringContainsString('<caption', $page);
        $this->assertStringContainsString('scope="col"', $page);
    }

    public function test_the_list_shows_date_type_masked_email_and_route_newest_first_with_a_filter(): void
    {
        $this->evenement('echec_connexion', '2026-10-04 09:15:00', 'j***@d***.fr');
        $this->evenement('acces_refuse', '2026-10-04 11:45:00', null, 'agent.demandes.index');

        $this->actingAs($this->admin)->get('/admin/securite')->assertOk()
            ->assertSeeInOrder(['04/10/2026 11:45', 'Accès refusé', 'agent.demandes.index', '04/10/2026 09:15', 'Échec de connexion', 'j***@d***.fr', 'login']);

        $page = $this->actingAs($this->admin)->get('/admin/securite?type=echec_connexion')->assertOk()
            ->assertSee('j***@d***.fr')
            ->assertDontSee('agent.demandes.index')
            ->getContent();
        $this->assertSame(1, substr_count($page, 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>\s*Échec de connexion/u', $page);

        foreach (['?type=nimporte', '?type[]=blocage', '?type='] as $requete) {
            $this->actingAs($this->admin)->get('/admin/securite'.$requete)->assertOk()->assertSee('agent.demandes.index');
        }
    }

    public function test_pagination_by_twenty_keeps_the_filter(): void
    {
        foreach (range(1, 25) as $i) {
            $this->evenement('echec_connexion', sprintf('2026-10-04 10:%02d:00', $i));
        }

        $page1 = $this->actingAs($this->admin)->get('/admin/securite?type=echec_connexion')->getContent();
        $this->assertSame(20, substr_count($page1, '<th scope="row"'));
        preg_match('/href="([^"]*page=2[^"]*)"/', $page1, $m);
        $this->assertStringContainsString('type=echec_connexion', html_entity_decode($m[1]));

        $page2 = $this->actingAs($this->admin)->get(html_entity_decode($m[1]))->getContent();
        $this->assertSame(5, substr_count($page2, '<th scope="row"'));
    }

    public function test_empty_states_are_in_french(): void
    {
        $this->actingAs($this->admin)->get('/admin/securite')->assertOk()
            ->assertSee('Aucun événement de sécurité enregistré pour le moment.')
            ->assertSee('Aucun échec de connexion');

        $this->evenement('blocage', '2026-10-04 10:00:00');
        $this->actingAs($this->admin)->get('/admin/securite?type=acces_refuse')->assertSee('Aucun événement de ce type pour le moment.');
    }

    public function test_the_page_runs_a_constant_number_of_event_queries(): void
    {
        $compter = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get('/admin/securite')->assertOk();
            $n = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'evenements_securite'))->count();
            DB::disableQueryLog();

            return $n;
        };

        $this->evenement('blocage', '2026-10-04 10:00:00');
        $avant = $compter();

        foreach (range(1, 60) as $i) {
            $this->evenement('echec_connexion', '2026-10-04 11:00:00');
        }

        $this->assertSame($avant, $compter());
        $this->assertLessThanOrEqual(4, $avant); // 1 requête groupée + la pagination (comptage et lecture) + l'alerte de connexion de la barre de navigation
    }

    public function test_the_security_link_is_in_the_admin_navigation_only(): void
    {
        $url = route('admin.securite');

        $this->actingAs($this->admin)->get('/admin')->assertSee($url);
        $this->actingAs($this->agent)->get('/agent')->assertDontSee($url);
        $this->actingAs($this->citoyen)->get('/espace')->assertDontSee($url);
        auth()->logout();
        $this->get('/')->assertDontSee($url);
    }

    // --- Page publique ---

    public function test_the_public_security_page_is_honest_and_linked_from_the_footer(): void
    {
        $page = $this->get('/securite')->assertOk()
            ->assertSee('Ce qui protège votre compte')
            ->assertSee('Ce que la plateforme ne fait pas')
            ->assertSee('Pas de double authentification')
            ->assertSee('Pas d&#039;e-mail d&#039;alerte', false)
            ->assertSee('Journal de sécurité sans données personnelles')
            ->assertSee('unsafe-eval')
            ->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
        $this->assertStringContainsString('Fil d&#039;Ariane', $page);

        $url = route('securite');
        $this->get('/')->assertSee($url);
        $this->actingAs($this->citoyen)->get('/espace')->assertSee($url);
        $this->actingAs($this->agent)->get('/agent')->assertSee($url);
        $this->actingAs($this->admin)->get('/admin')->assertSee($url);
    }

    // --- Purge ---

    public function test_the_purge_command_deletes_events_older_than_thirty_days(): void
    {
        $vieux = $this->evenement('echec_connexion', '2026-09-03 11:59:59');
        $limite = $this->evenement('echec_connexion', '2026-09-04 12:00:01');
        $recent = $this->evenement('blocage', '2026-10-04 11:00:00');

        $this->artisan('novaterra:purger-securite')->assertExitCode(0);

        $this->assertNull($vieux->fresh());
        $this->assertNotNull($limite->fresh());
        $this->assertNotNull($recent->fresh());
    }

    public function test_the_sync_command_runs_the_purge_at_its_end_at_most_once_an_hour(): void
    {
        config(['services.webcup.key' => '']);
        $vieux = $this->evenement('echec_connexion', '2026-08-01 10:00:00');

        $this->artisan('novaterra:sync')->assertExitCode(1); // pas de clé : la synchro échoue, la purge a lieu quand même
        $this->assertNull($vieux->fresh());

        $autre = $this->evenement('echec_connexion', '2026-08-01 10:00:00');
        $this->artisan('novaterra:sync')->assertExitCode(1);
        $this->assertNotNull($autre->fresh()); // la synchro tourne toutes les 30 s : la purge, au plus une fois par heure

        Carbon::setTestNow('2026-10-04 13:00:01');
        $this->artisan('novaterra:sync')->assertExitCode(1);
        $this->assertNull($autre->fresh());
    }

    // --- F70 : les refus 403 sur /agent et /admin sont tracés ---

    public function test_a_403_on_agent_or_admin_creates_an_event_with_route_role_and_user(): void
    {
        $this->actingAs($this->citoyen)->get('/agent')->assertForbidden();
        $this->actingAs($this->admin)->get('/agent/demandes')->assertForbidden();
        $this->actingAs($this->agent)->get('/admin/comptes')->assertForbidden();

        $evenements = EvenementSecurite::where('type', 'acces_refuse')->orderBy('id')->get();
        $this->assertCount(3, $evenements);
        $this->assertSame(['agent.index', 'agent.demandes.index', 'admin.comptes.index'], $evenements->pluck('route')->all());
        $this->assertSame([$this->citoyen->id, $this->admin->id, $this->agent->id], $evenements->pluck('user_id')->all());
        $this->assertSame(['rôle : citoyen', 'rôle : admin', 'rôle : agent'], $evenements->pluck('detail')->all());
        $this->assertNull($evenements[0]->email_masque); // aucune donnée personnelle
    }

    public function test_a_policy_403_under_agent_is_also_recorded(): void
    {
        $cachee = Demande::factory()->importee('D01')->type(TypeDemande::Institution)->create();

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $cachee))->assertForbidden();

        $this->assertSame('agent.demandes.show', EvenementSecurite::where('type', 'acces_refuse')->value('route'));
    }

    public function test_other_403_and_guests_are_not_recorded(): void
    {
        $autre = Demande::factory()->create();
        $this->actingAs($this->citoyen)->get(route('demandes.show', $autre))->assertForbidden(); // hors /agent et /admin

        auth()->logout();
        $this->get('/agent')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));

        $this->assertSame(0, EvenementSecurite::count());
    }

    public function test_refusals_are_capped_at_ten_per_user_and_per_minute(): void
    {
        foreach (range(1, 12) as $i) {
            $this->actingAs($this->citoyen)->get('/agent')->assertForbidden();
        }
        $this->assertSame(10, EvenementSecurite::where('type', 'acces_refuse')->count());

        // Le plafond est par utilisateur : un autre compte est tracé normalement.
        $this->actingAs(User::factory()->create())->get('/agent')->assertForbidden();
        $this->assertSame(11, EvenementSecurite::where('type', 'acces_refuse')->count());

        // Une minute plus tard, le plafond est levé.
        Carbon::setTestNow('2026-10-04 12:01:01');
        $this->actingAs($this->citoyen)->get('/agent')->assertForbidden();
        $this->assertSame(12, EvenementSecurite::where('type', 'acces_refuse')->count());
    }

    // --- ComptePolicy ---

    public function test_the_role_change_goes_through_the_account_policy(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('updateRole', $this->citoyen));
        $this->assertFalse(Gate::forUser($this->agent)->allows('updateRole', $this->citoyen));
        $this->assertFalse(Gate::forUser($this->citoyen)->allows('updateRole', $this->agent));

        $this->actingAs($this->agent)->post(route('admin.comptes.role', $this->citoyen), ['role' => 'admin'])->assertForbidden();
        $this->assertSame('citoyen', $this->citoyen->fresh()->role->value);

        $this->actingAs($this->admin)->post(route('admin.comptes.role', $this->citoyen), ['role' => 'agent'])->assertSessionHasNoErrors();
        $this->assertSame('agent', $this->citoyen->fresh()->role->value);
    }
}
