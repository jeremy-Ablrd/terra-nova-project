<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactAccesTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_can_open_the_contact_form(): void
    {
        $this->actingAs(User::factory()->create())->get('/contact')->assertOk();
    }

    public function test_agent_and_admin_get_403_on_contact_form_submission_and_confirmation(): void
    {
        $demande = Demande::factory()->create();

        foreach ([User::factory()->agent()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get('/contact')->assertForbidden();
            $this->actingAs($user)->post('/contact', [
                'objet' => 'Tentative',
                'message' => 'Un message suffisamment long.',
            ])->assertForbidden();
            $this->actingAs($user)->get(route('contact.confirmation', $demande))->assertForbidden();
        }

        $this->assertSame(1, Demande::count()); // seule la demande de la factory : rien n'a été créé
    }

    public function test_forbidden_page_sends_the_agent_back_to_the_agent_space(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/contact')
            ->assertForbidden()
            ->assertSee('Accès refusé')
            ->assertSee('href="/agent"', false);
    }

    public function test_contact_link_is_visible_for_citizens_only(): void
    {
        $url = route('contact.create');

        $this->actingAs(User::factory()->create())->get('/espace')
            ->assertSee('Contacter la mairie')
            ->assertSee($url);

        $this->actingAs(User::factory()->agent()->create())->get('/agent')
            ->assertDontSee('Contacter la mairie')
            ->assertDontSee($url);

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertDontSee('Contacter la mairie')
            ->assertDontSee($url);
    }

    public function test_new_demande_buttons_do_not_point_agents_or_admins_to_a_forbidden_page(): void
    {
        $url = route('contact.create');

        foreach (['/espace'] as $page) {
            $this->actingAs(User::factory()->create())->get($page)->assertSee('Nouvelle demande');
            $this->actingAs(User::factory()->agent()->create())->get($page)->assertOk()->assertDontSee($url);
            $this->actingAs(User::factory()->admin()->create())->get($page)->assertOk()->assertDontSee($url);
        }

        // /mes-demandes est réservé au citoyen (role:citoyen) : agent et admin reçoivent un 403.
        $this->actingAs(User::factory()->create())->get('/mes-demandes')->assertSee('Nouvelle demande');
        $this->actingAs(User::factory()->agent()->create())->get('/mes-demandes')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/mes-demandes')->assertForbidden();
    }
}
