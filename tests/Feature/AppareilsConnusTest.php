<?php

namespace Tests\Feature;

use App\Enums\TypeEvenementSecurite;
use App\Models\AppareilConnu;
use App\Models\EvenementSecurite;
use App\Models\User;
use App\Services\AppareilsConnus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** F54 : appareils connus et alerte « nouvelle connexion » (sans e-mail). */
class AppareilsConnusTest extends TestCase
{
    use RefreshDatabase;

    private const FIREFOX = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0';

    private const CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36';

    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 10:00:00');
        Cache::flush();
        Model::preventLazyLoading();
        $this->camille = User::factory()->create(['email' => 'camille@example.test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    /** Connexion depuis un navigateur ; $jeton : valeur du cookie d'appareil de ce navigateur (null : navigateur neuf). */
    private function connecter(User $user, string $agent = self::FIREFOX, ?string $jeton = null)
    {
        auth()->logout();
        $requete = $this->withHeader('User-Agent', $agent);
        if ($jeton !== null) {
            $requete = $requete->withCookie(AppareilsConnus::COOKIE, $jeton);
        }

        return $requete->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    private function jeton($reponse): string
    {
        return $reponse->getCookie(AppareilsConnus::COOKIE)->getValue();
    }

    // --- Enregistrement des appareils ---

    public function test_the_first_device_of_an_account_triggers_nothing(): void
    {
        $reponse = $this->connecter($this->camille)->assertRedirect();

        $this->assertSame(1, AppareilConnu::where('user_id', $this->camille->id)->count());
        $this->assertSame(0, EvenementSecurite::where('type', 'nouvel_appareil')->count());
        $this->actingAs($this->camille)->get('/espace')->assertDontSee('Nouvelle connexion le');
        $this->assertNotNull($this->jeton($reponse));
    }

    public function test_the_device_cookie_is_http_only_lax_one_year_and_only_its_hash_is_stored(): void
    {
        $reponse = $this->connecter($this->camille);

        $cookie = $reponse->getCookie(AppareilsConnus::COOKIE, false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertFalse($cookie->isSecure()); // Secure uniquement en production
        $this->assertEqualsWithDelta(now()->addYear()->getTimestamp(), $cookie->getExpiresTime(), 120);

        $jeton = $this->jeton($reponse);
        $this->assertSame(40, strlen($jeton));
        $appareil = AppareilConnu::firstOrFail();
        $this->assertSame(hash('sha256', $jeton), $appareil->jeton_hash);
        $this->assertStringNotContainsString($jeton, json_encode($appareil->toArray()));
        $this->assertSame('Firefox sur Windows', $appareil->libelle);
    }

    public function test_the_device_cookie_is_secure_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $requete = \Illuminate\Http\Request::create('/login', 'POST', [], [], [], ['HTTP_USER_AGENT' => self::FIREFOX]);
        app(AppareilsConnus::class)->enregistrerConnexion($requete, $this->camille);

        $cookie = collect(\Illuminate\Support\Facades\Cookie::getQueuedCookies())->first(fn ($c) => $c->getName() === AppareilsConnus::COOKIE);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_a_known_device_only_updates_its_last_seen_date(): void
    {
        $jeton = $this->jeton($this->connecter($this->camille));

        Carbon::setTestNow('2026-10-05 08:30:00');
        $this->connecter($this->camille, self::FIREFOX, $jeton)->assertRedirect();

        $this->assertSame(1, AppareilConnu::count());
        $this->assertSame(0, EvenementSecurite::where('type', 'nouvel_appareil')->count());
        $appareil = AppareilConnu::firstOrFail();
        $this->assertSame('2026-10-04 10:00:00', $appareil->premiere_vue_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 08:30:00', $appareil->derniere_vue_at->format('Y-m-d H:i:s'));
    }

    public function test_a_new_device_on_an_account_that_has_one_creates_the_event(): void
    {
        $this->connecter($this->camille);

        Carbon::setTestNow('2026-10-04 15:20:00');
        $this->connecter($this->camille, self::CHROME_ANDROID);

        $this->assertSame(2, AppareilConnu::where('user_id', $this->camille->id)->count());
        $evenement = EvenementSecurite::where('type', 'nouvel_appareil')->firstOrFail();
        $this->assertSame($this->camille->id, $evenement->user_id);
        $this->assertSame('Chrome sur Android', $evenement->detail);
        $this->assertSame('c***@e***.test', $evenement->email_masque);
        $this->assertNotNull($evenement->appareil_id);
        $this->assertNull($evenement->vu_at);
    }

    public function test_registration_creates_the_first_device_without_alert(): void
    {
        $this->post('/register', ['name' => 'Nouvelle Personne', 'email' => 'nouvelle@example.test', 'password' => 'motdepasse-2026', 'password_confirmation' => 'motdepasse-2026'])
            ->assertRedirect();

        $nouvelle = User::where('email', 'nouvelle@example.test')->firstOrFail();
        $this->assertSame(1, AppareilConnu::where('user_id', $nouvelle->id)->count());
        $this->assertSame(0, EvenementSecurite::where('type', 'nouvel_appareil')->count());
    }

    public function test_the_same_browser_can_serve_two_accounts_without_false_alerts(): void
    {
        $autre = User::factory()->create(['email' => 'autre@example.test']);
        $jeton = $this->jeton($this->connecter($this->camille));
        $this->connecter($autre, self::FIREFOX, $jeton); // premier appareil de ce compte
        $this->connecter($this->camille, self::FIREFOX, $jeton);
        $this->connecter($autre, self::FIREFOX, $jeton);

        $this->assertSame(0, EvenementSecurite::where('type', 'nouvel_appareil')->count());
        $this->assertSame(2, AppareilConnu::count());
    }

    // --- Alerte visible ---

    public function test_the_alert_is_visible_on_the_space_and_in_the_navigation(): void
    {
        $this->connecter($this->camille);
        Carbon::setTestNow('2026-10-04 15:20:00');
        $this->connecter($this->camille, self::CHROME_ANDROID);

        $page = $this->actingAs($this->camille)->get('/espace')->assertOk()
            ->assertSee('Nouvelle connexion le 04/10 à 15:20 depuis Chrome sur Android')
            ->assertSee('role="status"', false)
            ->assertSee('C&#039;était moi', false)
            ->assertSee('Ce n&#039;était pas moi', false)
            ->assertSee('1 alerte de connexion')
            ->assertSee(route('mes-connexions.index'))
            ->getContent();

        $this->assertMatchesRegularExpression('/<form method="POST" action="[^"]*\/vu"/', $page); // POST, sans JavaScript
    }

    public function test_the_alert_also_shows_on_agent_and_admin_homes_and_counts_exactly(): void
    {
        $agent = User::factory()->agent()->create();
        $this->connecter($agent);
        $this->connecter($agent, self::CHROME_ANDROID);
        $this->connecter($agent, self::FIREFOX.' autre');

        $this->actingAs($agent)->get('/agent')->assertSee('2 alertes de connexion')->assertSee('depuis Chrome sur Android');

        $admin = User::factory()->admin()->create();
        $this->connecter($admin);
        $this->connecter($admin, self::CHROME_ANDROID);
        $this->actingAs($admin)->get('/admin')->assertSee('1 alerte de connexion');
    }

    public function test_it_was_me_closes_the_alert_and_keeps_the_device(): void
    {
        $this->connecter($this->camille);
        $this->connecter($this->camille, self::CHROME_ANDROID);
        $evenement = EvenementSecurite::where('type', 'nouvel_appareil')->firstOrFail();

        $this->actingAs($this->camille)->post(route('mes-connexions.vu', $evenement))->assertRedirect();

        $this->assertNotNull($evenement->fresh()->vu_at);
        $this->assertSame(2, AppareilConnu::count());
        $this->actingAs($this->camille)->get('/espace')->assertDontSee('Nouvelle connexion le')->assertDontSee('alerte de connexion');
    }

    public function test_it_was_not_me_logs_out_other_sessions_forgets_unknown_devices_and_sends_to_the_password(): void
    {
        $jeton = $this->jeton($this->connecter($this->camille));
        $this->connecter($this->camille, self::CHROME_ANDROID); // l'intrus
        $evenement = EvenementSecurite::where('type', 'nouvel_appareil')->firstOrFail();
        $inconnu = AppareilConnu::findOrFail($evenement->appareil_id);
        $ancienJetonSouvenir = $this->camille->fresh()->remember_token;

        $voisin = User::factory()->create();
        $this->startSession();
        $courante = session()->getId();
        foreach ([$courante, 'autre-session-1', 'autre-session-2'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $this->camille->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);
        }
        DB::table('sessions')->insert(['id' => 'session-du-voisin', 'user_id' => $voisin->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);

        $this->withCookie(session()->getName(), $courante)->actingAs($this->camille)->post(route('mes-connexions.pas-moi', $evenement))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('securite', fn ($m) => str_contains($m, 'Changez maintenant votre mot de passe'));

        $this->assertSame([$courante], DB::table('sessions')->where('user_id', $this->camille->id)->pluck('id')->all());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $voisin->id)->count()); // les sessions d'un autre ne sont pas touchées
        $this->assertNull(AppareilConnu::find($inconnu->id));
        $this->assertSame(1, AppareilConnu::where('user_id', $this->camille->id)->count()); // l'appareil de confiance reste
        $this->assertSame(hash('sha256', $jeton), AppareilConnu::where('user_id', $this->camille->id)->value('jeton_hash'));
        $this->assertNotNull($evenement->fresh()->vu_at);
        $this->assertNotSame($ancienJetonSouvenir, $this->camille->fresh()->remember_token);

        $this->actingAs($this->camille)->get(route('profile.edit'))->assertOk()->assertSee('Changez maintenant votre mot de passe');
    }

    public function test_nobody_can_answer_someone_elses_alert_or_forget_someone_elses_device(): void
    {
        $this->connecter($this->camille);
        $this->connecter($this->camille, self::CHROME_ANDROID);
        $evenement = EvenementSecurite::where('type', 'nouvel_appareil')->firstOrFail();
        $appareil = AppareilConnu::firstOrFail();
        $intrus = User::factory()->create();

        $this->actingAs($intrus)->post(route('mes-connexions.vu', $evenement))->assertForbidden();
        $this->actingAs($intrus)->post(route('mes-connexions.pas-moi', $evenement))->assertForbidden();
        $this->actingAs($intrus)->delete(route('mes-connexions.oublier', $appareil))->assertForbidden();

        $this->assertNull($evenement->fresh()->vu_at);
        $this->assertNotNull($appareil->fresh());

        // Un événement d'un autre type ne se « répond » pas.
        $echec = EvenementSecurite::forceCreate(['type' => 'echec_connexion', 'user_id' => $this->camille->id, 'ip' => '1.1.1.1', 'created_at' => now()]);
        $this->actingAs($this->camille)->post(route('mes-connexions.vu', $echec))->assertForbidden();
    }

    // --- Page « Mes connexions » ---

    public function test_the_connections_page_lists_my_devices_and_lets_me_forget_one(): void
    {
        $jeton = $this->jeton($this->connecter($this->camille));
        Carbon::setTestNow('2026-10-06 09:00:00');
        $this->connecter($this->camille, self::CHROME_ANDROID);
        $autre = AppareilConnu::where('libelle', 'Chrome sur Android')->firstOrFail();

        $page = $this->withCookie(AppareilsConnus::COOKIE, $jeton)->actingAs($this->camille)->get('/mes-connexions')->assertOk()
            ->assertSee('Firefox sur Windows')->assertSee('Chrome sur Android')
            ->assertSee('04/10/2026 10:00')->assertSee('06/10/2026 09:00')
            ->assertSee('Cet appareil')
            ->assertSee('Oublier cet appareil')
            ->assertSee('Me déconnecter partout')
            ->assertSee('<caption', false)
            ->getContent();
        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));

        $this->actingAs($this->camille)->delete(route('mes-connexions.oublier', $autre))->assertRedirect();
        $this->assertNull(AppareilConnu::find($autre->id));
        $this->assertSame(1, AppareilConnu::count());
    }

    public function test_log_out_everywhere_keeps_the_current_session_only(): void
    {
        $this->startSession();
        $courante = session()->getId();
        foreach ([$courante, 'ailleurs-1', 'ailleurs-2'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $this->camille->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);
        }

        $this->withCookie(session()->getName(), $courante)->actingAs($this->camille)->post(route('mes-connexions.deconnexion'))->assertRedirect();

        $this->assertSame([$courante], DB::table('sessions')->where('user_id', $this->camille->id)->pluck('id')->all());
    }

    public function test_the_connections_page_is_for_every_role_and_closed_to_guests(): void
    {
        foreach ([$this->camille, User::factory()->agent()->create(), User::factory()->admin()->create()] as $utilisateur) {
            $this->actingAs($utilisateur)->get('/mes-connexions')->assertOk()->assertSee('Mes connexions');
        }

        auth()->logout();
        $this->get('/mes-connexions')->assertRedirect(route('login'));
        $this->post(route('mes-connexions.deconnexion'))->assertRedirect(route('login'));
    }

    // --- Libellé et requêtes ---

    public function test_labels_come_from_the_user_agent(): void
    {
        $cas = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/129.0 Safari/537.36 Edg/129.0' => 'Edge sur Windows',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/129.0 Safari/537.36' => 'Chrome sur Windows',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15' => 'Safari sur macOS',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile Safari/604.1' => 'Safari sur iOS',
            'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0' => 'Firefox sur Linux',
            self::FIREFOX => 'Firefox sur Windows',
            '' => 'Navigateur inconnu sur système inconnu',
        ];

        foreach ($cas as $agent => $attendu) {
            $this->assertSame($attendu, AppareilsConnus::libelle($agent), $agent);
        }
    }

    public function test_pages_run_a_constant_number_of_alert_queries(): void
    {
        $this->connecter($this->camille);
        $this->connecter($this->camille, self::CHROME_ANDROID);
        $compter = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->camille)->get($url)->assertOk();
            $n = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'evenements_securite'))->count();
            DB::disableQueryLog();

            return $n;
        };

        $this->assertSame(1, $compter('/espace')); // l'encart et le compteur partagent une seule requête
        $this->assertSame(1, $compter('/mes-demandes'));
        $this->assertSame(1, $compter('/mes-connexions'));

        // Aucun visiteur : aucune requête.
        auth()->logout();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/services')->assertOk();
        $this->assertSame(0, collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'evenements_securite'))->count());
    }
}
