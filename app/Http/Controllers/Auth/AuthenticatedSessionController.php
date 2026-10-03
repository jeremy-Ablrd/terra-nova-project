<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AppareilsConnus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, AppareilsConnus $appareils): RedirectResponse
    {
        $request->authenticate();

        // Session régénérée à la connexion ; l'appareil est enregistré et, s'il est nouveau, l'alerte est créée (F54).
        $request->session()->regenerate();
        $appareils->enregistrerConnexion($request, $request->user());

        return redirect()
            ->intended($request->user()->homeUrl())
            ->with('success', 'Bon retour, '.$request->user()->name.' !');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
