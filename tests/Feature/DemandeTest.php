<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demande_gets_nouvelle_status_and_reference(): void
    {
        $user = User::factory()->create();

        $demande = $user->demandes()->create(['objet' => 'Lampadaire cassé', 'message' => 'Rue des Lilas.']);
        $demande->refresh();

        $this->assertSame(Statut::Nouvelle, $demande->statut);
        $this->assertSame(sprintf('NT-%s-%05d', now()->year, $demande->id), $demande->reference);
    }

    public function test_references_are_unique_and_status_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $a = $user->demandes()->create(['objet' => 'A', 'message' => 'a', 'statut' => 'traitee']);
        $b = $user->demandes()->create(['objet' => 'B', 'message' => 'b']);

        $this->assertNotSame($a->fresh()->reference, $b->fresh()->reference);
        $this->assertSame(Statut::Nouvelle, $a->fresh()->statut);
    }

    public function test_user_only_gets_own_demandes(): void
    {
        $user = User::factory()->create();
        Demande::factory()->for($user)->count(2)->create();
        Demande::factory()->create();

        $this->assertCount(2, $user->demandes);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/mes-demandes')->assertRedirect('/login');
    }

    public function test_citizen_sees_only_own_demandes(): void
    {
        $user = User::factory()->create();
        $mine = Demande::factory()->for($user)->create(['objet' => 'Ma demande']);
        $other = Demande::factory()->create(['objet' => 'Demande du voisin']);

        $this->actingAs($user)->get('/mes-demandes')
            ->assertOk()
            ->assertSee('Ma demande')
            ->assertSee($mine->fresh()->reference)
            ->assertDontSee('Demande du voisin');
    }

    public function test_empty_state_is_shown_without_demandes(): void
    {
        $this->actingAs(User::factory()->create())->get('/mes-demandes')
            ->assertOk()
            ->assertSee("Vous n'avez encore aucune demande", false);
    }

    public function test_citizen_can_open_own_demande_with_status_badge(): void
    {
        $user = User::factory()->create();
        $demande = Demande::factory()->for($user)->statut(Statut::EnCours)->create(['message' => 'Détails ici']);

        $this->actingAs($user)->get(route('demandes.show', $demande))
            ->assertOk()
            ->assertSee('Détails ici')
            ->assertSee('En cours');
    }

    public function test_citizen_cannot_open_someone_elses_demande(): void
    {
        $demande = Demande::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('demandes.show', $demande))
            ->assertForbidden();
    }

    public function test_dashboard_summarizes_demandes_and_links_to_list(): void
    {
        $user = User::factory()->create();
        Demande::factory()->for($user)->create(['objet' => 'Fuite d\'eau']);

        $this->actingAs($user)->get('/espace')
            ->assertOk()
            ->assertSee('Fuite d&#039;eau', false)
            ->assertSee(route('demandes.index'));
    }
}
