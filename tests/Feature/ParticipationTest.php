<?php

namespace Tests\Feature;

use App\Enums\StatutContribution;
use App\Enums\TypeContribution;
use App\Models\Contribution;
use App\Models\Projet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Participation (F65, F66, F67, F68, F76) côté habitant : projets publics, avis (ce n'est pas un vote), idées,
 * commentaires sur un service, « Mes contributions », suivi. Un seul système, une seule référence PA-…
 */
class ParticipationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading();
        RateLimiter::clear('contrib|'.'127.0.0.1');
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function etape(Contribution $c, StatutContribution $statut, $date): void
    {
        $e = new \App\Models\ContributionEtape;
        $e->contribution_id = $c->id;
        $e->statut = $statut;
        $e->created_at = $date;
        $e->save();
    }

    private function projetOuvert(array $attributs = []): Projet
    {
        return Projet::factory()->create($attributs);
    }

    // --- Projets publics (F67) ---

    public function test_projects_are_public_and_drafts_do_not_exist_for_the_public(): void
    {
        $ouvert = $this->projetOuvert(['titre' => 'Place du Port']);
        $brouillon = Projet::factory()->brouillon()->create(['titre' => 'Projet secret']);

        $this->get(route('projets.index'))->assertOk()->assertSee('Place du Port')->assertDontSee('Projet secret');
        $this->get(route('projets.show', $ouvert))->assertOk()->assertSee('Place du Port');
        $this->get(route('projets.show', $brouillon))->assertNotFound();
    }

    public function test_the_consultation_state_is_written_in_text_and_computed_at_each_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00', 'Indian/Reunion'));
        $projet = Projet::factory()->create(['consultation_debut_at' => now()->addDay(), 'consultation_fin_at' => now()->addDays(5)]);

        $this->get(route('projets.show', $projet))->assertSee('Consultation à venir')->assertSee('Elle s\'ouvrira le 11/10/2026 10:00.');

        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00', 'Indian/Reunion'));
        $this->get(route('projets.show', $projet))->assertSee('Consultation ouverte')->assertSee('jusqu\'au 15/10/2026 10:00');

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Indian/Reunion'));
        $this->get(route('projets.show', $projet))->assertSee('Consultation terminée')->assertSee('Elle s\'est terminée le 15/10/2026 10:00.');
    }

    public function test_the_closed_consultation_shows_what_the_city_retains(): void
    {
        $projet = Projet::factory()->close()->create(['bilan' => 'La ville retient deux terrains.']);

        $this->get(route('projets.show', $projet))->assertSee('Ce que la ville retient de la consultation')->assertSee('La ville retient deux terrains.');
    }

    public function test_project_pages_have_no_vote_vocabulary_and_no_public_tally(): void
    {
        $projet = $this->projetOuvert();
        $citoyen = User::factory()->create();
        Contribution::factory()->count(3)->create(['projet_id' => $projet->id]);

        $pages = [
            $this->get(route('projets.index'))->getContent(),
            $this->get(route('projets.show', $projet))->getContent(),
            $this->actingAs($citoyen)->get(route('projets.avis.create', $projet))->getContent(),
            $this->actingAs($citoyen)->get(route('idees.create'))->getContent(),
            $this->actingAs($citoyen)->get(route('mes-contributions.index'))->getContent(),
        ];

        foreach ($pages as $html) {
            $texte = mb_strtolower(strip_tags($html));
            foreach (['voter', 'scrutin', 'sondage', 'résultats', 'urne', 'pour ou contre', 'plébiscit'] as $mot) {
                $this->assertStringNotContainsString($mot, $texte, "« $mot » ne doit pas apparaître");
            }
            // Aucun décompte de contributions n'est présenté au public.
            $this->assertDoesNotMatchRegularExpression('/\d+\s+(avis|contributions?|participants?|réponses?)\b/u', $texte);
        }
    }

    // --- Accès par rôle ---

    public function test_guests_are_sent_to_login_for_every_participation_action(): void
    {
        $projet = $this->projetOuvert();
        $service = Service::factory()->create();
        $contribution = Contribution::factory()->create();

        $this->get(route('projets.avis.create', $projet))->assertRedirect(route('login'));
        $this->post(route('projets.avis.store', $projet), [])->assertRedirect(route('login'));
        $this->get(route('idees.create'))->assertRedirect(route('login'));
        $this->post(route('idees.store'), [])->assertRedirect(route('login'));
        $this->get(route('services.commentaire.create', $service))->assertRedirect(route('login'));
        $this->post(route('services.commentaire.store', $service), [])->assertRedirect(route('login'));
        $this->get(route('mes-contributions.index'))->assertRedirect(route('login'));
        $this->get(route('mes-contributions.show', $contribution))->assertRedirect(route('login'));
    }

    public function test_agents_and_admins_get_403_everywhere_a_citizen_contributes(): void
    {
        $projet = $this->projetOuvert();
        $service = Service::factory()->create();
        $contribution = Contribution::factory()->create();

        foreach ([User::factory()->agent()->create(), User::factory()->admin()->create()] as $acteur) {
            $this->actingAs($acteur)->get(route('projets.avis.create', $projet))->assertForbidden();
            $this->actingAs($acteur)->post(route('projets.avis.store', $projet), ['message' => str_repeat('a', 20)])->assertForbidden();
            $this->actingAs($acteur)->get(route('idees.create'))->assertForbidden();
            $this->actingAs($acteur)->post(route('idees.store'), [])->assertForbidden();
            $this->actingAs($acteur)->get(route('services.commentaire.create', $service))->assertForbidden();
            $this->actingAs($acteur)->post(route('services.commentaire.store', $service), [])->assertForbidden();
            $this->actingAs($acteur)->get(route('mes-contributions.index'))->assertForbidden();
            $this->actingAs($acteur)->get(route('mes-contributions.show', $contribution))->assertForbidden();
        }
        $this->assertSame(1, Contribution::count());
    }

    // --- Dépôt : avis, idée, commentaire ---

    public function test_a_citizen_gives_an_opinion_and_gets_a_reference_and_a_first_step(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00', 'Indian/Reunion'));
        $citoyen = User::factory()->create();
        $projet = $this->projetOuvert();

        $reponse = $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => 'Je souhaite plus de bancs sur la place.']);

        $c = Contribution::firstOrFail();
        $reponse->assertRedirect(route('mes-contributions.show', $c));
        $this->assertSame('PA-2026-'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT), $c->reference);
        $this->assertSame($citoyen->id, $c->user_id);
        $this->assertSame(TypeContribution::Avis, $c->type);
        $this->assertSame($projet->id, $c->projet_id);
        $this->assertSame(StatutContribution::Recue, $c->fresh()->statut);
        $this->assertSame([StatutContribution::Recue], $c->etapes->pluck('statut')->all());

        $this->actingAs($citoyen)->get(route('mes-contributions.show', $c))
            ->assertSee($c->reference)->assertSee('Votre contribution est enregistrée. Référence : '.$c->reference.'.')
            ->assertSee('La ville a bien reçu votre contribution.');
    }

    public function test_the_account_type_status_and_target_cannot_be_forced_from_the_form(): void
    {
        $citoyen = User::factory()->create();
        $autre = User::factory()->create();
        $projet = $this->projetOuvert();
        $autreProjet = $this->projetOuvert();

        $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), [
            'message' => 'Un avis tout à fait valable.', 'user_id' => $autre->id, 'statut' => 'prise_en_compte',
            'type' => 'idee', 'projet_id' => $autreProjet->id, 'reponse' => 'Faux', 'reference' => 'PA-9999-99999',
        ]);

        $c = Contribution::firstOrFail();
        $this->assertSame($citoyen->id, $c->user_id);
        $this->assertSame(StatutContribution::Recue, $c->fresh()->statut);
        $this->assertSame(TypeContribution::Avis, $c->type);
        $this->assertSame($projet->id, $c->projet_id);
        $this->assertNull($c->reponse);
        $this->assertNotSame('PA-9999-99999', $c->reference);
    }

    public function test_an_opinion_is_refused_outside_an_open_consultation_on_the_form_and_on_the_server(): void
    {
        $citoyen = User::factory()->create();

        foreach ([Projet::factory()->aVenir()->create(), Projet::factory()->close()->create(), Projet::factory()->sansConsultation()->create()] as $projet) {
            $this->actingAs($citoyen)->get(route('projets.avis.create', $projet))->assertRedirect(route('projets.show', $projet));
            $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => 'Un avis tout à fait valable.'])
                ->assertRedirect(route('projets.show', $projet))->assertSessionHasErrors('participation');
        }
        $this->assertSame(0, Contribution::count());

        $brouillon = Projet::factory()->brouillon()->create();
        $this->actingAs($citoyen)->post(route('projets.avis.store', $brouillon), ['message' => 'Un avis tout à fait valable.'])->assertNotFound();
        $this->assertSame(0, Contribution::count());
    }

    public function test_the_opinion_button_follows_the_consultation_and_the_role(): void
    {
        $ouvert = $this->projetOuvert();
        $ferme = Projet::factory()->close()->create();

        $this->get(route('projets.show', $ouvert))->assertSee('Se connecter pour donner mon avis');
        $this->actingAs(User::factory()->create())->get(route('projets.show', $ouvert))->assertSee(route('projets.avis.create', $ouvert), false);
        $this->actingAs(User::factory()->create())->get(route('projets.show', $ferme))->assertDontSee(route('projets.avis.create', $ferme), false);
        $this->actingAs(User::factory()->agent()->create())->get(route('projets.show', $ouvert))->assertOk()->assertDontSee(route('projets.avis.create', $ouvert), false);
    }

    public function test_one_opinion_per_citizen_and_project(): void
    {
        $citoyen = User::factory()->create();
        $projet = $this->projetOuvert();
        $autre = $this->projetOuvert();

        $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => 'Mon premier avis sur le projet.']);
        $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => 'Un second avis, refusé.'])->assertSessionHasErrors('participation');
        $this->assertSame(1, Contribution::where('user_id', $citoyen->id)->where('projet_id', $projet->id)->count());
        $this->assertSame('Mon premier avis sur le projet.', Contribution::firstOrFail()->message);

        // Un autre projet, ou un autre habitant : possible.
        $this->actingAs($citoyen)->post(route('projets.avis.store', $autre), ['message' => 'Avis sur un autre projet.']);
        $this->actingAs(User::factory()->create())->post(route('projets.avis.store', $projet), ['message' => 'Avis d\'un autre habitant.']);
        $this->assertSame(3, Contribution::count());
    }

    public function test_the_form_tells_a_citizen_who_already_gave_an_opinion_and_links_to_it(): void
    {
        $citoyen = User::factory()->create();
        $projet = $this->projetOuvert();
        $existante = Contribution::factory()->create(['user_id' => $citoyen->id, 'projet_id' => $projet->id]);

        $this->actingAs($citoyen)->get(route('projets.avis.create', $projet))
            ->assertSee('Vous avez déjà donné votre avis')->assertSee($existante->reference)->assertDontSee('Envoyer ma contribution');
    }

    public function test_a_citizen_proposes_ideas_without_a_limit_of_one(): void
    {
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => 'Une boîte à livres', 'message' => 'Installer une boîte à livres près du marché.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => 'Des bancs au port', 'message' => 'Ajouter des bancs le long du quai du port.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Contribution::where('type', 'idee')->where('user_id', $citoyen->id)->count());
        $this->assertNull(Contribution::first()->projet_id);
        $this->assertStringStartsWith('PA-', Contribution::first()->reference);
    }

    public function test_a_citizen_comments_a_service_once_and_the_comment_is_not_public(): void
    {
        $citoyen = User::factory()->create();
        $service = Service::factory()->create(['actif' => true]);

        $this->actingAs($citoyen)->post(route('services.commentaire.store', $service), ['message' => 'Un accueil rapide et très clair.'])->assertSessionHasNoErrors();
        $this->actingAs($citoyen)->post(route('services.commentaire.store', $service), ['message' => 'Un second commentaire refusé.'])->assertSessionHasErrors('participation');

        $c = Contribution::firstOrFail();
        $this->assertSame(TypeContribution::Commentaire, $c->type);
        $this->assertSame($service->id, $c->service_id);
        $this->assertSame(1, Contribution::count());

        // Ni l'invité, ni un autre habitant, ni la fiche du service ne montrent le texte.
        $this->get(route('services.show', $service))->assertDontSee('Un accueil rapide et très clair.');
        $this->actingAs(User::factory()->create())->get(route('services.show', $service))->assertDontSee('Un accueil rapide et très clair.');
        $this->actingAs($citoyen)->get(route('services.show', $service))->assertDontSee('Un accueil rapide et très clair.');
        $this->actingAs($citoyen)->get(route('mes-contributions.show', $c))->assertSee('Un accueil rapide et très clair.');
    }

    public function test_the_service_page_only_has_a_comment_button_or_a_login_link(): void
    {
        $service = Service::factory()->create(['actif' => true]);

        $this->get(route('services.show', $service))->assertSee('Se connecter pour laisser un commentaire')->assertDontSee(route('services.commentaire.create', $service), false);
        $this->actingAs(User::factory()->create())->get(route('services.show', $service))->assertSee('Laisser un commentaire')->assertSee(route('services.commentaire.create', $service), false);
        $this->actingAs(User::factory()->agent()->create())->get(route('services.show', $service))->assertDontSee('Laisser un commentaire');
    }

    public function test_comments_are_allowed_without_a_linked_request_but_not_on_an_inactive_service(): void
    {
        $citoyen = User::factory()->create();
        $inactif = Service::factory()->create(['actif' => false]);

        $this->assertSame(0, $citoyen->demandes()->count());
        $this->actingAs($citoyen)->get(route('services.commentaire.create', $inactif))->assertNotFound();
        $this->actingAs($citoyen)->post(route('services.commentaire.store', $inactif), ['message' => 'Un commentaire valable ici.'])->assertNotFound();
    }

    public function test_length_limits_and_required_fields(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // la limite de débit a son propre test
        $citoyen = User::factory()->create();
        $projet = $this->projetOuvert();
        $service = Service::factory()->create(['actif' => true]);

        $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => ''])->assertSessionHasErrors('message');
        $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => 'court'])->assertSessionHasErrors('message');
        $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => str_repeat('a', 1001)])->assertSessionHasErrors('message');
        $this->actingAs($citoyen)->post(route('services.commentaire.store', $service), ['message' => str_repeat('a', 1001)])->assertSessionHasErrors('message');
        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => 'abc', 'message' => str_repeat('a', 30)])->assertSessionHasErrors('titre');
        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => 'Un bon titre', 'message' => str_repeat('a', 1501)])->assertSessionHasErrors('message');
        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => ['x'], 'message' => ['y']])->assertSessionHasErrors(['titre', 'message']);

        $this->assertSame(0, Contribution::count());

        $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => str_repeat('a', 1000)])->assertSessionHasNoErrors();
        $this->assertSame(1, Contribution::count());
    }

    public function test_the_form_has_labels_linked_errors_and_one_h1(): void
    {
        $citoyen = User::factory()->create();

        $html = $this->actingAs($citoyen)->get(route('idees.create'))->getContent();
        $page = $this->actingAs($citoyen)->from(route('idees.create'))->post(route('idees.store'), ['titre' => 'ab', 'message' => 'court']);

        $this->assertMatchesRegularExpression('/<label[^>]*for="titre"/', $html);
        $this->assertMatchesRegularExpression('/<label[^>]*for="message"/', $html);
        $this->assertStringContainsString('aria-describedby="titre_aide"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, '<main'));
        $page->assertSessionHasErrors(['titre', 'message']);

        $erreurs = $this->actingAs($citoyen)->from(route('idees.create'))->followingRedirects()
            ->post(route('idees.store'), ['titre' => 'ab', 'message' => 'court'])->getContent();
        $this->assertStringContainsString('aria-describedby="titre_aide titre_erreur"', $erreurs);
        $this->assertStringContainsString('aria-describedby="message_aide message_erreur"', $erreurs);
    }

    public function test_user_text_is_escaped(): void
    {
        $citoyen = User::factory()->create();
        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => '<script>alert(1)</script> titre', 'message' => 'Un <b>message</b> avec des balises <script>x</script>.']);

        $c = Contribution::firstOrFail();
        $html = $this->actingAs($citoyen)->get(route('mes-contributions.show', $c))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>message</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;message&lt;/b&gt;', $html);
    }

    public function test_submissions_are_throttled_per_account(): void
    {
        $citoyen = User::factory()->create();
        $autre = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => "Idée numéro $i", 'message' => "Description de l'idée numéro $i, assez longue."])->assertSessionHasNoErrors();
        }
        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => 'Idée de trop', 'message' => 'Description de l\'idée de trop, assez longue.'])->assertStatus(429);
        $this->assertSame(5, Contribution::count());

        // Le compteur est propre à chaque compte.
        $this->actingAs($autre)->post(route('idees.store'), ['titre' => 'Idée de l\'autre', 'message' => 'Description de l\'idée de l\'autre habitant.'])->assertSessionHasNoErrors();
        $this->assertSame(6, Contribution::count());
    }

    // --- Mes contributions et suivi ---

    public function test_my_contributions_lists_only_mine_newest_first(): void
    {
        $moi = User::factory()->create();
        $autre = User::factory()->create();
        $ancienne = Contribution::factory()->idee()->create(['user_id' => $moi->id, 'titre' => 'Idée ancienne', 'created_at' => now()->subDays(5)]);
        $recente = Contribution::factory()->create(['user_id' => $moi->id, 'created_at' => now()->subDay()]);
        $voisine = Contribution::factory()->idee()->create(['user_id' => $autre->id, 'titre' => 'Idée du voisin']);

        $this->actingAs($moi)->get(route('mes-contributions.index'))
            ->assertSeeInOrder([$recente->reference, $ancienne->reference])
            ->assertDontSee($voisine->reference)->assertDontSee('Idée du voisin')
            ->assertSee('Reçue');
    }

    public function test_the_list_is_paginated_by_ten(): void
    {
        $moi = User::factory()->create();
        Contribution::factory()->idee()->count(12)->create(['user_id' => $moi->id]);

        $page = $this->actingAs($moi)->get(route('mes-contributions.index'))->getContent();
        $this->assertSame(10, substr_count($page, 'class="underline tn-code"'));
        $this->actingAs($moi)->get(route('mes-contributions.index', ['page' => 2]))->assertSee('PA-');
    }

    public function test_a_citizen_cannot_open_someone_elses_contribution_nor_an_anonymised_one(): void
    {
        $moi = User::factory()->create();
        $autre = Contribution::factory()->create();
        $orpheline = Contribution::factory()->create(['user_id' => null]);

        $this->actingAs($moi)->get(route('mes-contributions.show', $autre))->assertForbidden();
        $this->actingAs($moi)->get(route('mes-contributions.show', $orpheline))->assertForbidden();
    }

    public function test_the_detail_shows_the_timeline_in_text_and_the_response_of_the_city(): void
    {
        $moi = User::factory()->create();
        $c = Contribution::factory()->create(['user_id' => $moi->id, 'statut' => StatutContribution::PriseEnCompte,
            'reponse' => 'Merci, nous installons deux bancs.', 'reponse_at' => now()]);
        $this->etape($c, StatutContribution::Examinee, now()->subHour());
        $this->etape($c, StatutContribution::PriseEnCompte, now());

        $html = $this->actingAs($moi)->get(route('mes-contributions.show', $c))->getContent();

        foreach (['Reçue', 'Examinée', 'Prise en compte', 'Étape actuelle', 'Réponse de la ville', 'Merci, nous installons deux bancs.'] as $texte) {
            $this->assertStringContainsString($texte, $html);
        }
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_a_status_change_shows_a_notice_on_the_dashboard_until_the_contribution_is_opened(): void
    {
        $moi = User::factory()->create();
        $autre = User::factory()->create();
        $c = Contribution::factory()->create(['user_id' => $moi->id, 'statut' => StatutContribution::Examinee]);
        $this->etape($c, StatutContribution::Examinee, now());

        $this->actingAs($moi)->get(route('dashboard'))
            ->assertSee('Votre contribution '.$c->reference.' a été examinée par la ville.')->assertSee('role="status"', false);
        $this->actingAs($autre)->get(route('dashboard'))->assertDontSee($c->reference);

        $this->actingAs($moi)->get(route('mes-contributions.show', $c))->assertOk();
        $this->actingAs($moi)->get(route('dashboard'))->assertDontSee('a été examinée par la ville');
    }

    public function test_every_new_page_is_linked_from_the_navigation(): void
    {
        $citoyen = User::factory()->create();

        $this->actingAs($citoyen)->get(route('dashboard'))
            ->assertSee(route('projets.index'), false)->assertSee(route('mes-contributions.index'), false)->assertSee(route('idees.create'), false);
        $this->get(route('accueil'))->assertSee(route('projets.index'), false);

        foreach ([User::factory()->agent()->create(), User::factory()->admin()->create()] as $acteur) {
            $this->actingAs($acteur)->get(route($acteur->isAgent() ? 'agent.index' : 'admin.index'))
                ->assertDontSee(route('mes-contributions.index'), false)->assertDontSee('Mes contributions');
        }
    }

    public function test_project_pages_have_one_main_and_one_h1(): void
    {
        $projet = $this->projetOuvert();
        $citoyen = User::factory()->create();
        $service = Service::factory()->create(['actif' => true]);
        $c = Contribution::factory()->create(['user_id' => $citoyen->id]);

        $urls = [route('projets.index'), route('projets.show', $projet), route('projets.avis.create', $projet), route('idees.create'),
            route('services.commentaire.create', $service), route('mes-contributions.index'), route('mes-contributions.show', $c)];

        foreach ($urls as $url) {
            $html = $this->actingAs($citoyen)->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<main'), $url);
            $this->assertSame(1, substr_count($html, '<h1'), $url);
            $this->assertStringContainsString('<html lang="fr"', $html);
            $this->assertDoesNotMatchRegularExpression('/style="[^"]*px/', $html, $url);
        }
    }

    public function test_pages_do_not_depend_on_the_number_of_projects_or_contributions(): void
    {
        $moi = User::factory()->create();
        $mesure = function (string $url) use ($moi): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($moi)->get($url)->assertOk();

            return count(DB::getQueryLog());
        };

        Projet::factory()->count(2)->create();
        Contribution::factory()->count(2)->create(['user_id' => $moi->id]);
        $liste = $mesure(route('projets.index'));
        $mes = $mesure(route('mes-contributions.index'));
        $espace = $mesure(route('dashboard'));

        Projet::factory()->count(6)->create();
        Contribution::factory()->idee()->count(6)->create(['user_id' => $moi->id]);

        $this->assertSame($liste, $mesure(route('projets.index')));
        $this->assertSame($mes, $mesure(route('mes-contributions.index')));
        $this->assertSame($espace, $mesure(route('dashboard')));
    }

    public function test_no_external_domain_in_the_participation_views(): void
    {
        foreach (['projets', 'participation', 'mes-contributions'] as $dossier) {
            foreach (glob(resource_path("views/$dossier/*.blade.php")) as $fichier) {
                $this->assertDoesNotMatchRegularExpression('#https?://#', file_get_contents($fichier), $fichier);
                $this->assertStringNotContainsString('style="', file_get_contents($fichier), $fichier);
            }
        }
    }
}
