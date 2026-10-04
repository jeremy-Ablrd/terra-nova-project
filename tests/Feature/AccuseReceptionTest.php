<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\Service;
use App\Models\User;
use App\Services\SuppressionCompte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F83 : accusé de réception d'une demande, lisible, imprimable et téléchargeable en .html autonome ;
 * habitant propriétaire seulement (user_id strict) ; ni JSON ni CSV.
 */
class AccuseReceptionTest extends TestCase
{
    use RefreshDatabase;

    private User $camille;

    private Demande $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:30:00', 'Indian/Reunion'));
        $this->camille = User::factory()->create(['name' => 'Camille Habitante']);
        $service = Service::factory()->create(['nom' => 'Voirie et propreté', 'actif' => true]);
        $this->demande = Demande::factory()->create([
            'user_id' => $this->camille->id, 'objet' => 'Lampadaire en panne', 'service_id' => $service->id,
            'message' => 'Message confidentiel de Camille sur son lampadaire.',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-10-12 15:45:00', 'Indian/Reunion'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // --- Page ---

    public function test_the_owner_reads_a_receipt_with_reference_local_reception_time_subject_service_and_status(): void
    {
        $this->actingAs($this->camille)->get(route('demandes.accuse', $this->demande))->assertOk()
            ->assertSee('Accusé de réception')->assertSee($this->demande->reference)
            ->assertSee('10/10/2026 09:30')->assertSee('heure de La Réunion')
            ->assertSee('Lampadaire en panne')->assertSee('Voirie et propreté')
            ->assertSee('Nouvelle')->assertSee('12/10/2026 15:45');
    }

    public function test_the_reception_time_is_the_creation_time_in_the_application_timezone(): void
    {
        // Heure stockée en heure locale : la même demande vue à une autre date garde son heure de réception.
        Carbon::setTestNow(Carbon::parse('2026-11-01 01:00:00', 'Indian/Reunion'));

        $this->actingAs($this->camille)->get(route('demandes.accuse', $this->demande))->assertSee('10/10/2026 09:30')->assertSee('01/11/2026 01:00');
        $this->assertSame('Indian/Reunion', config('app.timezone'));
    }

    public function test_the_status_follows_the_demande_and_a_missing_service_is_written_out(): void
    {
        $this->demande->forceFill(['statut' => Statut::EnCours, 'service_id' => null])->save();

        $this->actingAs($this->camille)->get(route('demandes.accuse', $this->demande))
            ->assertSee('En cours')->assertSee('Un agent s\'en occupe.')->assertSee('À orienter par la mairie');
    }

    public function test_the_receipt_never_contains_the_message_nor_anyone_else(): void
    {
        $autre = Demande::factory()->create(['objet' => 'Objet du voisin']);

        $html = $this->actingAs($this->camille)->get(route('demandes.accuse', $this->demande))->getContent();

        $this->assertStringNotContainsString('Message confidentiel', $html);
        $this->assertStringNotContainsString('Objet du voisin', $html);
        $this->assertStringNotContainsString($autre->reference, $html);
    }

    public function test_the_page_has_one_main_one_h1_a_download_a_print_hint_and_a_way_back(): void
    {
        $html = $this->actingAs($this->camille)->get(route('demandes.accuse', $this->demande))->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, '<main'));
        $this->assertStringContainsString(route('demandes.accuse.telecharger', $this->demande), $html);
        $this->assertStringContainsString('Ctrl + P', $html);
        $this->assertStringContainsString('no-print', $html);
        $this->assertStringContainsString(route('demandes.show', $this->demande), $html);
        $this->assertStringNotContainsString('style="', substr($html, strpos($html, '<article')));
    }

    // --- Téléchargement ---

