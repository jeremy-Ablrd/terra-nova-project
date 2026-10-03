<?php

namespace App\Http\Controllers;

use App\Enums\CategorieService;
use App\Enums\Disponibilite;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Catalogue public des services municipaux (visible sans connexion). */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        // Filtres ?categorie=sante et ?etat=desactive : une valeur inconnue (ou un tableau) est ignorée → catalogue complet.
        $valeur = $request->query('categorie');
        $categorie = is_string($valeur) ? CategorieService::tryFrom($valeur) : null;
        $valeurEtat = $request->query('etat');
        $etat = is_string($valeurEtat) ? Disponibilite::tryFrom($valeurEtat) : null;

        // UNE requête (prioritaires en tête, puis désactivés et interrompus avant les disponibles) ;
        // les filtres, les compteurs et la synthèse se font sur ce résultat.
        $services = Service::actifs()->parPrioriteEtEtat()->get();

        $dansCategorie = fn (Service $s) => $categorie === null || $s->categorie === $categorie;
        $dansEtat = fn (Service $s) => $etat === null || $s->disponibilite === $etat;

        // Chaque série de compteurs tient compte de l'autre filtre : les chiffres annoncés sont ceux qu'on obtiendra en cliquant.
        $compteurs = collect(CategorieService::cases())->mapWithKeys(fn (CategorieService $c) => [
            $c->value => $services->filter($dansEtat)->filter(fn (Service $s) => $s->categorie === $c)->count(),
        ]);
        $compteursEtat = collect(Disponibilite::cases())->mapWithKeys(fn (Disponibilite $d) => [
            $d->value => $services->filter($dansCategorie)->filter(fn (Service $s) => $s->disponibilite === $d)->count(),
        ]);

        return view('services.index', [
            'categorie' => $categorie,
            'etat' => $etat,
            'compteurs' => $compteurs,
            'compteursEtat' => $compteursEtat,
            'total' => $services->filter($dansEtat)->count(),
            'totalEtat' => $services->filter($dansCategorie)->count(),
            'synthese' => $this->synthese($services),
            'services' => $services->filter($dansCategorie)->filter($dansEtat)->values(),
        ]);
    }

    public function show(Service $service): View
    {
        abort_unless($service->actif, 404);

        return view('services.show', ['service' => $service]);
    }

    /** « 6 services disponibles, 1 interrompu, 1 désactivé » : seuls les états présents sont nommés, avec leurs accords. */
    private function synthese(Collection $services): string
    {
        $total = $services->count();
        if ($total === 0) {
            return __('synthese_services.etat.aucun_service');
        }

        $disponibles = $services->filter(fn (Service $s) => $s->disponibilite === Disponibilite::Disponible)->count();
        $interrompus = $services->filter(fn (Service $s) => $s->estInterrompu())->count();
        $desactives = $services->filter(fn (Service $s) => $s->estDesactive())->count();

        if ($interrompus === 0 && $desactives === 0) {
            return trans_choice('synthese_services.etat.tous_disponibles', $total);
        }

        $parties = [trans_choice('synthese_services.etat.disponibles', $disponibles)];
        if ($interrompus > 0) {
            $parties[] = trans_choice('synthese_services.etat.interrompus', $interrompus);
        }
        if ($desactives > 0) {
            $parties[] = trans_choice('synthese_services.etat.desactives', $desactives);
        }

        return mb_strtoupper(mb_substr($parties[0], 0, 1)).mb_substr(implode(', ', $parties), 1).'.';
    }
}
