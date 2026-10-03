<?php

namespace App\Services;

use App\Enums\Statut;
use App\Models\DemandeEtape;
use App\Models\User;
use Illuminate\Support\Collection;

/** F49 : changements d'état non encore vus par un habitant. Une seule requête par requête HTTP, citoyens seulement. */
class SuiviDemandes
{
    /** @return Collection<int, DemandeEtape> étapes non nouvelles avec vu_at nul ; attribut `reference` ajouté */
    public function changementsNonVus(?User $user): Collection
    {
        if ($user === null || ! $user->isCitoyen()) {
            return collect();
        }

        $requete = request();
        $cle = 'changements_non_vus_'.$user->id;

        if (! $requete->attributes->has($cle)) {
            $requete->attributes->set($cle, DemandeEtape::query()
                ->join('demandes', 'demandes.id', '=', 'demande_etapes.demande_id')
                ->where('demandes.user_id', $user->id)
                ->where('demande_etapes.statut', '!=', Statut::Nouvelle->value)
                ->whereNull('demande_etapes.vu_at')
                ->select('demande_etapes.*', 'demandes.reference as reference')
                ->orderBy('demande_etapes.created_at')
                ->orderBy('demande_etapes.id')
                ->get());
        }

        return $requete->attributes->get($cle);
    }
}
