<?php

namespace App\Providers;

use App\Models\Demande;
use App\Services\SuiviDemandes;
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
        // Compteur D17 de la barre de navigation : calculé (une seule requête) uniquement pour un agent connecté.
        View::composer('layouts.navigation', function ($view) {
            $user = auth()->user();

            $view->with('demandesEnAttente', $user?->isAgent() ? Demande::enAttente()->count() : null);

            // F49 : changements d'état non lus, pour un citoyen connecté seulement (une requête, partagée avec l'encadré).
            $view->with('changementsNonVus', $user?->isCitoyen() ? app(SuiviDemandes::class)->changementsNonVus($user)->count() : 0);
        });
    }
}
