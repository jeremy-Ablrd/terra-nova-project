<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_role_cannot_be_chosen_at_registration(): void
    {
        $this->post('/register', [
            'name' => 'Pirate',
            'email' => 'pirate@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);

        $this->assertSame(Role::Citoyen, User::where('email', 'pirate@example.com')->first()->role);
    }

    public function test_new_citizen_lands_on_personal_space(): void
    {
        $this->post('/register', [
            'name' => 'Camille',
            'email' => 'camille@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->get('/espace')
            ->assertOk()
            ->assertSee('Mon espace citoyen')
            ->assertSee('Bienvenue, Camille')
            ->assertSee('Mes informations')
            ->assertSee('camille@example.com')
            ->assertSee(now()->translatedFormat('j F Y'))
            ->assertSee('Citoyen');
    }
}
