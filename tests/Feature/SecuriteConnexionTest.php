<?php

namespace Tests\Feature;

use App\Enums\TypeEvenementSecurite;
use App\Models\EvenementSecurite;
use App\Models\User;
use App\Services\JournalSecurite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** F37 : limitation des tentatives de connexion (e-mail + IP, IP seule, e-mail seul), toujours temporaire. */
class SecuriteConnexionTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE_10_MIN = 'Trop de tentatives de connexion. Réessayez dans 10 minutes.';

    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 10:00:00');
        Cache::flush();
        RateLimiter::clear('x');

        $this->camille = User::factory()->create(['email' => 'camille@example.test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function tenter(string $email, string $motDePasse = 'mauvais', string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/login', ['email' => $email, 'password' => $motDePasse]);
    }

    private function echouer(string $email, int $fois, string $ip = '10.0.0.1'): void
    {
        foreach (range(1, $fois) as $i) {
            $this->tenter($email, 'mauvais', $ip)->assertSessionHasErrors('email');
        }
    }

    // --- E-mail + IP : 5 échecs, blocage de 10 minutes ---

    public function test_five_failures_block_the_email_and_ip_for_ten_minutes(): void
    {
        $this->echouer('camille@example.test', 4);
        $this->tenter('camille@example.test', 'password')->assertRedirect(); // 4 échecs : pas encore bloqué (et ce succès efface la clé)
        auth()->logout();

        $this->echouer('camille@example.test', 5);

        // Même avec le bon mot de passe, la 6e tentative est refusée avec le délai restant.
        $this->tenter('camille@example.test', 'password')
            ->assertSessionHasErrors(['email' => self::MESSAGE_10_MIN]);
        $this->assertGuest();
    }

    private function message($reponse): string
    {
        $erreurs = $reponse->baseResponse->getSession()->get('errors');

        return $erreurs['default']['messages']['email'][0] ?? '';
    }

    public function test_the_message_is_identical_for_a_known_and_an_unknown_email(): void
    {
        $this->echouer('camille@example.test', 5);
        $this->echouer('inconnu@example.test', 5);

        $connu = $this->message($this->tenter('camille@example.test'));
        $inconnu = $this->message($this->tenter('inconnu@example.test'));

        $this->assertSame(self::MESSAGE_10_MIN, $connu);
        $this->assertSame($connu, $inconnu);

        // Et le message d'un simple échec est lui aussi le même pour les deux.
        $this->assertSame(
            $this->message($this->tenter('encore-inconnu@example.test', 'x', '10.0.0.9')),
            $this->message($this->tenter('camille@example.test', 'x', '10.0.0.9')),
        );
    }

    public function test_the_block_is_lifted_after_the_delay_and_the_remaining_time_shrinks(): void
    {
        $this->echouer('camille@example.test', 5);

        Carbon::setTestNow('2026-10-04 10:05:00');
        $this->tenter('camille@example.test', 'password')
            ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Réessayez dans 5 minutes.']);

        Carbon::setTestNow('2026-10-04 10:10:01');
        $this->tenter('camille@example.test', 'password')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertAuthenticatedAs($this->camille);
    }

    public function test_normal_use_is_not_hindered(): void
    {
        $this->echouer('camille@example.test', 2);

        $this->tenter('camille@example.test', 'password')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertAuthenticatedAs($this->camille);

        // Le succès a remis à zéro la clé e-mail + IP : 4 nouveaux échecs ne bloquent pas encore.
        auth()->logout();
        $this->echouer('camille@example.test', 4);
        $this->tenter('camille@example.test', 'password')->assertSessionHasNoErrors();
    }

    public function test_emails_are_normalised_before_counting(): void
    {
        $this->echouer('CAMILLE@Example.test', 3);
        $this->echouer('camille@example.test', 2);

        $this->tenter('camille@example.test', 'password')->assertSessionHasErrors(['email' => self::MESSAGE_10_MIN]);
        $this->assertSame('camille@example.test', \App\Services\SecuriteConnexion::normaliser('  CAMILLE@Example.test '));
    }

    // --- IP seule : 20 échecs en 10 minutes ---

    public function test_twenty_failures_from_one_ip_block_that_ip_whatever_the_email(): void
    {
        foreach (range(1, 20) as $i) {
            $this->echouer("cible{$i}@example.test", 1, '10.0.0.7');
        }

        $this->tenter('camille@example.test', 'password', '10.0.0.7')
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Une autre IP n'est pas concernée.
        $this->tenter('camille@example.test', 'password', '10.0.0.8')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->camille);

        // Le blocage est temporaire : levé après 10 minutes.
        auth()->logout();
        Carbon::setTestNow('2026-10-04 10:10:01');
        $this->tenter('camille@example.test', 'password', '10.0.0.7')->assertSessionHasNoErrors();
    }

    public function test_the_ip_limit_can_be_disabled_from_the_environment(): void
    {
        config(['securite.limite_ip' => false]);

        foreach (range(1, 22) as $i) {
            $this->echouer("cible{$i}@example.test", 1, '10.0.0.7');
        }

        $this->tenter('camille@example.test', 'password', '10.0.0.7')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->camille);
    }

    // --- E-mail seul : 15 échecs en 15 minutes ---

    public function test_fifteen_failures_on_one_email_from_different_ips_block_that_email(): void
    {
        foreach (range(1, 15) as $i) {
            $this->echouer('camille@example.test', 1, '10.0.1.'.$i);
        }

        $this->tenter('camille@example.test', 'password', '10.0.1.200')
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Un autre compte, depuis la même IP neuve, n'est pas gêné.
        $autre = User::factory()->create(['email' => 'autre@example.test']);
        $this->tenter('autre@example.test', 'password', '10.0.1.200')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($autre);

        auth()->logout();
        Carbon::setTestNow('2026-10-04 10:15:01');
        $this->tenter('camille@example.test', 'password', '10.0.1.200')->assertSessionHasNoErrors();
    }

    public function test_a_success_clears_only_the_email_and_ip_key(): void
    {
        // 14 échecs sur l'e-mail depuis 14 IP différentes, puis un succès depuis une 15e : la clé « e-mail seul » reste à 14.
        foreach (range(1, 14) as $i) {
            $this->echouer('camille@example.test', 1, '10.0.2.'.$i);
        }
        $this->tenter('camille@example.test', 'password', '10.0.2.100')->assertSessionHasNoErrors();
        auth()->logout();

        // Un 15e échec atteint la limite « e-mail seul » : la tentative suivante est bloquée, malgré le succès intermédiaire.
        $this->echouer('camille@example.test', 1, '10.0.2.101');
        $this->tenter('camille@example.test', 'password', '10.0.2.102')->assertSessionHasErrors('email');
    }

    // --- Événements de sécurité ---

    public function test_every_failure_and_every_block_creates_an_event_without_secrets(): void
    {
        $this->echouer('camille@example.test', 5);

        $this->assertSame(5, EvenementSecurite::where('type', TypeEvenementSecurite::EchecConnexion->value)->count());
        $this->assertSame(1, EvenementSecurite::where('type', TypeEvenementSecurite::Blocage->value)->count());

        $echec = EvenementSecurite::where('type', TypeEvenementSecurite::EchecConnexion->value)->first();
        $this->assertSame('c***@e***.test', $echec->email_masque);
        $this->assertSame('10.0.0.1', $echec->ip);
        $this->assertSame('login', $echec->route);
        $this->assertSame($this->camille->id, $echec->user_id);

        $contenu = EvenementSecurite::all()->toJson(JSON_UNESCAPED_UNICODE);
        foreach (['camille@example.test', 'mauvais', 'password'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $contenu, $interdit);
        }

        // Un e-mail inconnu : événement sans compte.
        $this->echouer('inconnu@example.test', 1);
        $this->assertNull(EvenementSecurite::latest('id')->first()->user_id);
    }

    public function test_an_ip_block_and_an_email_block_each_create_a_block_event(): void
    {
        foreach (range(1, 20) as $i) {
            $this->echouer("cible{$i}@example.test", 1, '10.0.0.7');
        }
        $this->assertSame(1, EvenementSecurite::where('type', 'blocage')->where('detail', 'like', 'adresse IP%')->count());

        foreach (range(1, 15) as $i) {
            $this->echouer('camille@example.test', 1, '10.0.3.'.$i);
        }
        $this->assertSame(1, EvenementSecurite::where('type', 'blocage')->where('detail', 'like', 'e-mail :%')->count());
    }

    public function test_email_masking(): void
    {
        $this->assertSame('j***@d***.fr', JournalSecurite::masquerEmail('jean@dupont.fr'));
        $this->assertSame('c***@m***.test', JournalSecurite::masquerEmail(' Camille@mail.example.test '));
        $this->assertSame('a***@l***', JournalSecurite::masquerEmail('a@localhost'));
        $this->assertSame('***', JournalSecurite::masquerEmail('pas-une-adresse'));
        $this->assertNull(JournalSecurite::masquerEmail(null));
        $this->assertNull(JournalSecurite::masquerEmail('  '));
    }
}
