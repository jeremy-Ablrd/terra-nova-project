<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAffichageRequest;
use App\Support\Affichage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** Mémorise la taille du texte et le thème : cookie pour tous, compte en plus pour un utilisateur connecté. */
class PreferenceAffichageController extends Controller
{
    private const COOKIES = ['taille' => Affichage::COOKIE_TAILLE, 'theme' => Affichage::COOKIE_THEME];

    public function update(UpdateAffichageRequest $request): RedirectResponse
    {
        $choix = collect($request->validated())->filter(fn ($valeur) => $valeur !== null);

        foreach ($choix as $cle => $valeur) {
            Cookie::queue(self::COOKIES[$cle], $valeur, Affichage::COOKIE_MINUTES);
        }

        if ($choix->isNotEmpty() && ($user = $request->user())) {
            // Hors fillable : assigné explicitement. On conserve les autres préférences du compte.
            $user->preferences = array_merge($user->preferences ?? [], $choix->all());
            $user->save();
        }

        return redirect()->to($this->retour($request));
    }

    /** Retour à la page d'où vient la demande, jamais vers un autre site. */
    private function retour(Request $request): string
    {
        $precedent = url()->previous();

        return parse_url($precedent, PHP_URL_HOST) === $request->getHost() ? $precedent : url('/');
    }
}
