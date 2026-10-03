<?php

namespace App\Providers;

use App\Models\Demande;
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
        });
    }
}
