<?php

namespace App\Http\Middleware;

use App\Services\JournalSecurite;
use App\Services\ProtectionFormulaires;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protège un formulaire POST (F81, F82) : `formulaire`, `formulaire:reference` (le résultat d'origine affiche une référence)
 * ou `formulaire:sans-delai` (pas de délai minimal : connexion). Les options se combinent.
 * Ordre : limite de débit, jeton signé, champ leurre, renvoi d'un jeton déjà consommé, délai minimal, puis le traitement.
 * Un refus redirige vers le formulaire avec un message neutre et les champs saisis ; il est écrit dans evenements_securite
 * (formulaire_suspect) sans contenu. Un succès consomme le jeton ; un échec de validation ne le consomme pas.
 */
class ProtegerFormulaire
{
    /** Champs jamais remis dans le formulaire après un refus. */
    private const SECRETS = ['password', 'password_confirmation', 'current_password', ProtectionFormulaires::CHAMP_JETON];

    public function __construct(private ProtectionFormulaires $protection, private JournalSecurite $journal) {}

    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        if (! $this->protection->active()) {
            return $next($request);
        }

        $cle = 'formulaires:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cle, (int) config('securite.formulaires.limite'))) {
            return $this->refuser($request, 'limite de débit', __('Trop d\'envois en peu de temps. Patientez quelques minutes avant de réessayer.'));
        }
        RateLimiter::hit($cle, (int) config('securite.formulaires.fenetre_minutes') * 60);

        $brut = $request->input(ProtectionFormulaires::CHAMP_JETON);
        $jeton = $this->protection->lire($brut);
        if ($jeton === null) {
            return $this->refuser($request, $brut === null || $brut === '' ? 'jeton absent' : 'jeton invalide');
        }
        if (filled($request->input($this->protection->nomLeurre()))) {
            return $this->refuser($request, 'champ leurre rempli');
        }
        if ($this->protection->expire($jeton)) {
            return $this->refuser($request, 'jeton expiré');
        }

        if ($deja = $this->protection->dejaFait($jeton['nonce'])) {
            return $this->dejaEnregistre($deja['url'], $options);
        }
        // `formulaire:sans-delai` (connexion) : un gestionnaire de mots de passe remplit et envoie en moins de 2 secondes ;
        // la limite de tentatives de connexion (SecuriteConnexion) protège déjà ce formulaire.
        if (! in_array('sans-delai', $options, true) && $this->protection->age($jeton) < $this->protection->delaiMinimal()) {
            return $this->refuser($request, 'envoi trop rapide');
        }

        // Double clic : le second envoi attend la fin du premier, puis retrouve son résultat.
        $verrou = $this->protection->verrou($jeton['nonce']);
        $verrou->block(10);

        try {
            if ($deja = $this->protection->dejaFait($jeton['nonce'])) {
                return $this->dejaEnregistre($deja['url'], $options);
            }

            $reponse = $next($request);

            // Succès : une redirection sans erreur de validation. Le jeton est alors consommé.
            if ($reponse instanceof RedirectResponse && ! $request->session()->has('errors')) {
                $this->protection->marquerFait($jeton['nonce'], $reponse->getTargetUrl());
            }

            return $reponse;
        } finally {
            $verrou->release();
        }
    }

    private function dejaEnregistre(string $url, array $options): RedirectResponse
    {
        return redirect($url)->with('success', in_array('reference', $options, true)
            ? __('Déjà enregistré, voici sa référence.')
            : __('Déjà enregistré.'));
    }

    private function refuser(Request $request, string $motif, ?string $message = null): RedirectResponse
    {
        try {
            $this->journal->formulaireSuspect($request, $motif);
        } catch (\Throwable) {
            // la trace ne doit jamais empêcher d'afficher le refus
        }

        return back()
            ->withInput($request->except(self::SECRETS))
            ->withErrors(['formulaire' => $message ?? __('Votre envoi n\'a pas pu être pris en compte. Réessayez dans quelques secondes.')]);
    }
}
