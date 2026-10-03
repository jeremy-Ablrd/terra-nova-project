<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_helpers(): void
    {
        $this->assertTrue(User::factory()->make()->isCitoyen());
        $this->assertTrue(User::factory()->agent()->make()->isAgent());
        $this->assertTrue(User::factory()->admin()->make()->isAdmin());
        $this->assertFalse(User::factory()->agent()->make()->isAdmin());
    }

    public function test_seeder_creates_one_account_per_role_and_is_rerunnable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(Role::Citoyen, User::where('email', 'citoyen@novaterra.test')->first()->role);
        $this->assertSame(Role::Agent, User::where('email', 'agent@novaterra.test')->first()->role);
        $this->assertSame(Role::Admin, User::where('email', 'admin@novaterra.test')->first()->role);
        $this->assertSame(3, User::count());
    }

    public function test_login_redirects_by_role(): void
    {
        foreach ([
            [User::factory()->create(), '/espace'],
            [User::factory()->agent()->create(), '/agent'],
            [User::factory()->admin()->create(), '/admin'],
        ] as [$user, $url]) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect($url);
            $this->post('/logout');
        }
    }

    public function test_originally_requested_url_takes_priority_over_role_home(): void
    {
        $admin = User::factory()->admin()->create();

        $this->get('/mes-demandes')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/mes-demandes');
    }

    public function test_logged_in_user_visiting_login_is_sent_to_role_home(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/login')->assertRedirect('/agent');
    }

    public function test_agent_and_admin_pages_welcome_the_user(): void
    {
        $this->actingAs(User::factory()->agent()->create(['name' => 'Alex']))
            ->get('/agent')->assertOk()->assertSee('Espace agent')->assertSee('Bienvenue Alex');

        $this->actingAs(User::factory()->admin()->create(['name' => 'Sacha']))
            ->get('/admin')->assertOk()->assertSee('Administration')->assertSee('Bienvenue Sacha');
    }

    public function test_navigation_shows_role_and_matching_links(): void
    {
        $this->actingAs(User::factory()->create())->get('/espace')
            ->assertSee('Citoyen')->assertSee('Mes demandes')->assertDontSee('Espace agent')->assertDontSee('Administration');

        $this->actingAs(User::factory()->agent()->create())->get('/agent')
            ->assertSee('Agent')->assertSee('Espace agent')->assertDontSee('Mes demandes')->assertDontSee('Administration');

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertSee('Administrateur')->assertSee('Administration')->assertSee('Comptes')
            ->assertDontSee('Mes demandes')->assertDontSee('Espace agent')->assertDontSee('Contacter la mairie')
            ->assertDontSee(route('agent.index'))->assertDontSee(route('demandes.index'));
    }

    public function test_admin_sees_accounts_and_can_change_a_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Camille', 'email' => 'camille@example.com']);

        $this->actingAs($admin)->get('/admin/comptes')
            ->assertOk()->assertSee('Camille')->assertSee('camille@example.com');

        $this->actingAs($admin)->post(route('admin.comptes.role', $target), ['role' => 'agent', 'name' => 'Piraté'])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame(Role::Agent, $target->role);
        $this->assertSame('Camille', $target->name);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.comptes.role', $target), ['role' => 'superman'])
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::Citoyen, $target->fresh()->role);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.comptes.role', $admin), ['role' => 'citoyen'])
            ->assertSessionHasErrors('role');
        $this->assertSame(Role::Admin, $admin->fresh()->role);

        $this->actingAs($admin)->post(route('admin.comptes.role', $admin), ['role' => 'admin'])
            ->assertSessionHasNoErrors();
    }

    public function test_non_admin_cannot_change_roles(): void
    {
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->post(route('admin.comptes.role', $citoyen), ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(Role::Citoyen, $citoyen->fresh()->role);
    }
}
