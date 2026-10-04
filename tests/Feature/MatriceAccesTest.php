<?php

namespace Tests\Feature;

use App\Models\Alerte;
use App\Models\Contribution;
use App\Models\Demande;
use App\Models\Projet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F70 : matrice d'accès. Toute route GET sous /agent ou /admin : invité → connexion, citoyen sur /agent et /admin → 403,
 * agent sur /admin → 403, admin sur /agent → 403. Les routes sont parcourues par préfixe : une nouvelle route est testée sans rien écrire.
 */
class MatriceAccesTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<LaravelRoute> */
    private function routes(string $prefixe, ?string $methode = null): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $r) => ($r->uri() === $prefixe || str_starts_with($r->uri(), $prefixe.'/'))
                && ($methode === null || in_array($methode, $r->methods(), true)))
            ->values()->all();
    }

    /** URL de la route, avec des enregistrements existants pour ses paramètres (la liaison se fait avant le contrôle du rôle). */
    private function url(LaravelRoute $route): string
    {
        $modeles = ['demande' => Demande::class, 'service' => Service::class, 'alerte' => Alerte::class, 'user' => User::class,
            'projet' => Projet::class, 'contribution' => Contribution::class];
        $parametres = [];

        foreach ($route->parameterNames() as $nom) {
            $this->assertArrayHasKey($nom, $modeles, "Paramètre {{$nom}} inconnu de la matrice : ajoutez-le à MatriceAccesTest::url().");
            $parametres[$nom] = $modeles[$nom]::factory()->create();
        }

        return route($route->getName(), $parametres);
    }

    public function test_the_matrix_covers_enough_routes_to_mean_something(): void
    {
        $this->assertGreaterThanOrEqual(4, count($this->routes('agent', 'GET')));
        $this->assertGreaterThanOrEqual(8, count($this->routes('admin', 'GET')));
    }

    public function test_every_agent_and_admin_route_has_its_role_middleware(): void
    {
        foreach ($this->routes('agent') as $route) {
            $this->assertContains('role:agent', $route->gatherMiddleware(), $route->uri().' sans role:agent');
            $this->assertContains('auth', $route->gatherMiddleware(), $route->uri().' sans auth');
        }
        foreach ($this->routes('admin') as $route) {
            $this->assertContains('role:admin', $route->gatherMiddleware(), $route->uri().' sans role:admin');
            $this->assertContains('auth', $route->gatherMiddleware(), $route->uri().' sans auth');
        }
    }

    public function test_guests_are_redirected_to_login_on_every_agent_and_admin_get_route(): void
    {
        foreach (array_merge($this->routes('agent', 'GET'), $this->routes('admin', 'GET')) as $route) {
            $this->get($this->url($route))->assertRedirect(route('login'));
        }
    }

    public function test_citizens_get_403_on_every_agent_and_admin_get_route(): void
    {
        $citoyen = User::factory()->create();

        foreach (array_merge($this->routes('agent', 'GET'), $this->routes('admin', 'GET')) as $route) {
            $this->actingAs($citoyen)->get($this->url($route))->assertForbidden();
        }
    }

    public function test_agents_get_403_on_every_admin_get_route(): void
    {
        $agent = User::factory()->agent()->create();

        foreach ($this->routes('admin', 'GET') as $route) {
            $this->actingAs($agent)->get($this->url($route))->assertForbidden();
        }
    }

    public function test_admins_get_403_on_every_agent_get_route(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ($this->routes('agent', 'GET') as $route) {
            $this->actingAs($admin)->get($this->url($route))->assertForbidden();
        }
    }

    public function test_the_right_role_still_gets_in_on_the_routes_without_parameters(): void
    {
        $agent = User::factory()->agent()->create();
        $admin = User::factory()->admin()->create();

        foreach ($this->routes('agent', 'GET') as $route) {
            if ($route->parameterNames() === []) {
                $this->actingAs($agent)->get($this->url($route))->assertOk();
            }
        }
        foreach ($this->routes('admin', 'GET') as $route) {
            if ($route->parameterNames() === []) {
                $this->actingAs($admin)->get($this->url($route))->assertOk();
            }
        }
    }

    public function test_every_agent_and_admin_write_route_is_closed_to_the_wrong_roles(): void
    {
        $citoyen = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $admin = User::factory()->admin()->create();

        foreach ($this->routes('agent') as $route) {
            $methode = collect($route->methods())->first(fn ($m) => ! in_array($m, ['GET', 'HEAD'], true));
            if ($methode === null) {
                continue;
            }
            $this->actingAs($citoyen)->call($methode, $this->url($route))->assertForbidden();
            $this->actingAs($admin)->call($methode, $this->url($route))->assertForbidden();
        }
        foreach ($this->routes('admin') as $route) {
            $methode = collect($route->methods())->first(fn ($m) => ! in_array($m, ['GET', 'HEAD'], true));
            if ($methode === null) {
                continue;
            }
            $this->actingAs($citoyen)->call($methode, $this->url($route))->assertForbidden();
            $this->actingAs($agent)->call($methode, $this->url($route))->assertForbidden();
        }
    }
}
