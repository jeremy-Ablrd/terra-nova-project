<?php

namespace App\Http\Controllers;

use App\Models\AppareilConnu;
use App\Models\EvenementSecurite;
use App\Services\AppareilsConnus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** F54 : mes appareils et l'alerte « nouvelle connexion ». Pour son propre compte, quel que soit le rôle (aucun e-mail envoyé). */
class MesConnexionsController extends Controller
{
    public function index(Request $request, AppareilsConnus $appareils): View
    {
        return view('mes-connexions.index', [
            'appareils' => AppareilConnu::where('user_id', $request->user()->id)->orderByDesc('derniere_vue_at')->orderByDesc('id')->get(),
            'courant' => $appareils->empreinteCourante($request),
        ]);
    }

    /** « C'était moi » : l'alerte disparaît, l'appareil reste connu. */
    public function vu(EvenementSecurite $evenement): RedirectResponse
    {
        Gate::authorize('gerer', $evenement);

        $evenement->forceFill(['vu_at' => now()])->save();

        return back()->with('securite', __('Merci : l\'alerte est fermée et cet appareil reste enregistré.'));
    }

    /**
     * « Ce n'était pas moi » : les autres appareils et sessions sont déconnectés, les appareils inconnus récents sont oubliés,
     * puis l'utilisateur est renvoyé vers le changement de mot de passe.
     */
    public function pasMoi(Request $request, EvenementSecurite $evenement, AppareilsConnus $appareils): RedirectResponse
    {
        Gate::authorize('gerer', $evenement);
        $utilisateur = $request->user();

        DB::transaction(function () use ($utilisateur, $request, $appareils) {
            $alertes = $appareils->alertesNonVues($utilisateur);

            AppareilConnu::where('user_id', $utilisateur->id)->whereIn('id', $alertes->pluck('appareil_id')->filter())->delete();
            EvenementSecurite::whereIn('id', $alertes->pluck('id'))->update(['vu_at' => now()]);
            $appareils->deconnecterAutres($utilisateur, $request->session()->getId());
        });

        return redirect()->route('profile.edit')->with('securite',
            __('Nous avons déconnecté tous vos autres appareils et oublié les appareils inconnus. Changez maintenant votre mot de passe dans le formulaire « Mot de passe » ci-dessous.'));
    }

    public function oublier(AppareilConnu $appareil): RedirectResponse
    {
        Gate::authorize('supprimer', $appareil);

        $appareil->delete();

        return back()->with('securite', __('Appareil oublié. S\'il se reconnecte, vous en serez prévenu.'));
    }

    /** « Me déconnecter partout » : toutes les autres sessions et le jeton « se souvenir de moi ». */
    public function deconnexionGlobale(Request $request, AppareilsConnus $appareils): RedirectResponse
    {
        $appareils->deconnecterAutres($request->user(), $request->session()->getId());

        return back()->with('securite', __('Tous vos autres appareils ont été déconnectés. Cet appareil reste connecté.'));
    }
}
