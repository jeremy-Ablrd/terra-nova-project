<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Models\Demande;
use App\Models\DemandeReponse;
use App\Models\JournalActivite;
use App\Models\User;
use App\Services\RepondreDemande;
use App\Services\SuppressionCompte;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** F84 : réponse directe de l'agent à l'habitant (détail, frise, notification « Compris », journal sans texte). */
class ReponseAgentTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $habitant;

    private Demande $demande;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading();
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00', 'Indian/Reunion'));
        $this->agent = User::factory()->agent()->create(['name' => 'Alex Agent']);
        $this->habitant = User::factory()->create(['name' => 'Camille Habitante']);
        $this->demande = Demande::factory()->create(['user_id' => $this->habitant->id, 'objet' => 'Lampadaire en panne']);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function repondre(string $texte = 'Un technicien passera jeudi matin.', ?Demande $demande = null, ?User $agent = null)
    {
        return $this->actingAs($agent ?? $this->agent)->post(route('agent.demandes.reponse', $demande ?? $this->demande), ['reponse' => $texte]);
    }

    public function test_the_agent_detail_has_a_labelled_response_field(): void
    {
        $html = $this->actingAs($this->agent)->get(route('agent.demandes.show', $this->demande))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<label[^>]*for="reponse"/', $html);
        $this->assertStringContainsString('aria-describedby="reponse_aide"', $html);
        $this->assertStringContainsString('Envoyer la réponse', $html);
        $this->assertStringContainsString('votre nom ne lui est pas communiqué', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_a_response_is_stored_with_the_date_and_the_agent(): void
    {
        $this->repondre()->assertRedirect(route('agent.demandes.show', $this->demande))->assertSessionHas('succes');

        $reponse = DemandeReponse::firstOrFail();
        $this->assertSame($this->demande->id, $reponse->demande_id);
        $this->assertSame($this->agent->id, $reponse->agent_id);
        $this->assertSame('Alex Agent', $reponse->agent_nom);
        $this->assertSame('Un technicien passera jeudi matin.', $reponse->texte);
        $this->assertSame('2026-10-12 10:00:00', $reponse->created_at->format('Y-m-d H:i:s'));
        $this->assertNull($reponse->vu_at);
    }

    public function test_the_journal_says_response_sent_without_the_text(): void
    {
        $this->repondre('Réponse très confidentielle pour Camille.');

        $entree = JournalActivite::where('action', ActionJournal::ReponseEnvoyee->value)->firstOrFail();
        $this->assertSame('Réponse envoyée', $entree->action->label());
        $this->assertSame($this->agent->id, $entree->acteur_id);
        $this->assertSame($this->demande->reference, $entree->objet_libelle);
        $tout = json_encode($entree->getAttributes(), JSON_UNESCAPED_UNICODE);
        foreach (['confidentielle', 'Camille', 'Lampadaire'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $tout);
        }
    }

    public function test_if_the_journal_fails_no_response_is_stored(): void
    {
        JournalActivite::creating(fn () => throw new RuntimeException('journal indisponible'));

        try {
            app(RepondreDemande::class)->envoyer($this->demande, $this->agent, 'Une réponse.');
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException $e) {
            $this->assertSame('journal indisponible', $e->getMessage());
        }

        $this->assertSame(0, DemandeReponse::count());
    }

    public function test_the_citizen_reads_the_response_in_the_detail_and_in_the_timeline_without_the_agent_name(): void
    {
        $this->repondre();

        $html = $this->actingAs($this->habitant)->get(route('demandes.show', $this->demande))->assertOk()->getContent();

        $this->assertStringContainsString('Réponse de la mairie', $html);
        $this->assertStringContainsString('Un technicien passera jeudi matin.', $html);
        $this->assertStringContainsString('12/10/2026 10:00', $html);
        $this->assertStringContainsString('<span aria-hidden="true">✉</span>Réponse de la mairie', $html);   // dans la frise, en texte
        $this->assertStringNotContainsString('Alex Agent', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="step"'), 'l\'étape actuelle ne change pas');
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_the_agent_sees_the_response_and_its_author_in_the_timeline(): void
    {
        $this->repondre();

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $this->demande))
            ->assertSee('Réponse de la mairie')->assertSee('par Alex Agent')->assertSee('Un technicien passera jeudi matin.');
    }

    public function test_the_response_triggers_the_existing_got_it_notice_until_it_is_acknowledged(): void
    {
        $this->repondre();
        $reponse = DemandeReponse::firstOrFail();

        $page = $this->actingAs($this->habitant)->get(route('dashboard'))
            ->assertSee('La mairie a répondu à votre demande '.$this->demande->reference.'.')
            ->assertSee('role="status"', false)->assertSee('Compris')->assertSee(route('demandes.reponses.vu', $reponse), false);
        $this->actingAs($this->habitant)->get(route('demandes.index'))->assertSee('La mairie a répondu à votre demande');

        $this->actingAs($this->habitant)->post(route('demandes.reponses.vu', $reponse))->assertRedirect();
        $this->assertNotNull($reponse->fresh()->vu_at);
        $this->actingAs($this->habitant)->get(route('dashboard'))->assertDontSee('La mairie a répondu à votre demande');
        $this->assertNotNull($page);
    }

    public function test_opening_the_request_counts_as_reading_the_response(): void
    {
        $this->repondre();

        $this->actingAs($this->habitant)->get(route('demandes.show', $this->demande))->assertOk();

        $this->assertNotNull(DemandeReponse::firstOrFail()->vu_at);
        $this->actingAs($this->habitant)->get(route('dashboard'))->assertDontSee('La mairie a répondu');
    }

    public function test_the_notice_and_the_acknowledgement_belong_to_the_owner_only(): void
    {
        $this->repondre();
        $reponse = DemandeReponse::firstOrFail();
        $voisin = User::factory()->create();

        $this->actingAs($voisin)->get(route('dashboard'))->assertDontSee('La mairie a répondu');
        $this->actingAs($voisin)->post(route('demandes.reponses.vu', $reponse))->assertForbidden();
        $this->actingAs($this->agent)->post(route('demandes.reponses.vu', $reponse))->assertForbidden();
        $this->assertNull($reponse->fresh()->vu_at);
    }

    public function test_several_responses_are_all_kept_in_order(): void
    {
        $this->repondre('Première réponse.');
        Carbon::setTestNow(Carbon::parse('2026-10-13 09:00', 'Indian/Reunion'));
        $this->repondre('Seconde réponse.');

        $this->actingAs($this->habitant)->get(route('demandes.show', $this->demande))->assertSeeInOrder(['Première réponse.', 'Seconde réponse.']);
        $this->assertSame(2, DemandeReponse::count());
    }

    public function test_an_imported_request_has_no_recipient_so_no_response_can_be_sent(): void
    {
        $importee = Demande::factory()->importee()->create();

        $this->actingAs($this->agent)->get(route('agent.demandes.show', $importee))->assertOk()
            ->assertSee('n\'a pas de compte habitant destinataire')->assertDontSee('Envoyer la réponse');
        $this->repondre('Réponse sans destinataire.', $importee)->assertForbidden();

        $this->assertSame(0, DemandeReponse::count());
        $this->assertSame(0, JournalActivite::where('action', 'reponse_envoyee')->count());
    }

    public function test_the_service_also_refuses_a_request_without_recipient(): void
    {
        $importee = Demande::factory()->importee()->create();

        $this->expectException(\App\Services\TransitionRefusee::class);
        app(RepondreDemande::class)->envoyer($importee, $this->agent, 'Une réponse.');
    }

    public function test_validation(): void
    {
        $this->repondre('')->assertSessionHasErrors('reponse');
        $this->repondre('abc')->assertSessionHasErrors('reponse');
        $this->repondre(str_repeat('a', 2001))->assertSessionHasErrors('reponse');
        $this->actingAs($this->agent)->post(route('agent.demandes.reponse', $this->demande), ['reponse' => ['x']])->assertSessionHasErrors('reponse');
        $this->assertSame(0, DemandeReponse::count());

        $this->repondre(str_repeat('a', 2000))->assertSessionHasNoErrors();
        $this->assertSame(1, DemandeReponse::count());
    }

    public function test_the_text_is_escaped_for_the_citizen_and_the_agent(): void
    {
        $this->repondre('Voir <script>alert(1)</script> et <b>gras</b>.');

        foreach ([[$this->habitant, route('demandes.show', $this->demande)], [$this->agent, route('agent.demandes.show', $this->demande)]] as [$user, $url]) {
            $html = $this->actingAs($user)->get($url)->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringContainsString('&lt;b&gt;gras&lt;/b&gt;', $html);
        }
    }

    public function test_only_the_agent_can_send_a_response(): void
    {
        $this->post(route('agent.demandes.reponse', $this->demande), ['reponse' => 'Une réponse.'])->assertRedirect(route('login'));
        foreach ([User::factory()->create(), $this->habitant, User::factory()->admin()->create()] as $autre) {
            $this->actingAs($autre)->post(route('agent.demandes.reponse', $this->demande), ['reponse' => 'Une réponse.'])->assertForbidden();
        }
        $this->assertSame(0, DemandeReponse::count());
    }

    public function test_deleting_the_account_replaces_the_text_of_the_responses_but_keeps_date_and_agent(): void
    {
        $this->repondre('Camille, votre lampadaire est réparé.');
        $autre = Demande::factory()->create();
        $this->repondre('Réponse à un autre habitant.', $autre);

        app(SuppressionCompte::class)->supprimer($this->habitant);

        $reponse = DemandeReponse::where('demande_id', $this->demande->id)->firstOrFail();
        $this->assertSame(SuppressionCompte::TEXTE_SUPPRIME, $reponse->texte);
        $this->assertSame($this->agent->id, $reponse->agent_id);
        $this->assertNotNull($reponse->created_at);
        $this->assertSame('Réponse à un autre habitant.', DemandeReponse::where('demande_id', $autre->id)->value('texte'));
        $this->assertStringNotContainsString('Camille', DemandeReponse::pluck('texte')->implode(' '));
    }

    public function test_the_nav_counter_includes_unseen_responses_and_no_extra_step_query(): void
    {
        $this->repondre();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->habitant)->get(route('dashboard'))->assertOk();
        $reponses = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'demande_reponses'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $reponses, 'une seule requête sur les réponses par page citoyen');
        // Aucune requête sur les réponses pour un agent.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->agent)->get(route('agent.index'))->assertOk();
        $this->assertSame(0, collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'demande_reponses'))->count());
        DB::disableQueryLog();
    }

    public function test_my_dossier_lists_the_responses_received_with_date_reference_and_text(): void
    {
        $this->repondre('Un technicien passera jeudi matin.');
        Carbon::setTestNow(Carbon::parse('2026-10-13 09:00', 'Indian/Reunion'));
        $this->repondre('C\'est réparé.');
        $voisin = Demande::factory()->create();
        $this->repondre('Réponse destinée à un autre habitant.', $voisin);

        $html = $this->actingAs($this->habitant)->get(route('mes-donnees.dossier'))->assertOk()->getContent();

        $this->assertStringContainsString('Les réponses de la mairie', $html);
        $this->assertStringContainsString('Vous avez reçu 2 réponses directes de la mairie.', $html);
        $this->assertStringContainsString('Demande '.$this->demande->reference.', le 12/10/2026 10:00', $html);
        $this->assertStringContainsString('Demande '.$this->demande->reference.', le 13/10/2026 09:00', $html);
        $this->assertStringContainsString('Un technicien passera jeudi matin.', $html);
        $this->assertStringContainsString('C&#039;est réparé.', $html);
        $this->assertStringNotContainsString('Réponse destinée à un autre habitant.', $html);
        $this->assertStringNotContainsString($voisin->reference, $html);
        $this->assertStringNotContainsString('Alex Agent', $html);   // jamais le nom de l'agent
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertTrue(strpos($html, 'Un technicien') < strpos($html, 'C&#039;est réparé.'));
    }

    public function test_the_dossier_says_so_without_response_and_the_download_has_them_too(): void
    {
        $this->actingAs($this->habitant)->get(route('mes-donnees.dossier'))->assertSee('Vous n\'avez reçu aucune réponse directe de la mairie.');

        $this->repondre('Une réponse à garder dans le dossier.');
        $fichier = $this->actingAs($this->habitant)->get(route('mes-donnees.dossier.telecharger'))->assertOk();

        $fichier->assertSee('Une réponse à garder dans le dossier.')->assertSee($this->demande->reference);
        $this->assertStringNotContainsString('<script', $fichier->getContent());
        $this->assertStringContainsString('no-store', $fichier->headers->get('Cache-Control'));
    }

    public function test_the_conservation_table_mentions_the_responses_and_the_dossier_stays_constant_in_queries(): void
    {
        $this->actingAs($this->habitant)->get(route('mes-donnees.dossier'))->assertSee('Les réponses de la mairie à vos demandes');

        $mesure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->habitant)->get(route('mes-donnees.dossier'))->assertOk();

            return count(DB::getQueryLog());
        };
        $this->repondre('Réponse numéro un.');
        $une = $mesure();
        foreach (range(2, 6) as $i) {
            $this->repondre("Réponse numéro $i.");
        }

        $this->assertSame($une, $mesure());
    }}
