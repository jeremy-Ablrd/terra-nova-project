<?php

namespace App\Http\Controllers;

use App\Enums\CategorieService;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Catalogue public des services municipaux (visible sans connexion). */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        // Filtre ?categorie=sante : une valeur inconnue (ou un tableau) est ignorée → catalogue complet.
        $valeur = $request->query('categorie');
        $categorie = is_string($valeur) ? CategorieService::tryFrom($valeur) : null;

        // UNE requête (prioritaires en tête) ; le filtre et les compteurs se font sur ce résultat.
        $services = Service::actifs()->parPriorite()->get();

        $compteurs = collect(CategorieService::cases())->mapWithKeys(fn (CategorieService $c) => [
            $c->value => $services->filter(fn (Service $s) => $s->categorie === $c)->count(),
        ]);

        return view('services.index', [
            'categorie' => $categorie,
            'compteurs' => $compteurs,
            'total' => $services->count(),
            'services' => $categorie ? $services->filter(fn (Service $s) => $s->categorie === $categorie)->values() : $services,
        ]);
    }

    public function show(Service $service): View
    {
        abort_unless($service->actif, 404);

        return view('services.show', ['service' => $service]);
    }
}
