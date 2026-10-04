<?php

namespace Tests\Feature;

use App\Models\Alerte;
use App\Models\Contribution;
use App\Models\Demande;
use App\Models\Projet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Test de fumée de chaque formulaire protégé, protection ACTIVÉE : on affiche le vrai formulaire, on lit son jeton dans le
 * HTML, on avance le temps de 3 secondes, on poste des données valides et on vérifie le succès. Un composant oublié dans
 * une vue casserait le formulaire en production : les autres tests tournent protection coupée et ne le verraient pas.
 */
class ProtectionFormulairesParcoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['securite.protection_formulaires' => true]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public static function formulaires(): array
    {
        return array_combine($noms = ['inscription', 'connexion', 'mot de passe oublié', 'réinitialisation', 'contact', 'avis', 'idée', 'commentaire de service', 'alerte (admin)', 'projet (admin)'],
            array_map(fn ($n) => [$n], $noms));
    }

    #[DataProvider('formulaires')]
    public function test_each_protected_form_works_end_to_end_with_the_protection_on(string $formulaire): void
    {
        [$user, $pageUrl, $postUrl, $donnees, $succes] = $this->scenario($formulaire);

        $requete = $user ? $this->actingAs($user) : $this;
        $page = $requete->get($pageUrl)->assertOk()->getContent();

        // Le formulaire affiché porte son jeton et son champ leurre : sinon la protection casse l'envoi.
        $this->assertMatchesRegularExpression('/name="_jeton_formulaire" value="[0-9a-f]+\.\d+\.[0-9a-f]{64}"/', $page, "$formulaire : jeton absent du formulaire");
        $this->assertStringContainsString('name="note_interne_zq"', $page, "$formulaire : champ leurre absent du formulaire");
        preg_match('/name="_jeton_formulaire" value="([^"]+)"/', $page, $m);

        $this->travel(3)->seconds();

        $reponse = ($user ? $this->actingAs($user) : $this)->post($postUrl, $donnees + ['_jeton_formulaire' => $m[1], 'note_interne_zq' => '']);

        $reponse->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($succes(), "$formulaire : l'envoi valide n'a pas abouti");
    }

    /** @return array{0: ?User, 1: string, 2: string, 3: array<string, mixed>, 4: \Closure(): bool} */
    private function scenario(string $formulaire): array
    {
        $citoyen = User::factory()->create(['email' => 'camille@example.test', 'password' => 'unmotdepasse10']);
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create(['actif' => true]);
        $projet = Projet::factory()->create();
        $motDePasse = ['password' => 'nouveaumotdepasse10', 'password_confirmation' => 'nouveaumotdepasse10'];

        return match ($formulaire) {
            'inscription' => [null, route('register'), url('/register'),
                ['name' => 'Nouvelle Habitante', 'email' => 'nouvelle@example.test'] + $motDePasse,
                fn () => User::where('email', 'nouvelle@example.test')->exists()],
            'connexion' => [null, route('login'), url('/login'),
                ['email' => 'camille@example.test', 'password' => 'unmotdepasse10'],
                fn () => auth()->check()],
            'mot de passe oublié' => [null, route('password.request'), route('password.email'),
                ['email' => 'camille@example.test'],
                fn () => session()->has('status')],
            'réinitialisation' => [null, route('password.reset', ['token' => $jeton = Password::createToken($citoyen)]), route('password.store'),
                ['token' => $jeton, 'email' => 'camille@example.test'] + $motDePasse,
                fn () => password_verify('nouveaumotdepasse10', $citoyen->fresh()->password)],
            'contact' => [$citoyen, route('contact.create'), route('contact.store'),
                ['objet' => 'Lampadaire en panne', 'message' => 'Le lampadaire de ma rue est éteint.', 'service_id' => $service->id],
                fn () => Demande::where('objet', 'Lampadaire en panne')->exists()],
            'avis' => [$citoyen, route('projets.avis.create', $projet), route('projets.avis.store', $projet),
                ['message' => 'Un avis assez long sur ce projet.'],
                fn () => Contribution::where('type', 'avis')->exists()],
            'idée' => [$citoyen, route('idees.create'), route('idees.store'),
                ['titre' => 'Une boîte à livres', 'message' => 'Installer une boîte à livres près du marché.'],
                fn () => Contribution::where('type', 'idee')->exists()],
            'commentaire de service' => [$citoyen, route('services.commentaire.create', $service), route('services.commentaire.store', $service),
                ['message' => 'Un commentaire assez long sur ce service.'],
                fn () => Contribution::where('type', 'commentaire')->exists()],
            'alerte (admin)' => [$admin, route('admin.alertes.create'), route('admin.alertes.store'),
                ['titre' => 'Coupure d\'eau', 'niveau' => 'info', 'ce_qui_se_passe' => 'Une coupure.', 'ce_quil_faut_faire' => 'Stocker de l\'eau.'],
                fn () => Alerte::where('titre', 'Coupure d\'eau')->exists()],
            'projet (admin)' => [$admin, route('admin.participation.projets.create'), route('admin.participation.projets.store'),
                ['titre' => 'Jardins du port', 'resume' => 'Des jardins.', 'description' => 'Une description.', 'publie' => '1'],
                fn () => Projet::where('slug', 'jardins-du-port')->exists()],
        };
    }
}