    public function test_the_download_is_a_standalone_html_file_without_script_or_external_resource(): void
    {
        $reponse = $this->actingAs($this->camille)->get(route('demandes.accuse.telecharger', $this->demande))->assertOk();
        $html = $reponse->getContent();

        $this->assertStringContainsString('text/html', $reponse->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="accuse-de-reception-'.$this->demande->reference.'.html"', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $reponse->headers->get('Cache-Control'));

        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($html));
        $this->assertStringContainsString('<html lang="fr"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        foreach (['<script', '<link', 'src=', '@import', 'http://', 'https://'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $html, $interdit);
        }
        foreach ([$this->demande->reference, '10/10/2026 09:30', 'Lampadaire en panne', 'Voirie et propreté', 'Nouvelle', 'Document remis le 12/10/2026 15:45.'] as $texte) {
            $this->assertStringContainsString($texte, $html);
        }
        $this->assertStringNotContainsString('Message confidentiel', $html);
    }

    public function test_there_is_no_json_or_csv_version(): void
    {
        $this->actingAs($this->camille)->getJson(route('demandes.accuse', $this->demande))->assertOk();
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_contains($route->uri(), 'accuse')) {
                $this->assertDoesNotMatchRegularExpression('/json|csv/i', $route->uri().$route->getName());
            }
        }
        $reponse = $this->actingAs($this->camille)->get(route('demandes.accuse.telecharger', $this->demande));
        $this->assertStringNotContainsString('json', $reponse->headers->get('Content-Type'));
        $this->assertStringNotContainsString('csv', $reponse->headers->get('Content-Disposition'));
    }

    public function test_downloads_are_throttled_like_the_other_documents(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->camille)->get(route('demandes.accuse.telecharger', $this->demande))->assertOk();
        }

        $this->actingAs($this->camille)->get(route('demandes.accuse.telecharger', $this->demande))->assertStatus(429);
    }

    // --- Propriétaire seulement ---

    public function test_only_the_owner_gets_the_receipt_page_and_file(): void
    {
        $voisin = User::factory()->create();
        $importee = Demande::factory()->importee()->create();
        $anonymisee = Demande::factory()->create(['user_id' => $voisin->id]);
        app(SuppressionCompte::class)->supprimer($voisin);

        foreach (['demandes.accuse', 'demandes.accuse.telecharger'] as $nom) {
            $this->get(route($nom, $this->demande))->assertRedirect(route('login'));
        }

        foreach (['demandes.accuse', 'demandes.accuse.telecharger'] as $nom) {
            $this->actingAs(User::factory()->create())->get(route($nom, $this->demande))->assertForbidden();
            $this->actingAs(User::factory()->agent()->create())->get(route($nom, $this->demande))->assertForbidden();
            $this->actingAs(User::factory()->admin()->create())->get(route($nom, $this->demande))->assertForbidden();

            // user_id nul : jamais accessible (demande importée de l'API, demande anonymisée).
            $this->actingAs($this->camille)->get(route($nom, $importee))->assertForbidden();
            $this->actingAs(User::factory()->create())->get(route($nom, $anonymisee->fresh()))->assertForbidden();
        }
    }

    // --- Liens ---

    public function test_the_receipt_is_reachable_from_the_confirmation_and_from_the_request_detail(): void
    {
        $lien = route('demandes.accuse', $this->demande);

        $this->actingAs($this->camille)->get(route('contact.confirmation', $this->demande))->assertSee($lien, false)->assertSee('Accusé de réception');
        $this->actingAs($this->camille)->get(route('demandes.show', $this->demande))->assertSee($lien, false);
    }

    public function test_the_link_is_not_offered_to_an_agent_reading_the_request(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get(route('demandes.show', $this->demande))->assertOk()
            ->assertDontSee(route('demandes.accuse', $this->demande), false);
    }

    public function test_after_sending_the_contact_form_the_confirmation_leads_to_a_readable_receipt(): void
    {
        $service = Service::factory()->create(['actif' => true]);

        $reponse = $this->actingAs($this->camille)->post(route('contact.store'), ['objet' => 'Nouvelle demande de test', 'message' => 'Un message assez long.', 'service_id' => $service->id]);
        $demande = Demande::where('objet', 'Nouvelle demande de test')->firstOrFail();

        $reponse->assertRedirect(route('contact.confirmation', $demande));
        $this->actingAs($this->camille)->get(route('demandes.accuse', $demande))->assertOk()->assertSee($demande->reference)->assertSee('Nouvelle demande de test');
    }
}
