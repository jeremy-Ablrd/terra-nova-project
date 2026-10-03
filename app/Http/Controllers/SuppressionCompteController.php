<?php

namespace App\Http\Controllers;

use App\Services\SuppressionCompte;
use App\Services\SuppressionRefusee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * F33 : suppression de son compte en deux étapes, sans JavaScript. Citoyen seulement (role:citoyen).
 * Étape 1 : ce qui sera supprimé et conservé. Étape 2 : mot de passe + case à cocher. Le travail est fait par SuppressionCompte.
 */
class SuppressionCompteController extends Controller
{
    public function information(): View
    {
        return view('mes-donnees.suppression');
    }

    public function confirmation(): View
    {
        return view('mes-donnees.suppression-confirmer');
    }

    public function destroy(Request $request, SuppressionCompte $suppression): RedirectResponse
    {
        $validateur = Validator::make($request->all(), [
            'password' => ['required', 'current_password'],
            'comprends' => ['accepted'],
        ], [
            'password.required' => __('Saisissez votre mot de passe.'),
            'comprends.accepted' => __('Cochez la case pour confirmer que vous avez compris.'),
        ], ['password' => __('mot de passe')]);

        if ($validateur->fails()) {
            return redirect()->route('mes-donnees.suppression.confirmer')->withErrors($validateur);
        }

        try {
            $suppression->supprimer($request->user());
        } catch (SuppressionRefusee $e) {
            return redirect()->route('mes-donnees.suppression')->withErrors(['compte' => $e->getMessage()]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('compte-supprime');
    }

    /** Page publique affichée après la suppression (l'utilisateur n'est plus connecté). */
    public function termine(): View
    {
        return view('mes-donnees.supprime');
    }
}
