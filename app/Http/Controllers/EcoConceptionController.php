<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/** Page publique d'éco-conception (F57 à F60) : affiche les mesures de resources/data/mesures-poids.json. */
class EcoConceptionController extends Controller
{
    public function index(): View
    {
        $chemin = resource_path('data/mesures-poids.json');
        $mesures = File::exists($chemin) ? File::json($chemin) : [];

        $avant = $mesures['avant'] ?? null;
        $apres = $mesures['apres'] ?? null;
        $sansRegles = $mesures['apres_sans_regles'] ?? null;

        // Une ligne par page, avec les valeurs avant et après (lues dans le fichier, jamais écrites à la main).
        $lignes = collect($apres['pages'] ?? $avant['pages'] ?? [])->map(function (array $page) use ($avant, $apres, $sansRegles) {
            $trouve = fn (?array $mesure) => collect($mesure['pages'] ?? [])->first(
                fn ($p) => $p['chemin'] === $page['chemin'] && $p['role'] === $page['role']
            );

            return ['chemin' => $page['chemin'], 'role' => $page['role'], 'avant' => $trouve($avant), 'apres' => $trouve($apres), 'sans_regles' => $trouve($sansRegles)];
        });

        return view('eco-conception', [
            'avant' => $avant,
            'apres' => $apres,
            'sansRegles' => $sansRegles,
            'lignes' => $lignes,
            'budget' => config('eco.budget'),
        ]);
    }
}
