<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité (F69) : nosniff, Referrer-Policy, Permissions-Policy, interdiction d'inclusion en iframe,
 * HSTS (production et HTTPS seulement, 24 h) et Content-Security-Policy sans domaine tiers.
 *
 * Mode de la CSP : NOVATERRA_CSP_MODE = enforce (défaut), report-only ou off (config/securite.php).
 *
 * 'unsafe-eval' dans script-src : Alpine (menus, formulaires) évalue ses expressions avec new Function. Sans lui les menus
 * ne marchent plus. Les scripts en ligne restent interdits (aucun 'unsafe-inline' pour les scripts) et aucun domaine tiers
 * n'est autorisé. Remède complet : le build CSP d'Alpine, qui demande de réécrire les expressions des menus.
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        // Jeton à usage unique par réponse : autorise les rares balises <style> en ligne (repli sans JavaScript).
        $nonce = base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $response->headers->set('X-Frame-Options', 'DENY');

        // Domaine d'un concours : durée courte, sans includeSubDomains ni preload.
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=86400');
        }

        $this->contentSecurityPolicy($response, $nonce);

        return $response;
    }

    private function contentSecurityPolicy(Response $response, string $nonce): void
    {
        $mode = config('securite.csp_mode');
        if ($mode === 'off') {
            return;
        }

        // Serveur Vite de développement (public/hot) : ses styles injectés et son rechargement à chaud n'ont pas de jeton.
        // Jamais présent en production : aucune CSP tant que le serveur de développement tourne.
        if (is_file(public_path('hot'))) {
            return;
        }

        $politique = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $response->headers->set($mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', $politique);
    }
}
