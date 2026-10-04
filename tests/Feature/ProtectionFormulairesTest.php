<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TypeEvenementSecurite;
use App\Models\Alerte;
use App\Models\Contribution;
use App\Models\Demande;
use App\Models\EvenementSecurite;
use App\Models\Projet;
use App\Models\Service;
use App\Models\User;
use App\Services\ProtectionFormulaires;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F81 (envois automatiques) et F82 (envois en double) : jeton signé avec délai minimal, champ leurre, limite de débit,
 * jeton à usage unique. Désactivée dans phpunit.xml : activée ici, explicitement.
 */
class ProtectionFormulairesTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $t0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['securite.protection_formulaires' => true]);
        $this->t0 = Carbon::parse('2026-10-10 10:00:00', 'Indian/Reunion');
        Carbon::setTestNow($this->t0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Jeton émis à $t0, comme à l'affichage du formulaire. */
    private function jeton(): string
    {
        Carbon::setTestNow($this->t0);

        return app(ProtectionFormulaires::class)->jeton();
    }

    /** Envoie un formulaire avec le jeton donné, $age secondes après son affichage. */
    private function envoyer(string $methode, string $url, array $donnees, string|array $jeton, int $age = 5, ?User $user = null, array $extra = [])
    {
        Carbon::setTestNow($this->t0->copy()->addSeconds($age));
        $requete = $user ? $this->actingAs($user) : $this;

        return $requete->{$methode}($url, array_merge($donnees, [ProtectionFormulaires::CHAMP_JETON => $jeton], $extra));
    }

    private function citoyen(): User
    {
        return User::factory()->create();
    }

    private function donneesContact(): array
    {
        $service = Service::factory()->create(['actif' => true]);

        return ['objet' => 'Lampadaire en panne', 'message' => 'Le lampadaire de ma rue est éteint depuis une semaine.', 'service_id' => $service->id];
    }

    private const MESSAGE_NEUTRE = 'Votre envoi n\'a pas pu être pris en compte. Réessayez dans quelques secondes.';

    // --- Mise en place ---

    public function test_the_protection_is_on_by_default_and_off_in_phpunit_xml(): void
    {
        $this->assertStringContainsString("env('NOVATERRA_PROTECTION_FORMULAIRES', true)", file_get_contents(config_path('securite.php')));
        $this->assertStringContainsString('NOVATERRA_PROTECTION_FORMULAIRES" value="false"', file_get_contents(base_path('phpunit.xml')));
        $this->assertStringContainsString('NOVATERRA_PROTECTION_FORMULAIRES=true', file_get_contents(base_path('.env.example')));
    }

    public function test_when_disabled_nothing_is_added_to_the_forms_and_no_token_is_required(): void
    {
        config(['securite.protection_formulaires' => false]);
        $citoyen = $this->citoyen();

        $this->get(route('login'))->assertDontSee('_jeton_formulaire')->assertDontSee('note_interne_zq');
        $this->actingAs($citoyen)->post(route('contact.store'), $this->donneesContact())->assertSessionHasNoErrors();
        $this->assertSame(1, Demande::count());
    }

    public function test_every_protected_form_carries_a_token_and_an_invisible_decoy_field(): void
    {
        $citoyen = $this->citoyen();
        $admin = User::factory()->admin()->create();
        $projet = Projet::factory()->create();
        $service = Service::factory()->create(['actif' => true]);

        $pages = [
            [null, route('login')], [null, route('register')], [null, route('password.request')], [null, route('password.reset', ['token' => 'abc'])],
            [$citoyen, route('contact.create')], [$citoyen, route('projets.avis.create', $projet)], [$citoyen, route('idees.create')],
            [$citoyen, route('services.commentaire.create', $service)],
            [$admin, route('admin.alertes.create')], [$admin, route('admin.participation.projets.create')],
        ];

        foreach ($pages as [$user, $url]) {
            $html = ($user ? $this->actingAs($user) : $this)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('name="_jeton_formulaire"', $html, $url);
            $this->assertMatchesRegularExpression('/<div class="hidden" aria-hidden="true">\s*<label[^>]*for="leurre-note_interne_zq"[^>]*>[^<]+<\/label>\s*<input type="text" id="leurre-note_interne_zq" name="note_interne_zq" value="" tabindex="-1" autocomplete="off">/', $html, $url);
            $this->assertStringNotContainsString('style=', substr($html, strpos($html, 'note_interne_zq') - 200, 400), $url);
            $this->assertStringNotContainsStringIgnoringCase('captcha', $html, $url);
            $this->assertSame(1, substr_count($html, '<h1'), $url);
        }
    }

    public function test_the_decoy_name_is_not_a_real_field_of_any_form(): void
    {
        $nom = app(ProtectionFormulaires::class)->nomLeurre();

        foreach (glob(resource_path('views/{auth,contact,participation,admin}/{,*/}*.blade.php'), GLOB_BRACE) as $fichier) {
            $this->assertStringNotContainsString('name="'.$nom.'"', file_get_contents($fichier), $fichier);
        }
        $this->assertNotContains($nom, ['email', 'name', 'password', 'objet', 'message', 'titre', 'telephone', 'adresse']);
    }

    public function test_every_public_form_route_goes_through_the_middleware(): void
    {
        foreach (['register', 'login'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri && in_array('POST', $r->methods(), true));
            $this->assertNotNull($route, $uri);
            $this->assertContains('formulaire', array_map(fn ($m) => explode(':', $m)[0], $route->gatherMiddleware()), $uri);
        }
        foreach (['password.email', 'password.store', 'contact.store', 'projets.avis.store', 'idees.store',
            'services.commentaire.store', 'admin.alertes.store', 'admin.participation.projets.store'] as $nom) {
            $route = Route::getRoutes()->getByName($nom);
            $this->assertNotNull($route, $nom);
            $this->assertContains('formulaire', array_map(fn ($m) => explode(':', $m)[0], $route->gatherMiddleware()), $nom);
        }
    }

    // --- F81 : refus ---

    public function test_a_post_without_token_is_refused_with_a_neutral_message_and_nothing_is_created(): void
    {
        $citoyen = $this->citoyen();
        $projet = Projet::factory()->create();
        $service = Service::factory()->create(['actif' => true]);

        $this->post(route('register'), ['name' => 'Robot', 'email' => 'robot@example.test', 'password' => 'unmotdepasse10', 'password_confirmation' => 'unmotdepasse10'])
            ->assertSessionHasErrors('formulaire');

        $this->actingAs($citoyen)->from(route('contact.create'))->post(route('contact.store'), $this->donneesContact())
            ->assertRedirect(route('contact.create'))->assertSessionHasErrors(['formulaire' => self::MESSAGE_NEUTRE]);
        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => 'Une idée', 'message' => str_repeat('a', 30)])->assertSessionHasErrors('formulaire');
        $this->actingAs($citoyen)->post(route('projets.avis.store', $projet), ['message' => str_repeat('a', 20)])->assertSessionHasErrors('formulaire');
        $this->actingAs($citoyen)->post(route('services.commentaire.store', $service), ['message' => str_repeat('a', 20)])->assertSessionHasErrors('formulaire');

        $this->assertSame(0, Demande::count() + Contribution::count());
        $this->assertSame(1, User::count()); // le citoyen seulement
        $this->assertNull(User::where('email', 'robot@example.test')->first());
    }

    public function test_the_neutral_message_is_the_same_whatever_the_reason(): void
    {
        $citoyen = $this->citoyen();
        $jeton = $this->jeton();
        $donnees = ['titre' => 'Une idée', 'message' => str_repeat('a', 30)];

        $messages = [
            $this->envoyer('post', route('idees.store'), $donnees, $jeton, 1, $citoyen),                                       // trop rapide
            $this->envoyer('post', route('idees.store'), $donnees, 'jeton.falsifie.xyz', 5, $citoyen),                           // jeton invalide
            $this->envoyer('post', route('idees.store'), $donnees, $jeton, 5, $citoyen, ['note_interne_zq' => 'robot']),   // leurre
        ];

        foreach ($messages as $reponse) {
            $reponse->assertSessionHasErrors(['formulaire' => self::MESSAGE_NEUTRE]);
        }
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_post_faster_than_two_seconds_is_refused_keeps_the_fields_and_the_same_token_works_a_moment_later(): void
    {
        $citoyen = $this->citoyen();
        $jeton = $this->jeton();
        $donnees = $this->donneesContact();

        $this->actingAs($citoyen)->from(route('contact.create'));
        $this->envoyer('post', route('contact.store'), $donnees, $jeton, 1, $citoyen)
            ->assertSessionHasErrors(['formulaire' => self::MESSAGE_NEUTRE])->assertSessionHasInput('objet', 'Lampadaire en panne');
        $this->assertSame(0, Demande::count());

        // Le jeton n'a pas été consommé : le même formulaire est accepté trois secondes plus tard.
        $this->envoyer('post', route('contact.store'), $donnees, $jeton, 3, $citoyen)->assertSessionHasNoErrors();
        $this->assertSame(1, Demande::count());
    }

    public function test_the_message_page_shows_the_neutral_message_and_the_typed_text(): void
    {
        $citoyen = $this->citoyen();

        $this->actingAs($citoyen)->from(route('idees.create'))->post(route('idees.store'), ['titre' => 'Mon idée à garder', 'message' => 'Un texte saisi à conserver.']);
        $this->actingAs($citoyen)->get(route('idees.create'))
            ->assertSee(self::MESSAGE_NEUTRE)->assertSee('role="alert"', false)->assertSee('Mon idée à garder')->assertSee('Un texte saisi à conserver.');
    }

    public function test_a_filled_decoy_a_forged_token_and_an_expired_token_are_refused(): void
    {
        $citoyen = $this->citoyen();
        $donnees = ['titre' => 'Une idée', 'message' => str_repeat('a', 30)];

        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 5, $citoyen, ['note_interne_zq' => 'https://spam.example'])->assertSessionHasErrors('formulaire');

        $jeton = $this->jeton();
        $falsifie = substr($jeton, 0, -4).'0000';
        $this->envoyer('post', route('idees.store'), $donnees, $falsifie, 5, $citoyen)->assertSessionHasErrors('formulaire');
        $autreHeure = explode('.', $jeton);
        $autreHeure[1] = (string) ((int) $autreHeure[1] + 100);   // heure modifiée, signature d'origine
        $this->envoyer('post', route('idees.store'), $donnees, implode('.', $autreHeure), 5, $citoyen)->assertSessionHasErrors('formulaire');
        $this->envoyer('post', route('idees.store'), $donnees, ['tableau'], 5, $citoyen)->assertSessionHasErrors('formulaire');

        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 181 * 60, $citoyen)->assertSessionHasErrors('formulaire');
        $this->assertSame(0, Contribution::count());

        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 10 * 60, $citoyen)->assertSessionHasNoErrors();
        $this->assertSame(1, Contribution::count());
    }

    public function test_passwords_are_never_sent_back_to_the_form_after_a_refusal(): void
    {
        $this->post(route('register'), ['name' => 'Camille', 'email' => 'camille@example.test', 'password' => 'unmotdepasse10', 'password_confirmation' => 'unmotdepasse10'])
            ->assertSessionHasInput('name', 'Camille')->assertSessionHasInput('email', 'camille@example.test')
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation')
            ->assertSessionMissing('_old_input._jeton_formulaire');
    }

    // --- Journal de sécurité ---

    public function test_refusals_are_written_without_content_and_with_a_masked_email(): void
    {
        $this->post(route('login'), ['email' => 'camille@example.test', 'password' => 'secret-tres-confidentiel'])->assertSessionHasErrors('formulaire');
        $this->envoyer('post', route('register'), ['name' => 'Camille Secrète', 'email' => 'zoe@example.fr', 'password' => 'unmotdepasse10', 'password_confirmation' => 'unmotdepasse10'], $this->jeton(), 1);

        $evenements = EvenementSecurite::where('type', TypeEvenementSecurite::FormulaireSuspect->value)->orderBy('id')->get();
        $this->assertCount(2, $evenements);
        $this->assertSame('jeton absent', $evenements[0]->detail);
        $this->assertSame('c***@e***.test', $evenements[0]->email_masque);
        $this->assertSame('envoi trop rapide', $evenements[1]->detail);
        $this->assertSame('z***@e***.fr', $evenements[1]->email_masque);
        $this->assertSame('login', $evenements[0]->route);

        $tout = json_encode($evenements->map->getAttributes()->all(), JSON_UNESCAPED_UNICODE);
        foreach (['camille@example.test', 'zoe@example.fr', 'secret-tres-confidentiel', 'Camille Secrète', 'unmotdepasse10'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $tout);
        }
    }

    public function test_the_reasons_are_logged_and_the_log_is_capped_per_address_and_minute(): void
    {
        $citoyen = $this->citoyen();
        $donnees = ['titre' => 'Une idée', 'message' => str_repeat('a', 30)];

        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 5, $citoyen, ['note_interne_zq' => 'x']);
        $this->envoyer('post', route('idees.store'), $donnees, 'x.y.z', 5, $citoyen);
        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 181 * 60, $citoyen);

        $this->assertEqualsCanonicalizing(['champ leurre rempli', 'jeton invalide', 'jeton expiré'],
            EvenementSecurite::where('type', 'formulaire_suspect')->pluck('detail')->all());

    }

    public function test_the_log_is_capped_at_ten_refusals_per_address_and_minute(): void
    {
        config(['securite.formulaires.limite' => 1000]);
        $citoyen = $this->citoyen();

        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => 'Une idée', 'message' => str_repeat('a', 30)])->assertSessionHasErrors('formulaire');
        }

        $this->assertSame(10, EvenementSecurite::where('type', 'formulaire_suspect')->count());
    }

    public function test_the_security_page_lists_the_new_type_for_the_admin(): void
    {
        $this->post(route('login'), ['email' => 'x@example.test', 'password' => 'y']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.securite'))->assertOk()->assertSee('Formulaire suspect');
    }

    // --- Limite de débit ---

    public function test_the_rate_limit_refuses_with_its_own_neutral_message_and_logs_it(): void
    {
        config(['securite.formulaires.limite' => 3]);
        $citoyen = $this->citoyen();
        $donnees = ['titre' => 'Une idée', 'message' => str_repeat('a', 30)];

        for ($i = 1; $i <= 3; $i++) {
            $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 5, $citoyen)->assertSessionHasNoErrors();
        }
        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 5, $citoyen)
            ->assertSessionHasErrors(['formulaire' => 'Trop d\'envois en peu de temps. Patientez quelques minutes avant de réessayer.']);

        $this->assertSame(3, Contribution::count());
        $this->assertSame(1, EvenementSecurite::where('detail', 'limite de débit')->count());

        // La fenêtre passée, l'envoi redevient possible.
        $this->t0 = $this->t0->copy()->addMinutes(11);
        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 5, $citoyen)->assertSessionHasNoErrors();
        $this->assertSame(4, Contribution::count());
    }

    // --- F82 : usage unique ---

    public function test_a_resubmitted_contact_form_creates_nothing_and_leads_back_to_the_original_result(): void
    {
        $citoyen = $this->citoyen();
        $jeton = $this->jeton();
        $donnees = $this->donneesContact();

        $this->envoyer('post', route('contact.store'), $donnees, $jeton, 5, $citoyen)->assertRedirect();
        $demande = Demande::firstOrFail();

        // Double clic, F5 ou bouton Retour : le même jeton.
        $this->envoyer('post', route('contact.store'), $donnees, $jeton, 6, $citoyen)
            ->assertRedirect(route('contact.confirmation', $demande))->assertSessionHas('success', 'Déjà enregistré, voici sa référence.');
        $this->envoyer('post', route('contact.store'), $donnees, $jeton, 60, $citoyen)->assertRedirect(route('contact.confirmation', $demande));

        $this->assertSame(1, Demande::count());
        $this->actingAs($citoyen)->get(route('contact.confirmation', $demande))->assertSee($demande->reference);
    }

    public function test_every_contribution_form_is_single_use(): void
    {
        $citoyen = $this->citoyen();
        $projet = Projet::factory()->create();
        $service = Service::factory()->create(['actif' => true]);

        $cas = [
            [route('idees.store'), ['titre' => 'Une idée', 'message' => str_repeat('a', 30)]],
            [route('projets.avis.store', $projet), ['message' => 'Un avis assez long.']],
            [route('services.commentaire.store', $service), ['message' => 'Un commentaire assez long.']],
        ];

        foreach ($cas as [$url, $donnees]) {
            $jeton = $this->jeton();
            $this->envoyer('post', $url, $donnees, $jeton, 5, $citoyen)->assertRedirect();
            $origine = Contribution::latest('id')->firstOrFail();

            $this->envoyer('post', $url, $donnees, $jeton, 6, $citoyen)
                ->assertRedirect(route('mes-contributions.show', $origine))->assertSessionHas('success', 'Déjà enregistré, voici sa référence.');
        }
        $this->assertSame(3, Contribution::count());
    }

    public function test_a_new_display_of_the_form_gives_a_new_token_that_works(): void
    {
        $citoyen = $this->citoyen();
        $donnees = ['titre' => 'Une idée', 'message' => str_repeat('a', 30)];

        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 5, $citoyen);
        $this->envoyer('post', route('idees.store'), $donnees, $this->jeton(), 5, $citoyen);

        $this->assertSame(2, Contribution::count());
        $this->assertNotSame($this->jeton(), $this->jeton());
    }

    public function test_a_validation_error_does_not_consume_the_token(): void
    {
        $citoyen = $this->citoyen();
        $jeton = $this->jeton();

        $this->envoyer('post', route('idees.store'), ['titre' => 'ab', 'message' => 'court'], $jeton, 5, $citoyen)->assertSessionHasErrors(['titre', 'message']);
        $this->assertSame(0, Contribution::count());

        $this->envoyer('post', route('idees.store'), ['titre' => 'Un bon titre', 'message' => str_repeat('a', 30)], $jeton, 20, $citoyen)->assertSessionHasNoErrors();
        $this->assertSame(1, Contribution::count());
    }

    public function test_a_refused_business_rule_does_not_consume_the_token_either(): void
    {
        $citoyen = $this->citoyen();
        $projet = Projet::factory()->close()->create();
        $jeton = $this->jeton();

        $this->envoyer('post', route('projets.avis.store', $projet), ['message' => 'Un avis assez long.'], $jeton, 5, $citoyen)->assertSessionHasErrors('participation');
        $this->assertNull(app(ProtectionFormulaires::class)->dejaFait(explode('.', $jeton)[0]));
    }

    public function test_registration_and_login_work_with_a_token_and_cannot_be_replayed(): void
    {
        $jeton = $this->jeton();
        $donnees = ['name' => 'Camille Habitante', 'email' => 'camille@example.test', 'password' => 'unmotdepasse10', 'password_confirmation' => 'unmotdepasse10'];

        $this->envoyer('post', route('register'), $donnees, $jeton, 5)->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame(Role::Citoyen, User::where('email', 'camille@example.test')->firstOrFail()->role);
        $this->assertAuthenticated();

        $this->envoyer('post', route('register'), $donnees, $jeton, 6);
        $this->assertSame(1, User::where('email', 'camille@example.test')->count());

        auth()->logout();
        $this->envoyer('post', route('login'), ['email' => 'camille@example.test', 'password' => 'mauvais-mot-de-passe'], $this->jeton(), 5)->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->envoyer('post', route('login'), ['email' => 'camille@example.test', 'password' => 'unmotdepasse10'], $this->jeton(), 5)->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_admin_creation_forms_do_not_create_duplicates(): void
    {
        $admin = User::factory()->admin()->create();
        $jeton = $this->jeton();
        $alerte = ['titre' => 'Coupure d\'eau', 'niveau' => 'info', 'ce_qui_se_passe' => 'Une coupure.', 'ce_quil_faut_faire' => 'Stocker de l\'eau.'];

        $this->envoyer('post', route('admin.alertes.store'), $alerte, $jeton, 5, $admin)->assertRedirect(route('admin.alertes.index'));
        $this->envoyer('post', route('admin.alertes.store'), $alerte, $jeton, 6, $admin)
            ->assertRedirect(route('admin.alertes.index'))->assertSessionHas('success', 'Déjà enregistré.');
        $this->assertSame(1, Alerte::count());

        $jeton = $this->jeton();
        $projet = ['titre' => 'Jardins du port', 'resume' => 'Des jardins.', 'description' => 'Une description.', 'publie' => '1'];
        $this->envoyer('post', route('admin.participation.projets.store'), $projet, $jeton, 5, $admin);
        $this->envoyer('post', route('admin.participation.projets.store'), $projet, $jeton, 6, $admin);
        $this->assertSame(1, Projet::count());
    }

    public function test_the_token_is_never_optional_when_the_protection_is_active(): void
    {
        // Sans le champ jeton, même avec le bon leurre vide : refus (le jeton n'est jamais facultatif quand la protection est active).
        $citoyen = $this->citoyen();

        Carbon::setTestNow($this->t0->copy()->addSeconds(30));
        $this->actingAs($citoyen)->post(route('idees.store'), ['titre' => 'Une idée', 'message' => str_repeat('a', 30), 'note_interne_zq' => ''])
            ->assertSessionHasErrors('formulaire');
        $this->assertSame(0, Contribution::count());
    }

    // --- Connexion : pas de délai minimal ---

    public function test_the_login_form_has_no_minimum_delay_but_keeps_the_decoy_and_the_single_use_token(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'login' && in_array('POST', $r->methods(), true));
        $this->assertContains('formulaire:sans-delai', $route->gatherMiddleware());

        User::factory()->create(['email' => 'camille@example.test', 'password' => 'unmotdepasse10']);
        $identifiants = ['email' => 'camille@example.test', 'password' => 'unmotdepasse10'];

        // Un gestionnaire de mots de passe remplit et envoie dans la même seconde : accepté.
        $jeton = $this->jeton();
        $this->envoyer('post', route('login'), $identifiants, $jeton, 0)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertAuthenticated();

        // Le jeton reste à usage unique.
        auth()->logout();
        $this->envoyer('post', route('login'), $identifiants, $jeton, 0)->assertSessionHas('success', 'Déjà enregistré.');
        $this->assertGuest();

        // Le champ leurre, le jeton signé et les autres formulaires gardent leurs règles.
        $this->envoyer('post', route('login'), $identifiants, $this->jeton(), 0, null, ['note_interne_zq' => 'robot'])->assertSessionHasErrors('formulaire');
        $this->envoyer('post', route('login'), $identifiants, 'x.y.z', 0)->assertSessionHasErrors('formulaire');
        $this->assertGuest();
        $this->envoyer('post', route('idees.store'), ['titre' => 'Une idée', 'message' => str_repeat('a', 30)], $this->jeton(), 0, User::first())->assertSessionHasErrors('formulaire');
    }
}
