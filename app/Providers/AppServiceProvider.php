<?php

namespace App\Providers;

use App\Models\Demande;
use App\Services\SuiviDemandes;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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
        // Limites par compte, chacune avec son propre compteur : téléchargements de données (10 par minute), suppression de compte (5 par minute).
        RateLimiter::for('donnees-telechargement', fn (Request $request) => Limit::perMinute(10)->by('dl|'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('suppression-compte', fn (Request $request) => Limit::perMinute(5)->by('sup|'.($request->user()?->id ?? $request->ip())));

        // Compteur D17 de la barre de navigation : calculé (une seule requête) uniquement pour un agent connecté.
        View::composer('layouts.navigation', function ($view) {
            $user = auth()->user();

            $view->with('demandesEnAttente', $user?->isAgent() ? Demande::enAttente()->count() : null);

            // F49 : changements d'état non lus, pour un citoyen connecté seulement (une requête, partagée avec l'encadré).
            $view->with('changementsNonVus', $user?->isCitoyen() ? app(SuiviDemandes::class)->changementsNonVus($user)->count() : 0);
        });
    }
}
