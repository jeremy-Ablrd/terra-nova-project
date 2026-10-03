<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\Service;
use App\Models\User;
use App\Services\TransitionDemande;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** F55 (mes informations), F56 (récapitulatif de mes demandes) : pages et téléchargements, citoyen seulement. */
class MesDonneesTest extends TestCase
{
    use RefreshDatabase;

    private User $habitant;

    private User $voisin;

    private User $agent;

    private Demande $d1;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 12:00:00');
        Model::preventLazyLoading();

        $this->habitant = User::factory()->create(['name' => 'Camille Habitant', 'email' => 'camille@example.test', 'remember_token' => 'jeton-secret-camille']);
        $this->voisin = User::factory()->create(['name' => 'Victor Voisin', 'email' => 'victor@example.test']);
        $this->agent = User::factory()->agent()->create(['name' => 'Agent Secret']);

        $service = Service::factory()->create(['nom' => 'Voirie, propreté']);
        $this->d1 = $this->demande($this->habitant, '=1+1', 'Premier message', $service);
        $this->demande($this->habitant, '@x', 'Deuxième message');
        $this->demande($this->habitant, 'Éclairage à réparer', 'Troisième message');
        $this->demande($this->voisin, 'Demande du voisin', 'Secret de Victor');
        Demande::factory()->importee('F21', 'Marc Importé')->create(['objet' => 'Demande importée', 'message' => 'Message importé']);

        $transition = app(TransitionDemande::class);
        Carbon::setTestNow('2026-10-04 13:00:00');
        $transition->passer($this->d1, Statut::Nouvelle, $this->agent);
        Carbon::setTestNow('2026-10-04 14:00:00');
        $transition->passer($this->d1, Statut::EnCours, $this->agent);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    private function demande(User $user, string $objet, string $message, ?Service $service = null): Demande
    {
        return Demande::factory()->create([
            'user_id' => $user->id, 'objet' => $objet, 'message' => $message, 'service_id' => $service?->id,
            'created_at' => '2026-09-01 10:00:00', 'updated_at' => '2026-09-01 10:00:00',
        ])->refresh();
    }

    // --- F56 : CSV et version imprimable ---

    public function test_printable_summary_is_simple_html_with_ctrl_p_hint(): void
    {
        $page = $this->actingAs($this->habitant)->get('/mes-demandes/recapitulatif')->assertOk()
            ->assertSee('Récapitulatif de mes demandes')
            ->assertSee($this->d1->reference)
            ->assertSee('Éclairage à réparer')
            ->assertSee('Ctrl + P')
            ->assertSee('Imprimer')
            ->assertSee('<caption', false)
            ->assertSee('no-print', false)
            ->assertDontSee('Demande du voisin')
            ->assertDontSee('Demande importée')
            ->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
        $this->assertMatchesRegularExpression('/<button[^>]*\bhidden\b[^>]*>\s*Imprimer/', $page); // visible seulement avec JavaScript
        $this->assertStringContainsString('@media print', file_get_contents(resource_path('css/app.css')));
    }

    public function test_printable_summary_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get('/mes-demandes/recapitulatif')->assertOk()
            ->assertSee('Vous n\'avez encore aucune demande', false);
    }

    // --- Navigation ---

    public function test_my_data_link_is_in_the_citizen_navigation_and_space_only(): void
    {
        $url = route('mes-donnees.index');

        $this->actingAs($this->habitant)->get('/espace')->assertSee($url);
        $this->actingAs($this->habitant)->get('/mes-demandes')->assertSee($url);

        foreach ([$this->agent, User::factory()->admin()->create()] as $autre) {
            foreach (['/espace', '/profile'] as $page) {
                $this->actingAs($autre)->get($page)->assertOk()->assertDontSee($url)->assertDontSee('Supprimer mon compte');
            }
        }
        $this->actingAs($this->agent)->get('/agent')->assertDontSee($url);
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertDontSee($url);
    }

    public function test_profile_page_has_one_link_to_the_deletion_path_and_no_modal_form(): void
    {
        $this->actingAs($this->habitant)->get('/profile')->assertOk()
            ->assertSee(route('mes-donnees.suppression'))
            ->assertDontSee('confirm-user-deletion');
    }

    // --- Accès : 403 pour agent et admin, redirection pour l'invité, limites ---

    public function test_agent_and_admin_get_403_and_guest_is_redirected_on_every_route(): void
    {
        $routes = [
            ['get', '/mes-donnees'], ['get', '/mes-donnees/dossier'], ['get', '/mes-donnees/dossier/telecharger'], ['get', '/mes-demandes/recapitulatif/telecharger'],
            ['get', '/mes-demandes/recapitulatif'], ['get', '/mes-donnees/suppression'], ['get', '/mes-donnees/suppression/confirmer'],
            ['delete', '/mes-donnees/suppression'],
        ];

        foreach ([$this->agent, User::factory()->admin()->create()] as $utilisateur) {
            foreach ($routes as [$methode, $url]) {
                $this->actingAs($utilisateur)->{$methode}($url)->assertForbidden();
            }
        }
        $this->assertNotNull($this->agent->fresh());

        auth()->logout();
        foreach ($routes as [$methode, $url]) {
            $this->{$methode}($url)->assertRedirect(route('login'));
        }
    }

    public function test_downloads_are_throttled(): void
    {
        $this->actingAs($this->habitant);

        foreach (range(1, 10) as $i) {
            $this->get('/mes-donnees/dossier/telecharger')->assertOk();
        }
        $this->get('/mes-donnees/dossier/telecharger')->assertStatus(429);
        $this->get('/mes-demandes/recapitulatif/telecharger')->assertStatus(429); // les trois téléchargements partagent la limite de 10 par minute et par compte
    }

    public function test_exports_run_a_constant_number_of_queries(): void
    {
        $compter = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->habitant)->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $avant = [$compter('/mes-donnees'), $compter('/mes-donnees/dossier/telecharger'), $compter('/mes-demandes/recapitulatif')];

        Demande::factory()->count(25)->create(['user_id' => $this->habitant->id, 'service_id' => Service::factory()->create()->id]);

        $this->assertSame($avant, [$compter('/mes-donnees'), $compter('/mes-donnees/dossier/telecharger'), $compter('/mes-demandes/recapitulatif')]);
    }
}
