<?php

namespace App\Services;

use App\Enums\StatutContribution;
use App\Models\ContributionEtape;
use App\Models\User;
use Illuminate\Support\Collection;

/** Changements d'état de contributions non encore vus par un habitant (encart de /espace). Une requête par requête HTTP, citoyens seulement. */
class SuiviContributions
{
    /** @return Collection<int, ContributionEtape> étapes non « reçue » avec vu_at nul ; attribut `reference` ajouté */
    public function changementsNonVus(?User $user): Collection
    {
        if ($user === null || ! $user->isCitoyen()) {
            return collect();
        }

        $requete = request();
        $cle = 'contributions_non_vues_'.$user->id;

        if (! $requete->attributes->has($cle)) {
            $requete->attributes->set($cle, ContributionEtape::query()
                ->join('contributions', 'contributions.id', '=', 'contribution_etapes.contribution_id')
                ->where('contributions.user_id', $user->id)
                ->where('contribution_etapes.statut', '!=', StatutContribution::Recue->value)
                ->whereNull('contribution_etapes.vu_at')
                ->select('contribution_etapes.*', 'contributions.reference as reference')
                ->orderBy('contribution_etapes.created_at')
                ->orderBy('contribution_etapes.id')
                ->get());
        }

        return $requete->attributes->get($cle);
    }
}
