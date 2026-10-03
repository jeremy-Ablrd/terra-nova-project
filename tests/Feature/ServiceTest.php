<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_demande_relations(): void
    {
        $service = Service::factory()->create();
        $agent = User::factory()->agent()->create();
        $demande = Demande::factory()->create(['service_id' => $service->id, 'agent_id' => $agent->id]);

        $this->assertTrue($demande->service->is($service));
        $this->assertTrue($demande->agent->is($agent));
        $this->assertNotNull($demande->user);
        $this->assertTrue($service->demandes->first()->is($demande));
    }

    public function test_service_and_agent_are_nullable_and_nulled_on_delete(): void
    {
        $service = Service::factory()->create();
        $agent = User::factory()->agent()->create();
        $demande = Demande::factory()->create(['service_id' => $service->id, 'agent_id' => $agent->id]);

        $service->delete();
        $agent->delete();
        $demande->refresh();

        $this->assertNull($demande->service_id);
        $this->assertNull($demande->agent_id);
        $this->assertNull(Demande::factory()->create()->traitee_at);
    }

    public function test_server_side_fields_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();
        $pirate = User::factory()->create();
        $service = Service::factory()->create();

        $demande = $user->demandes()->create([
            'objet' => 'X',
            'message' => 'x',
            'service_id' => $service->id,
            'user_id' => $pirate->id,
            'agent_id' => $pirate->id,
            'statut' => Statut::Traitee,
            'traitee_at' => now(),
        ])->fresh();

        $this->assertSame($user->id, $demande->user_id);
        $this->assertSame($service->id, $demande->service_id);
        $this->assertNull($demande->agent_id);
        $this->assertNull($demande->traitee_at);
        $this->assertSame(Statut::Nouvelle, $demande->statut);
    }

    public function test_service_seeder_creates_eight_unique_services_and_is_rerunnable(): void
    {
        $this->seed(ServiceSeeder::class);
        $this->seed(ServiceSeeder::class);

        $this->assertSame(8, Service::count());
        $this->assertSame(8, Service::pluck('slug')->unique()->count());
    }
}
