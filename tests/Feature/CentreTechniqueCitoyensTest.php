<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Enums\TypeDemande;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le Centre technique municipal ne montre que les demandes citoyennes. Des lignes institution/alerte
 * (importées avant le retour à l'import « Citoyen » seul) peuvent rester en base : elles restent masquées.
 */
class CentreTechniqueCitoyensTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create();

        Demande::factory()->statut(Statut::Nouvelle)->create(['objet' => 'Demande citoyenne un']);
        Demande::factory()->importee('F21')->statut(Statut::EnCours)->create(['objet' => 'Demande citoyenne importée']);
        Demande::factory()->importee('D01')->type(TypeDemande::Institution)->statut(Statut::Nouvelle)->create(['objet' => 'Demande institution masquée']);
        Demande::factory()->importee('F27')->type(TypeDemande::Alerte)->statut(Statut::Nouvelle)->create(['objet' => 'Demande alerte masquée']);
    }

    public function test_hidden_types_are_not_listed_and_not_counted(): void
    {
        $response = $this->actingAs($this->agent)->get('/agent/demandes')
            ->assertOk()
            ->assertSee('Demande citoyenne un')
            ->assertSee('Demande citoyenne importée')
            ->assertDontSee('Demande institution masquée')
            ->assertDontSee('Demande alerte masquée')
            ->assertSeeInOrder(['Toutes', '(2)', 'Nouvelle', '(1)', 'En cours', '(1)', 'Traitée', '(0)']);

        $this->assertSame(2, substr_count($response->getContent(), '<time datetime='));
    }

    public function test_there_is_no_type_filter_and_a_type_parameter_changes_nothing(): void
    {
        $this->actingAs($this->agent)->get('/agent/demandes')
            ->assertDontSee('Filtrer par type')
            ->assertDontSee('Tous les types');

        foreach (['?type=institution', '?type=alerte', '?type[]=institution&type[]=alerte'] as $query) {
            $this->actingAs($this->agent)->get('/agent/demandes'.$query)
                ->assertOk()
                ->assertSee('Demande citoyenne un')
                ->assertDontSee('Demande institution masquée')
                ->assertDontSee('Demande alerte masquée');
        }
    }

    public function test_pending_counter_ignores_hidden_types(): void
    {
        $this->assertSame(1, Demande::enAttente()->count());

        $this->actingAs($this->agent)->get('/agent')->assertSee('1 demande en attente');
        $this->actingAs($this->agent)->get('/agent/demandes')->assertSee('1 demande en attente');
    }

    public function test_agent_cannot_open_a_hidden_demande_but_can_open_a_citizen_one(): void
    {
        $hidden = Demande::where('request_code', 'D01')->firstOrFail();
        $citizen = Demande::where('request_code', 'F21')->firstOrFail();

        $this->actingAs($this->agent)->get(route('demandes.show', $hidden))->assertForbidden();
        $this->actingAs($this->agent)->get(route('demandes.show', $citizen))->assertOk();
    }

    public function test_hidden_types_stay_hidden_from_citizens_and_admins(): void
    {
        $hidden = Demande::where('request_code', 'F27')->firstOrFail();

        $this->actingAs(User::factory()->create())->get('/mes-demandes')->assertDontSee('Demande alerte masquée');
        $this->actingAs(User::factory()->create())->get(route('demandes.show', $hidden))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('demandes.show', $hidden))->assertForbidden();
    }
}
