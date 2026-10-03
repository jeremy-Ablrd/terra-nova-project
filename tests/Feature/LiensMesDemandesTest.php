<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Aucun lien vers /mes-demandes (réservé au citoyen) pour l'agent ni l'admin. */
class LiensMesDemandesTest extends TestCase
{
    use RefreshDatabase;

    private function lienListe(): string
    {
        return 'href="'.route('demandes.index').'"';
    }

    public function test_agent_and_admin_do_not_see_the_my_demandes_block_on_their_space(): void
    {
        foreach ([User::factory()->agent()->create(), User::factory()->admin()->create()] as $utilisateur) {
            $this->actingAs($utilisateur)->get('/espace')->assertOk()
                ->assertDontSee('Mes demandes')
                ->assertDontSee('Voir tout')
                ->assertDontSee($this->lienListe(), false)
                ->assertSee('Mes informations')
                ->assertSee('Bienvenue');
        }
    }

    public function test_citizen_sees_the_my_demandes_block_on_his_space(): void
    {
        $this->actingAs(User::factory()->create())->get('/espace')->assertOk()
            ->assertSee('Mes demandes')
            ->assertSee('Voir tout')
            ->assertSee($this->lienListe(), false);
    }

    public function test_agent_detail_links_back_to_the_technical_center_not_to_my_demandes(): void
    {
        $demande = Demande::factory()->create()->refresh();

        $page = $this->actingAs(User::factory()->agent()->create())->get(route('demandes.show', $demande))->assertOk()
            ->assertSee('Retour au Centre technique municipal')
            ->assertSee('href="'.route('agent.demandes.index').'"', false)
            ->assertDontSee('Retour à mes demandes')
            ->assertDontSee($this->lienListe(), false)
            ->getContent();

        // Fil d'Ariane de l'agent : Accueil > Espace agent > Centre technique municipal > référence.
        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertMatchesRegularExpression('/Espace agent<\/a>.*Centre technique municipal<\/a>.*'.preg_quote($demande->reference, '/').'/s', $page);
    }

    public function test_owner_detail_keeps_the_back_link_and_breadcrumb_to_my_demandes(): void
    {
        $citoyen = User::factory()->create();
        $demande = Demande::factory()->for($citoyen)->create()->refresh();

        $page = $this->actingAs($citoyen)->get(route('demandes.show', $demande))->assertOk()
            ->assertSee('Retour à mes demandes')
            ->assertSee($this->lienListe(), false)
            ->assertDontSee('Retour au Centre technique municipal')
            ->getContent();

        $this->assertMatchesRegularExpression('/Mon espace<\/a>.*Mes demandes<\/a>.*'.preg_quote($demande->reference, '/').'/s', $page);
    }
}
