<?php

namespace App\Providers;

use App\Models\Demande;
use App\Services\SuiviDemandes;
use App\Models\User;
use App\Policies\ComptePolicy;
use App\Services\AppareilsConnus;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mots de passe : au moins 10 caractères à l'inscription et au changement (jamais à la connexion), sans service externe.
        Password::defaults(fn () => Password::min(10));

        // Gestion des comptes : ComptePolicy (administrateur seul).
        Gate::policy(User::class, ComptePolicy::class);

        // Proxys de confiance : vides par défaut (aucun proxy approuvé). TRUSTED_PROXIES=* ou une liste d'adresses.
        $proxys = config('securite.trusted_proxies');
        if (filled($proxys)) {
            TrustProxies::at($proxys === '*' ? '*' : array_map('trim', explode(',', $proxys)));
        }

        // Aucune information technique dans les pages d'erreur en production, même si APP_DEBUG est resté à true.
        if ($this->app->environment('production')) {
            config(['app.debug' => false]);
        }

        // Limites par compte, chacune avec son propre compteur : téléchargements de données (10 par minute), suppression de compte (5 par minute).
        RateLimiter::for('donnees-telechargement', fn (Request $request) => Limit::perMinute(10)->by('dl|'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('suppression-compte', fn (Request $request) => Limit::perMinute(5)->by('sup|'.($request->user()?->id ?? $request->ip())));

        // Contributions des habitants : 5 par tranche de 10 minutes et par compte (avis, idées et commentaires confondus).
        RateLimiter::for('contributions', fn (Request $request) => Limit::perMinutes(10, 5)->by('contrib|'.($request->user()?->id ?? $request->ip())));

        // Compteur D17 de la barre de navigation : calculé (une seule requête) uniquement pour un agent connecté.
        View::composer('layouts.navigation', function ($view) {
            $user = auth()->user();

            // F54 : alertes « nouvelle connexion » non vues, pour tout utilisateur connecté (une requête, partagée avec l'encart).
            $view->with('alertesConnexion', $user ? app(AppareilsConnus::class)->alertesNonVues($user)->count() : 0);

            $view->with('demandesEnAttente', $user?->isAgent() ? Demande::enAttente()->count() : null);

            // F49 : changements d'état non lus, pour un citoyen connecté seulement (une requête, partagée avec l'encadré).
            $view->with('changementsNonVus', $user?->isCitoyen() ? app(SuiviDemandes::class)->changementsNonVus($user)->count() + app(SuiviDemandes::class)->reponsesNonVues($user)->count() : 0);
        });
    }
}
