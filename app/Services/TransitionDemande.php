<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Enums\Statut;
use App\Models\Demande;
use App\Models\DemandeEtape;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Seul point de passage pour changer le statut d'une demande : nouvelle → en_cours → traitee, rien d'autre.
 * Statut, étape d'historique et entrée du journal sont écrits dans la même transaction (tout ou rien).
 */
class TransitionDemande
{
    /**
     * @param  Statut  $statutAffiche  statut que l'agent avait sous les yeux (refusé s'il a changé entre-temps)
     *
     * @throws TransitionRefusee
     */
    public function passer(Demande $demande, Statut $statutAffiche, User $agent): Demande
    {
        return DB::transaction(function () use ($demande, $statutAffiche, $agent) {
            $demande = Demande::whereKey($demande->id)->lockForUpdate()->firstOrFail();

            if ($demande->statut !== $statutAffiche) {
                throw new TransitionRefusee(__('Le statut de cette demande a changé entre-temps. Vérifiez son état actuel avant de recommencer.'));
            }

            $cible = $demande->statut->suivant();
            if ($cible === null) {
                throw new TransitionRefusee(__('Cette demande est déjà traitée : son statut ne peut plus changer.'));
            }

            $avant = $demande->statut;
            $demande->statut = $cible;
            if ($cible === Statut::EnCours) {
                $demande->agent_id = $agent->id;
            }
            if ($cible === Statut::Traitee) {
                $demande->traitee_at = now();
            }
            $demande->save();

            $etape = new DemandeEtape;
            $etape->demande_id = $demande->id;
            $etape->statut = $cible;
            $etape->agent_id = $agent->id;
            $etape->agent_nom = $agent->name;
            $etape->save();

            // Détail sans contenu de message ni nom d'habitant : référence et statuts seulement.
            Journal::enregistrer($agent, ActionJournal::StatutDemandeModifie, $demande,
                $demande->reference.' : '.$avant->value.' → '.$cible->value);

            return $demande;
        });
    }
}
