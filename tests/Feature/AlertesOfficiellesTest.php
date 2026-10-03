<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\Emetteur;
use App\Models\Alerte;
use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** F73 : message officiel du Haut Conseil visible par tous immédiatement, avec son émetteur identifié en texte. */
class AlertesOfficiellesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 12:00:00');
        Model::preventLazyLoading();
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    private function publier(array $surcharge = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.alertes.store'), array_merge([
            'titre' => 'Annonce du Haut Conseil',
            'ce_qui_se_passe' => 'Un message officiel est publié.',
            'ce_quil_faut_faire' => 'Lisez-le et suivez les consignes.',
            'niveau' => 'info',
        ], $surcharge));
    }

    // --- Émetteur : liste fermée, phrase complète par clé ---

    public function test_each_emitter_has_its_own_complete_sentence_visible_to_a_guest(): void
    {
        $phrases = [
            'haut_conseil' => 'Message officiel du Haut Conseil',
            'ville' => 'Message officiel de la Ville de Nova Terra',
            'service_communication' => 'Message officiel du service communication',
        ];

        foreach ($phrases as $cle => $phrase) {
            Alerte::query()->delete();
            Alerte::factory()->create(['emetteur' => $cle, 'titre' => 'Annonce '.$cle]);

            // Invité non connecté : bandeau de l'accueil et d'une page publique, pages /alertes et détail.
            foreach (['/', '/services', '/alertes'] as $url) {
                $this->get($url)->assertOk()->assertSee($phrase);
            }
            $this->get(route('alertes.show', Alerte::firstOrFail()))->assertOk()->assertSee($phrase);
            $this->assertSame($phrase, Emetteur::from($cle)->phrase());
        }
    }

    public function test_sentences_are_whole_translated_strings_not_interpolated(): void
    {
        $this->assertSame(['haut_conseil', 'ville', 'service_communication'], array_map(fn ($e) => $e->value, Emetteur::cases()));
        $this->assertSame(['Haut Conseil', 'Ville de Nova Terra', 'Service communication'], array_map(fn ($e) => $e->label(), Emetteur::cases()));

        foreach (Emetteur::cases() as $e) {
            $this->assertStringStartsWith('Message officiel ', $e->phrase());
            $this->assertStringNotContainsString(':', $e->phrase()); // aucun trou à remplir
        }
    }

    public function test_existing_alerts_default_to_the_city(): void
    {
        // Une ligne créée comme avant la migration (sans émetteur) prend la valeur par défaut « ville ».
        DB::table('alertes')->insert([
            'titre' => 'Ancienne alerte', 'ce_qui_se_passe' => 'x', 'ce_quil_faut_faire' => 'y', 'niveau' => 'info',
            'starts_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(Emetteur::Ville, Alerte::firstOrFail()->emetteur);
        $this->get('/')->assertSee('Message officiel de la Ville de Nova Terra');
    }

    public function test_the_admin_form_has_a_labelled_select_with_the_high_council_preselected(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.alertes.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<label[^>]*for="emetteur"[^>]*>\s*Émetteur du message officiel \*/u', $page);
        $this->assertStringContainsString('<select id="emetteur" name="emetteur"', $page);
        $this->assertStringContainsString('<option value="haut_conseil" selected>Haut Conseil</option>', $page);
        $this->assertStringContainsString('<option value="ville" >Ville de Nova Terra</option>', $page);
        $this->assertStringContainsString('<option value="service_communication" >Service communication</option>', $page);
    }

    public function test_publishing_stores_the_key_and_refuses_anything_else(): void
    {
        $this->publier(['emetteur' => 'haut_conseil'])->assertSessionHasNoErrors();
        $this->assertSame(Emetteur::HautConseil, Alerte::latest('id')->first()->emetteur);

        $this->publier(['titre' => 'Texte libre', 'emetteur' => 'Le grand chef'])->assertSessionHasErrors('emetteur');
        $this->publier(['titre' => 'Tableau', 'emetteur' => ['haut_conseil']])->assertSessionHasErrors('emetteur');
        $this->assertSame(1, Alerte::count());

        // Absent (ancien appel) : la ville.
        $this->publier(['titre' => 'Sans émetteur'])->assertSessionHasNoErrors();
        $this->assertSame(Emetteur::Ville, Alerte::latest('id')->first()->emetteur);
    }

    public function test_the_journal_entry_is_still_written_without_personal_data(): void
    {
        $this->publier(['emetteur' => 'haut_conseil']);

        $entree = JournalActivite::firstOrFail();
        $this->assertSame(ActionJournal::AlertePubliee, $entree->action);
        $this->assertSame('niveau : Information', $entree->detail);
        $this->assertStringNotContainsString($this->admin->email, $entree->detail.$entree->objet_libelle);
    }

    public function test_only_the_admin_publishes(): void
    {
        foreach ([User::factory()->agent()->create(), User::factory()->create()] as $autre) {
            $this->actingAs($autre)->post(route('admin.alertes.store'), ['titre' => 'X', 'niveau' => 'info', 'emetteur' => 'haut_conseil'])->assertForbidden();
        }
        $this->assertSame(0, Alerte::count());
    }

    // --- Immédiateté ---

    public function test_a_published_message_is_visible_to_a_guest_immediately_and_nothing_is_cached(): void
    {
        $this->get('/')->assertDontSee('Annonce du Haut Conseil');

        $this->publier(['emetteur' => 'haut_conseil', 'niveau' => 'urgent']);

        auth()->logout();
        $reponse = $this->get('/')->assertOk();
        $reponse->assertSee('Annonce du Haut Conseil')->assertSee('Message officiel du Haut Conseil');

        $cache = $reponse->headers->get('Cache-Control');
        $this->assertStringNotContainsString('public', $cache);
        $this->assertStringNotContainsString('max-age=', str_replace('max-age=0', '', $cache));

        // Fin de validité : « Terminer maintenant » la retire aussitôt.
        $this->actingAs($this->admin)->post(route('admin.alertes.terminer', Alerte::firstOrFail()));
        auth()->logout();
        $this->get('/')->assertDontSee('Annonce du Haut Conseil');
    }

    // --- Bandeau : toutes les alertes urgentes, en plus de 3 autres au maximum ---

    public function test_all_urgent_alerts_exceed_the_limit_of_three_in_the_banner(): void
    {
        foreach (range(1, 5) as $i) {
            Alerte::factory()->urgente()->create(['titre' => "Urgence {$i}", 'starts_at' => now()->subMinutes($i)]);
        }
        foreach (range(1, 4) as $i) {
            Alerte::factory()->create(['titre' => "Info {$i}", 'starts_at' => now()->subHours($i)]);
        }

        $page = $this->get('/')->assertOk()->getContent();
        preg_match('~<aside.*?</aside>~s', $page, $m);
        $bandeau = $m[0];

        $this->assertSame(8, substr_count($bandeau, '<section role=')); // 5 urgentes + 3 autres
        foreach (range(1, 5) as $i) {
            $this->assertStringContainsString("Urgence {$i}", $bandeau);
        }
        $this->assertStringContainsString('Info 1', $bandeau);
        $this->assertStringContainsString('Info 3', $bandeau);
        $this->assertStringNotContainsString('Info 4', $bandeau);
        $this->assertStringContainsString('Voir toutes les alertes (9)', $bandeau);
        $this->assertSame(5, substr_count($bandeau, 'role="alert"'));
    }

    public function test_the_banner_without_excess_has_no_see_all_link(): void
    {
        Alerte::factory()->urgente()->create();
        Alerte::factory()->count(3)->create();

        $this->get('/')->assertDontSee('Voir toutes les alertes');
    }

    // --- Requêtes ---

    public function test_pages_do_not_lazy_load_and_the_banner_stays_one_query(): void
    {
        Alerte::factory()->count(6)->urgente()->create();
        Alerte::factory()->count(6)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $n = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "alertes"') || str_contains($q['query'], 'from `alertes`'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $n);
    }
}
