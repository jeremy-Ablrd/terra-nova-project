<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** F69 : en-têtes de sécurité et CSP (trois modes), sessions, mots de passe, pages d'erreur sobres, diagnostic des proxys. */
class SecuritePlateformeTest extends TestCase
{
    use RefreshDatabase;

    private function csp($reponse): string
    {
        return (string) $reponse->headers->get('Content-Security-Policy');
    }

    // --- En-têtes ---

    public function test_security_headers_are_sent_on_public_and_authenticated_pages(): void
    {
        foreach ([$this->get('/services'), $this->actingAs(User::factory()->create())->get('/espace')] as $reponse) {
            $reponse->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('X-Frame-Options', 'DENY');
            $this->assertStringContainsString('camera=()', $reponse->headers->get('Permissions-Policy'));
            $this->assertStringContainsString('geolocation=()', $reponse->headers->get('Permissions-Policy'));
        }
    }

    public function test_the_csp_has_no_third_party_domain_and_forbids_framing(): void
    {
        $csp = $this->csp($this->get('/services'));

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertDoesNotMatchRegularExpression('~https?://|\*~', $csp);

        // Scripts : même origine seulement, jamais en ligne ; unsafe-eval documenté (Alpine).
        $this->assertStringContainsString("script-src 'self' 'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-eval' 'unsafe-inline'", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $csp);
        // Styles : même origine et jeton, jamais 'unsafe-inline'.
        $this->assertDoesNotMatchRegularExpression("/style-src[^;]*'unsafe-inline'/", $csp);
        $this->assertMatchesRegularExpression("/style-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $csp);
    }

    public function test_the_nonce_in_the_header_matches_the_inline_style_of_the_page(): void
    {
        // Sans le mode économie de données, le style en ligne est dans <noscript>, avec le jeton de l'en-tête.
        $normal = $this->get('/services')->assertOk();
        preg_match("/'nonce-([^']+)'/", $this->csp($normal), $m);
        $this->assertMatchesRegularExpression('~<noscript><style\s+nonce="'.preg_quote($m[1], '~').'"\s*>~', $normal->getContent());

        // Avec Save-Data (en-tête conservé pour la suite du test), le style est affiché directement, avec son propre jeton.
        $economie = $this->withHeader('Save-Data', 'on')->get('/services')->assertOk();
        preg_match("/'nonce-([^']+)'/", $this->csp($economie), $m2);
        $this->assertMatchesRegularExpression('~<style\s+nonce="'.preg_quote($m2[1], '~').'"\s*>~', $economie->getContent());
        $this->assertNotSame($m[1], $m2[1]); // un jeton neuf à chaque réponse
    }

    public function test_hsts_only_in_production_over_https_for_one_day_without_subdomains(): void
    {
        $this->get('https://localhost/services')->assertHeaderMissing('Strict-Transport-Security'); // environnement de test

        $this->app->detectEnvironment(fn () => 'production');

        $this->get('http://localhost/services')->assertHeaderMissing('Strict-Transport-Security');

        $hsts = $this->get('https://localhost/services')->headers->get('Strict-Transport-Security');
        $this->assertSame('max-age=86400', $hsts);
        $this->assertStringNotContainsString('includeSubDomains', $hsts);
        $this->assertStringNotContainsString('preload', $hsts);
    }

    // --- Modes de la CSP (NOVATERRA_CSP_MODE) ---

    public function test_default_mode_enforces_the_csp(): void
    {
        $reponse = $this->get('/services');

        $this->assertNotSame('', $this->csp($reponse));
        $reponse->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_report_only_mode_sends_the_report_only_header_and_nothing_enforced(): void
    {
        config(['securite.csp_mode' => 'report-only']);

        $reponse = $this->get('/services')->assertOk();

        $reponse->assertHeader('Content-Security-Policy-Report-Only');
        $reponse->assertHeaderMissing('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'unsafe-eval'", $reponse->headers->get('Content-Security-Policy-Report-Only'));
        $reponse->assertHeader('X-Content-Type-Options', 'nosniff'); // les autres en-têtes restent
    }

    public function test_off_mode_sends_no_csp_header_at_all(): void
    {
        config(['securite.csp_mode' => 'off']);

        $reponse = $this->get('/services')->assertOk();

        $reponse->assertHeaderMissing('Content-Security-Policy');
        $reponse->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $reponse->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_an_unknown_mode_falls_back_to_enforce(): void
    {
        config(['securite.csp_mode' => 'nimporte']);

        $this->assertNotSame('', $this->csp($this->get('/services')));
    }

    // --- Compatibilité avec la CSP : ni style en ligne, ni gestionnaire d'événement en ligne ---

    public function test_no_static_inline_style_attribute_and_no_inline_event_handler_in_views(): void
    {
        $fautes = [];
        foreach (File::allFiles(resource_path('views')) as $fichier) {
            $texte = File::get($fichier->getPathname());
            if (preg_match('/\sstyle\s*=/i', $texte)) {
                $fautes[] = $fichier->getRelativePathname().' : attribut style=""';
            }
            if (preg_match('/\son(click|submit|change|load|input|keydown|keyup|focus|blur|mouse\w+)\s*=/i', $texte)) {
                $fautes[] = $fichier->getRelativePathname().' : gestionnaire d\'événement en ligne';
            }
        }

        $this->assertSame([], $fautes);
    }

    public function test_logout_is_a_real_submit_button_that_works_without_javascript(): void
    {
        $page = $this->actingAs(User::factory()->create())->get('/espace')->getContent();

        $this->assertSame(2, preg_match_all('~<form method="POST" action="[^"]*/logout">\s*<input type="hidden" name="_token"[^>]*>\s*<button type="submit"~', $page)); // bureau et mobile
        $this->assertStringNotContainsString('onclick', $page);
    }

    // --- Sessions ---

    public function test_the_session_cookie_is_http_only_lax_and_regenerated_at_login(): void
    {
        $user = User::factory()->create();
        $this->startSession();
        $avant = session()->getId();

        $reponse = $this->withCookie(session()->getName(), $avant)->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $cookie = $reponse->getCookie(session()->getName(), false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNotSame($avant, $reponse->getCookie(session()->getName())->getValue()); // identifiant régénéré
    }

    public function test_the_session_cookie_is_secure_by_default_in_production(): void
    {
        $config = File::get(config_path('session.php'));

        $this->assertStringContainsString("env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')", $config);
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    // --- Mots de passe ---

    public function test_registration_refuses_nine_characters_and_accepts_ten(): void
    {
        $this->post('/register', ['name' => 'Court', 'email' => 'court@example.test', 'password' => '123456789', 'password_confirmation' => '123456789'])
            ->assertSessionHasErrors(['password' => 'Le champ mot de passe doit contenir au moins 10 caractères.']);
        $this->assertNull(User::where('email', 'court@example.test')->first());

        $this->post('/register', ['name' => 'Assez Long', 'email' => 'long@example.test', 'password' => '1234567890', 'password_confirmation' => '1234567890'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'long@example.test')->first());
    }

    public function test_password_change_refuses_nine_characters_and_accepts_ten(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/password', ['current_password' => 'password', 'password' => '123456789', 'password_confirmation' => '123456789'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->actingAs($user)->from('/profile')->put('/password', ['current_password' => 'password', 'password' => '1234567890', 'password_confirmation' => '1234567890'])
            ->assertSessionHasNoErrors();
    }

    public function test_an_existing_account_with_an_eight_character_password_can_still_log_in(): void
    {
        $demo = User::factory()->create(['email' => 'demo@example.test', 'password' => 'abcd1234']);

        $this->post('/login', ['email' => 'demo@example.test', 'password' => 'abcd1234'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertAuthenticatedAs($demo);
    }

    public function test_no_external_service_is_asked_about_passwords(): void
    {
        \Illuminate\Support\Facades\Http::fake();

        $this->post('/register', ['name' => 'Sans Fuite', 'email' => 'sf@example.test', 'password' => 'motdepasse-2026', 'password_confirmation' => 'motdepasse-2026']);

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    // --- Pages d'erreur : aucune information technique ---

    public function test_error_pages_show_no_technical_information(): void
    {
        config(['app.debug' => false]);

        $introuvable = $this->get('/cette-page-n-existe-pas')->assertNotFound()->getContent();
        $refus = $this->actingAs(User::factory()->create())->get('/agent')->assertForbidden()->getContent();

        foreach ([$introuvable, $refus] as $page) {
            foreach (['vendor/laravel', 'Stack trace', 'Illuminate\\', base_path(), 'APP_KEY', 'DB_PASSWORD'] as $interdit) {
                $this->assertStringNotContainsString($interdit, $page, $interdit);
            }
        }
    }

    public function test_debug_is_forced_off_in_production(): void
    {
        $this->assertStringContainsString("environment('production')", File::get(app_path('Providers/AppServiceProvider.php')));
        $this->assertFalse((bool) config('app.debug', false) && app()->environment('production'));
    }

    // --- Proxys de confiance : repérage du problème ---

    public function test_trusted_proxies_are_empty_by_default_and_a_forged_forwarded_header_is_ignored(): void
    {
        $this->assertEmpty(config('securite.trusted_proxies'));

        $admin = User::factory()->admin()->create();
        $this->withHeader('X-Forwarded-For', '9.9.9.9')->actingAs($admin)->get('/admin/securite')->assertOk()
            ->assertSee('Adresse IP détectée pour votre requête : 127.0.0.1')
            ->assertSee('TRUSTED_PROXIES) : vide, aucun proxy n&#039;est approuvé', false)
            ->assertSee('role="alert"', false)
            ->assertSee('X-Forwarded-For');
    }

    public function test_the_admin_page_reports_configured_proxies_and_the_ip_limit_state(): void
    {
        $admin = User::factory()->admin()->create();
        config(['securite.trusted_proxies' => '10.0.0.1', 'securite.limite_ip' => false, 'securite.csp_mode' => 'report-only']);

        $this->actingAs($admin)->get('/admin/securite')->assertOk()
            ->assertSee('renseignés (10.0.0.1)')
            ->assertSee('Limite de connexion par adresse IP : désactivée.')
            ->assertSee('Mode de la politique de sécurité du contenu (CSP) : report-only.');

        config(['securite.limite_ip' => true]);
        $this->actingAs($admin)->get('/admin/securite')->assertSee('Limite de connexion par adresse IP : activée.');
    }
}
