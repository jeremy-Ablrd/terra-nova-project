<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'service_id' => null,
            'objet' => 'Lampadaire en panne',
            'message' => 'Le lampadaire de la rue des Lilas ne fonctionne plus.',
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/contact')->assertRedirect('/login');
        $this->post('/contact', $this->payload())->assertRedirect('/login');
        $this->assertSame(0, Demande::count());
    }

    public function test_citizen_sees_the_form_with_own_identity_and_active_services_only(): void
    {
        $actif = Service::factory()->create(['nom' => 'Service ouvert']);
        Service::factory()->create(['nom' => 'Service fermé', 'actif' => false]);
        $citoyen = User::factory()->create(['name' => 'Camille', 'email' => 'camille@example.com']);

        $this->actingAs($citoyen)->get('/contact')
            ->assertOk()
            ->assertSee('camille@example.com')
            ->assertSee($actif->nom)
            ->assertDontSee('Service fermé')
            ->assertSee('Je ne sais pas, laissez la mairie orienter ma demande');
    }

    public function test_valid_submission_creates_the_demande_and_redirects_to_confirmation(): void
    {
        $citoyen = User::factory()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($citoyen)->post('/contact', $this->payload(['service_id' => $service->id]));

        $demande = Demande::firstOrFail();
        $this->assertSame($citoyen->id, $demande->user_id);
        $this->assertSame($service->id, $demande->service_id);
        $this->assertSame(Statut::Nouvelle, $demande->statut);
        $this->assertNull($demande->agent_id);
        $this->assertNull($demande->traitee_at);
        $this->assertMatchesRegularExpression('/^NT-\d{4}-\d{5}$/', $demande->reference);

        $response->assertRedirect(route('contact.confirmation', $demande));
        $this->actingAs($citoyen)->get(route('contact.confirmation', $demande))
            ->assertOk()
            ->assertSee('Votre demande a bien été envoyée')
            ->assertSee($demande->reference);
    }

    public function test_injected_server_side_fields_are_ignored(): void
    {
        $citoyen = User::factory()->create();
        $autre = User::factory()->create();
        $agent = User::factory()->agent()->create();

        $this->actingAs($citoyen)->post('/contact', $this->payload([
            'statut' => 'traitee',
            'user_id' => $autre->id,
            'agent_id' => $agent->id,
            'traitee_at' => now()->subDay()->toDateTimeString(),
            'reference' => 'NT-1999-00001',
        ]))->assertRedirect();

        $demande = Demande::firstOrFail();
        $this->assertSame($citoyen->id, $demande->user_id);
        $this->assertSame(Statut::Nouvelle, $demande->statut);
        $this->assertNull($demande->agent_id);
        $this->assertNull($demande->traitee_at);
        $this->assertNotSame('NT-1999-00001', $demande->reference);
    }

    public function test_unknown_or_inactive_service_is_rejected(): void
    {
        $citoyen = User::factory()->create();
        $inactif = Service::factory()->create(['actif' => false]);

        $this->actingAs($citoyen)->post('/contact', $this->payload(['service_id' => 9999]))
            ->assertSessionHasErrors('service_id');
        $this->actingAs($citoyen)->post('/contact', $this->payload(['service_id' => $inactif->id]))
            ->assertSessionHasErrors('service_id');

        $this->assertSame(0, Demande::count());
    }

    public function test_empty_service_creates_a_demande_without_service(): void
    {
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->post('/contact', $this->payload(['service_id' => '']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull(Demande::firstOrFail()->service_id);
    }

    public function test_missing_or_too_long_objet_is_rejected(): void
    {
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->post('/contact', $this->payload(['objet' => '']))
            ->assertSessionHasErrors('objet');
        $this->actingAs($citoyen)->post('/contact', $this->payload(['objet' => str_repeat('a', 151)]))
            ->assertSessionHasErrors('objet');

        $this->assertSame(0, Demande::count());
    }

    public function test_missing_too_short_or_too_long_message_is_rejected(): void
    {
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->post('/contact', $this->payload(['message' => '']))
            ->assertSessionHasErrors('message');
        $this->actingAs($citoyen)->post('/contact', $this->payload(['message' => 'court']))
            ->assertSessionHasErrors('message');
        $this->actingAs($citoyen)->post('/contact', $this->payload(['message' => str_repeat('a', 3001)]))
            ->assertSessionHasErrors('message');
        $this->assertSame(0, Demande::count());

        // Limite basse : exactement 10 caractères → accepté.
        $this->actingAs($citoyen)->post('/contact', $this->payload(['message' => '0123456789']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Demande::count());
    }

    public function test_validation_errors_are_in_french_and_old_input_is_kept(): void
    {
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->from('/contact')
            ->followingRedirects()
            ->post('/contact', $this->payload(['objet' => '', 'message' => 'court']))
            ->assertSee('Le champ objet est obligatoire.')
            ->assertSee('Le champ message doit contenir au moins 10 caractères.')
            ->assertSee('court');
    }

    public function test_confirmation_of_someone_elses_demande_is_forbidden_except_for_agents(): void
    {
        $demande = Demande::factory()->create();
        $url = route('contact.confirmation', $demande);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->agent()->create())->get($url)->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertForbidden();
    }

    public function test_owner_sees_the_summary_on_the_confirmation_page(): void
    {
        $citoyen = User::factory()->create();
        $service = Service::factory()->create(['nom' => 'Voirie test']);
        $avecService = Demande::factory()->for($citoyen)->create(['objet' => 'Nid de poule', 'service_id' => $service->id]);
        $sansService = Demande::factory()->for($citoyen)->create(['objet' => 'Question diverse']);

        $this->actingAs($citoyen)->get(route('contact.confirmation', $avecService))
            ->assertOk()
            ->assertSee('Nid de poule')
            ->assertSee('Voirie test')
            ->assertSee('Conservez cette référence pour suivre votre demande')
            ->assertSee(route('demandes.show', $avecService))
            ->assertSee(route('contact.create'));

        $this->actingAs($citoyen)->get(route('contact.confirmation', $sansService))
            ->assertSee('À orienter par la mairie');
    }

    public function test_sixth_submission_within_a_minute_is_throttled(): void
    {
        $citoyen = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($citoyen)->post('/contact', $this->payload(['objet' => "Demande $i"]))
                ->assertRedirect();
        }

        $this->actingAs($citoyen)->post('/contact', $this->payload(['objet' => 'Demande 6']))
            ->assertStatus(429);

        $this->assertSame(5, Demande::count());
    }
}
