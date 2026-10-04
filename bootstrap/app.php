<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\ProtegerFormulaire;
use App\Services\JournalSecurite;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Http\Middleware\SobrieteReponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureUserHasRole::class, 'formulaire' => ProtegerFormulaire::class]);
        // Les refus et les renvois de jeton sont traités AVANT les limites de débit : ils n'en consomment pas.
        $middleware->prependToPriorityList(before: \Illuminate\Routing\Middleware\ThrottleRequests::class, prepend: ProtegerFormulaire::class);
        $middleware->web(append: [SobrieteReponse::class, EnTetesSecurite::class]);
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeUrl());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // F70 : chaque refus 403 sur /agent/* ou /admin/* laisse une trace (plafonnée par utilisateur) ; la page 403 reste inchangée.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 403 && $request->user() && $request->is('agent', 'agent/*', 'admin', 'admin/*')) {
                try {
                    app(JournalSecurite::class)->accesRefuse($request);
                } catch (\Throwable) {
                    // la trace ne doit jamais empêcher d'afficher le refus
                }
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
