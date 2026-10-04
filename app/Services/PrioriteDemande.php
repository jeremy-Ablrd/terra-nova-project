<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Enums\Priorite;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Seul point de passage pour changer la priorité d'une demande (F80). Écrit la priorité et l'entrée du journal dans la même
 * transaction ; le journal ne contient que les niveaux, jamais le contenu de la demande.
 */
class PrioriteDemande
{
    /**
     * @param  Priorite  $affichee  priorité que l'agent avait sous les yeux (refusée si elle a changé entre-temps)
     *
     * @throws TransitionRefusee
     */
    public function definir(Demande $demande, Priorite $affichee, Priorite $cible, User $agent): Demande
    {
        return DB::transaction(function () use ($demande, $affichee, $cible, $agent) {
            $demande = Demande::whereKey($demande->id)->lockForUpdate()->firstOrFail();

            if ($demande->priorite !== $affichee) {
                throw new TransitionRefusee(__('La priorité de cette demande a changé entre-temps. Vérifiez son état actuel avant de recommencer.'));
            }
            if ($demande->priorite === $cible) {
                throw new TransitionRefusee(__('Cette demande a déjà la priorité « :priorite ».', ['priorite' => $cible->label()]));
            }

            $avant = $demande->priorite;
            $demande->priorite = $cible;
            $demande->save();

            Journal::enregistrer($agent, ActionJournal::PrioriteModifiee, $demande, 'priorité : '.$avant->value.' → '.$cible->value);

            return $demande;
        });
    }
}
