<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Http\Controllers\RecapitulatifDemandesController;
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

    // --- F55 : page et export JSON ---

    public function test_page_explains_what_is_kept_and_shows_my_information_in_plain_labels(): void
    {
        $this->habitant->forceFill(['preferences' => ['taille' => 'grand', 'theme' => 'contraste']])->save();

        $page = $this->actingAs($this->habitant)->get('/mes-donnees')->assertOk()
            ->assertSee('Ce que la ville conserve, et pourquoi')
            ->assertSee('Camille Habitant')->assertSee('camille@example.test')
            ->assertSee('Citoyen')
            ->assertSee('Taille du texte')->assertSee('Grand')
            ->assertSee('Contraste renforcé')
            ->assertSee('Mes demandes par statut')
            ->assertSee('Dernier changement d&#039;état le', false)
            ->assertSee('04/10/2026 14:00')
            ->assertSee(route('mes-donnees.export'))
            ->assertSee(route('demandes.export-csv'))
            ->assertSee(route('demandes.recapitulatif'))
            ->assertSee(route('mes-donnees.suppression'))
            ->assertDontSee('Victor Voisin')
            ->assertDontSee('Agent Secret')
            ->assertDontSee('jeton-secret-camille')
            ->assertDontSee($this->habitant->password)
            ->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
        $this->assertStringContainsString('<caption', $page);
        $this->assertStringContainsString('scope="col"', $page);
        $this->assertStringContainsString('Fil d&#039;Ariane', $page);
    }

    public function test_counts_per_status_are_exact_and_only_mine(): void
    {
        $this->actingAs($this->habitant)->get('/mes-donnees')
            ->assertSeeInOrder(['Nouvelle', '2', 'En cours', '0', 'Traitée', '1', 'Total', '3']);
    }

    public function test_json_export_is_a_documented_valid_download(): void
    {
        Carbon::setTestNow('2026-10-04 15:00:00');
        $reponse = $this->actingAs($this->habitant)->get('/mes-donnees/export.json')->assertOk();

        $this->assertStringContainsString('application/json', $reponse->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('mes-donnees-nova-terra-2026-10-04.json', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $reponse->headers->get('Cache-Control'));

        $brut = $reponse->getContent();
        $this->assertStringContainsString("\n    \"description\"", $brut); // indenté, description en tête
        $this->assertStringContainsString('Éclairage à réparer', $brut); // accents non échappés

        $json = json_decode($brut, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('description', array_key_first($json));
        $this->assertArrayHasKey('objet', $json['description']);
        $this->assertSame(['genere_le', 'compte', 'preferences_affichage', 'demandes_par_statut', 'dernieres_activites', 'demandes'], array_keys($json['description']['champs']));

        $this->assertSame('04/10/2026 15:00', $json['genere_le']);
        $this->assertSame('Camille Habitant', $json['compte']['nom']);
        $this->assertSame('camille@example.test', $json['compte']['adresse_email']);
        $this->assertSame('Citoyen', $json['compte']['role']);
        $this->assertSame(['Nouvelle' => 2, 'En cours' => 0, 'Traitée' => 1, 'Total' => 3], $json['demandes_par_statut']);
        $this->assertSame('04/10/2026 14:00', $json['dernieres_activites']['dernier_changement_d_etat_le']);

        $this->assertCount(3, $json['demandes']);
        $this->assertEqualsCanonicalizing(['=1+1', '@x', 'Éclairage à réparer'], array_column($json['demandes'], 'objet'));

        $premiere = collect($json['demandes'])->firstWhere('reference', $this->d1->reference);
        $this->assertSame('Voirie, propreté', $premiere['service']);
        $this->assertSame('Traitée', $premiere['statut']);
        $this->assertSame(['Nouvelle', 'En cours', 'Traitée'], array_column($premiere['chronologie'], 'statut'));
        $this->assertSame([null, 'Un agent', 'Un agent'], array_column($premiere['chronologie'], 'par'));
        $this->assertSame('04/10/2026 13:00', $premiere['chronologie'][1]['date']);
    }

    public function test_json_export_contains_nothing_secret_and_nothing_from_others(): void
    {
        $brut = $this->actingAs($this->habitant)->get('/mes-donnees/export.json')->getContent();

        foreach ([
            $this->habitant->password, 'jeton-secret-camille', 'remember_token', '"password"',
            'Agent Secret', $this->agent->email,
            'Victor Voisin', 'victor@example.test', 'Demande du voisin', 'Secret de Victor',
            'Demande importée', 'Message importé', 'Marc Importé',
        ] as $interdit) {
            $this->assertStringNotContainsString($interdit, $brut, $interdit);
        }

        // Vérification croisée : l'export du voisin ne contient rien de l'habitant.
        $autre = $this->actingAs($this->voisin)->get('/mes-donnees/export.json')->getContent();
        $this->assertStringContainsString('Demande du voisin', $autre);
        foreach (['Camille Habitant', 'camille@example.test', '=1+1', 'Premier message', 'Éclairage à réparer'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $autre, $interdit);
        }
    }

    // --- F56 : CSV et version imprimable ---

    public function test_csv_has_bom_semicolons_accents_and_only_my_demandes(): void
    {
        $reponse = $this->actingAs($this->habitant)->get('/mes-demandes/export.csv')->assertOk();

        $this->assertStringContainsString('text/csv', $reponse->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('mes-demandes-nova-terra-2026-10-04.csv', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));

        $csv = $reponse->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lignes = array_values(array_filter(preg_split('/\R/', substr($csv, 3))));
        $this->assertSame(['Référence', 'Objet', 'Service', 'Statut', 'Créée le', 'Dernière mise à jour', 'Traitée le'], str_getcsv($lignes[0], ';', '"', ''));
        $this->assertStringStartsWith('Référence;Objet;Service;Statut;', $lignes[0]);
        $this->assertCount(4, $lignes); // un en-tête + mes 3 demandes

        $this->assertStringContainsString('Éclairage à réparer', $csv);
        $this->assertStringContainsString('"Voirie, propreté"', $csv);
        $this->assertStringContainsString('Traitée', $csv);
        $this->assertStringContainsString('01/09/2026 10:00', $csv);
        $this->assertStringContainsString('04/10/2026 14:00', $csv);

        foreach (['Demande du voisin', 'Demande importée', 'Victor', 'Agent Secret', 'Secret de Victor'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $csv, $interdit);
        }
    }

    public function test_csv_neutralises_formula_injection(): void
    {
        $csv = $this->actingAs($this->habitant)->get('/mes-demandes/export.csv')->getContent();

        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString("'@x", $csv);
        $this->assertStringNotContainsString(';=1+1', $csv);
        $this->assertStringNotContainsString(';@x', $csv);

        foreach (['=SOMME(A1)', '+33', '-1', '@cmd', "\tX", "\rX"] as $dangereux) {
            $this->assertSame("'".$dangereux, RecapitulatifDemandesController::cellule($dangereux));
        }
        foreach (['Normal', 'a=b', '04/10/2026 12:00', ''] as $sur) {
            $this->assertSame($sur, RecapitulatifDemandesController::cellule($sur));
        }
    }

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
            ['get', '/mes-donnees'], ['get', '/mes-donnees/export.json'], ['get', '/mes-demandes/export.csv'],
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
            $this->get('/mes-donnees/export.json')->assertOk();
        }
        $this->get('/mes-donnees/export.json')->assertStatus(429);
        $this->get('/mes-demandes/export.csv')->assertStatus(429); // les trois téléchargements partagent la limite de 10 par minute et par compte
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

        $avant = [$compter('/mes-donnees'), $compter('/mes-donnees/export.json'), $compter('/mes-demandes/recapitulatif')];

        Demande::factory()->count(25)->create(['user_id' => $this->habitant->id, 'service_id' => Service::factory()->create()->id]);

        $this->assertSame($avant, [$compter('/mes-donnees'), $compter('/mes-donnees/export.json'), $compter('/mes-demandes/recapitulatif')]);
    }
}
