<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage : ->middleware('role:agent') ou ->middleware('role:rôle1,rôle2'). Invité → connexion ; mauvais rôle → 403.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        abort_unless(in_array($user->role->value, $roles, true), 403);

        return $next($request);
    }
}
