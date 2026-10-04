<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Support\DateLocale;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * F83 : accusé de réception d'une demande, remis à son auteur sous forme d'un document lisible et imprimable (page et fichier
 * .html autonome, comme « Mon dossier »). Habitant propriétaire seulement (user_id strict, DemandePolicy::accuserReception) ;
 * ni JSON ni CSV. Il ne contient ni le message de la demande ni aucune donnée d'un autre habitant.
 */
class AccuseReceptionController extends Controller
{
    public function show(Demande $demande): View
    {
        Gate::authorize('accuserReception', $demande);

        return view('demandes.accuse', ['a' => $this->donnees($demande)]);
    }

    /** Le même document, en fichier .html autonome (CSS en ligne, aucun script, aucune ressource externe). */
    public function telecharger(Demande $demande): Response
    {
        Gate::authorize('accuserReception', $demande);

        // Les liens absolus du fichier partent de APP_URL, jamais de l'hôte de la requête (voir DonneesPersonnelles::rendreAutonome).
        URL::forceRootUrl(config('app.url'));
        try {
            $html = view('documents.accuse', ['a' => $this->donnees($demande)])->render();
        } finally {
            URL::forceRootUrl(null);
        }

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="accuse-de-reception-'.$demande->reference.'.html"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Chiffres et phrases calculés une seule fois ; la date de réception est celle de la création, en heure locale (APP_TIMEZONE). */
    private function donnees(Demande $demande): array
    {
        $demande->loadMissing('service');

        return [
            'reference' => $demande->reference,
            'recue_le' => DateLocale::format($demande->created_at),
            'objet' => $demande->objet,
            'service' => $demande->service?->nom ?? __('À orienter par la mairie'),
            'statut' => $demande->statut->label(),
            'signification' => __('dossier.statuts.'.$demande->statut->value),
            'delivre_le' => DateLocale::format(now()),
            'demande' => $demande,
        ];
    }
}
