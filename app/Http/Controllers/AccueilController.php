<?php

namespace App\Http\Controllers;

use App\Enums\Disponibilite;
use App\Models\Service;
use App\Services\AlertesActives;
use Illuminate\View\View;

/** Page d'accueil publique : chiffres et extraits lus en base (jamais l'API), sans donnée personnelle. */
class AccueilController extends Controller
{
    public function __invoke(AlertesActives $alertes): View
    {
        // Une requête pour tous les services actifs ; les comptages et extraits se font en mémoire.
        $services = Service::actifs()->parPrioriteEtEtat()->get();
        $urgences = Service::actifs()->urgences()->get();

        return view('welcome', [
            'nbServices' => $services->count(),
            'nbDisponibles' => $services->filter(fn (Service $s) => $s->disponibilite === Disponibilite::Disponible)->count(),
            'alertes' => $alertes->get(),
            'nbAlertes' => $alertes->get()->count(),
            'nbUrgences' => $urgences->count(),
            'interrompus' => $services->filter(fn (Service $s) => $s->disponibilite !== Disponibilite::Disponible)->take(2),
            'alaUne' => $services->sortBy([['prioritaire', 'desc']])->take(4),
            'urgences' => $urgences->take(2),
            // Numéro d'urgence national à 2 ou 3 chiffres, s'il est renseigné sur un service d'urgence.
            'numeroUrgence' => $urgences->pluck('telephone')->first(fn ($t) => preg_match('/^\d{2,3}$/', (string) $t)),
        ]);
    }
}
