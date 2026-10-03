<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sobriété (F58, F59) :
 *  - les pages d'un utilisateur connecté sont « private » : jamais conservées dans un cache partagé (proxy, CDN) ;
 *  - le contenu change avec l'en-tête Save-Data (mode économie de données) : la réponse l'indique par Vary.
 */
class SobrieteReponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Les téléchargements en « no-store » gardent leur en-tête.
        if ($request->user() && ! $response->headers->hasCacheControlDirective('no-store')) {
            $response->headers->set('Cache-Control', 'private, no-cache');
        }

        $response->headers->set('Vary', 'Save-Data', false);

        return $response;
    }
}
