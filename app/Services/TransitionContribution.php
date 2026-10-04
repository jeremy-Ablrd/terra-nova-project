<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Enums\StatutContribution;
use App\Models\Contribution;
use App\Models\ContributionEtape;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Seul point de passage pour changer le statut d'une contribution : reçue → examinée → prise en compte, rien d'autre.
 * Statut, réponse, étape de la frise et entrée du journal sont écrits dans la même transaction (tout ou rien).
 * « Prise en compte » exige une réponse de la ville ; « examinée » l'accepte, facultative.
 */
class TransitionContribution
{
    /**
     * @param  StatutContribution  $statutAffiche  statut que l'administrateur avait sous les yeux (refusé s'il a changé entre-temps)
     *
     * @throws TransitionRefusee
     */
    public function passer(Contribution $contribution, StatutContribution $statutAffiche, User $admin, ?string $reponse = null): Contribution
    {
        $reponse = $reponse === null ? null : trim($reponse);
        $reponse = $reponse === '' ? null : $reponse;

        return DB::transaction(function () use ($contribution, $statutAffiche, $admin, $reponse) {
            $contribution = Contribution::whereKey($contribution->id)->lockForUpdate()->firstOrFail();

            if ($contribution->statut !== $statutAffiche) {
                throw new TransitionRefusee(__('Le statut de cette contribution a changé entre-temps. Vérifiez son état actuel avant de recommencer.'));
            }

            $cible = $contribution->statut->suivant();
            if ($cible === null) {
                throw new TransitionRefusee(__('Cette contribution est déjà prise en compte : son statut ne peut plus changer.'));
            }
            if ($cible === StatutContribution::PriseEnCompte && $reponse === null) {
                throw new TransitionRefusee(__('Une réponse de la ville est obligatoire pour prendre une contribution en compte.'));
            }

            $avant = $contribution->statut;
            $contribution->statut = $cible;
            if ($reponse !== null) {
                $contribution->reponse = $reponse;
                $contribution->reponse_at = now();
            }
            $contribution->save();

            $etape = new ContributionEtape;
            $etape->contribution_id = $contribution->id;
            $etape->statut = $cible;
            $etape->save();

            // Référence et statuts seulement : ni le texte de l'habitant ni la réponse de la ville.
            Journal::enregistrer($admin, ActionJournal::ContributionTraitee, $contribution,
                $contribution->reference.' : '.$avant->value.' → '.$cible->value);

            return $contribution;
        });
    }
}
