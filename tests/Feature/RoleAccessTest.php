<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/espace', '/agent', '/admin', '/admin/comptes'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_guest_cannot_post_role_change(): void
    {
        $target = User::factory()->create();

        $this->post(route('admin.comptes.role', $target), ['role' => 'admin'])->assertRedirect('/login');
        $this->assertSame(Role::Citoyen, $target->fresh()->role);
    }

    public function test_citizen_gets_403_on_agent_and_admin_areas(): void
    {
        $citoyen = User::factory()->create();

        foreach (['/agent', '/admin', '/admin/comptes'] as $url) {
            $this->actingAs($citoyen)->get($url)->assertForbidden();
        }
    }

    public function test_agent_can_reach_agent_area_but_not_admin(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get('/agent')->assertOk();
        $this->actingAs($agent)->get('/admin')->assertForbidden();
        $this->actingAs($agent)->get('/admin/comptes')->assertForbidden();
    }

    public function test_admin_reaches_admin_area_but_not_agent_tools(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/comptes')->assertOk();
        $this->actingAs($admin)->get('/agent')->assertForbidden()
            ->assertSee('Accès refusé', false)
            ->assertSee('href="/admin"', false);
    }

    public function test_citizen_posting_role_change_gets_403_and_role_is_unchanged(): void
    {
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->post(route('admin.comptes.role', $citoyen), ['role' => 'admin'])->assertForbidden();
        $this->assertSame(Role::Citoyen, $citoyen->fresh()->role);
    }

    public function test_agent_cannot_post_role_change(): void
    {
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->post(route('admin.comptes.role', $agent), ['role' => 'admin'])->assertForbidden();
        $this->assertSame(Role::Agent, $agent->fresh()->role);
    }

    public function test_forbidden_page_is_in_french_with_link_back_to_own_space(): void
    {
        $this->actingAs(User::factory()->create())->get('/agent')
            ->assertForbidden()
            ->assertSee('Accès refusé')
            ->assertSee('Retour à mon espace')
            ->assertSee('href="/espace"', false);

        $this->actingAs(User::factory()->agent()->create())->get('/admin')
            ->assertForbidden()
            ->assertSee('href="/agent"', false);
    }

    public function test_citizen_cannot_open_someone_elses_demande(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('demandes.show', Demande::factory()->create()))
            ->assertForbidden();
    }

    public function test_agent_can_view_any_demande_but_admin_cannot(): void
    {
        $demande = Demande::factory()->create();

        $this->actingAs(User::factory()->agent()->create())->get(route('demandes.show', $demande))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('demandes.show', $demande))->assertForbidden();
    }

    public function test_only_agent_can_update_demande_status(): void
    {
        $demande = Demande::factory()->create();

        $this->assertFalse(Gate::forUser($demande->user)->allows('updateStatus', $demande));
        $this->assertTrue(Gate::forUser(User::factory()->agent()->create())->allows('updateStatus', $demande));
        $this->assertFalse(Gate::forUser(User::factory()->admin()->create())->allows('updateStatus', $demande));
    }
}
