<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Models\Demande;
use App\Models\DemandeReponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Réponse directe d'un agent à l'habitant (F84). La réponse (texte, date, agent) et l'entrée du journal sont écrites dans la
 * même transaction. Le journal dit « Réponse envoyée » et la référence : jamais le texte. Une demande sans compte
 * destinataire (importée de l'API, ou anonymisée) ne reçoit pas de réponse.
 */
class RepondreDemande
{
    /** @throws TransitionRefusee */
    public function envoyer(Demande $demande, User $agent, string $texte): DemandeReponse
    {
        $texte = trim($texte);

        return DB::transaction(function () use ($demande, $agent, $texte) {
            $demande = Demande::whereKey($demande->id)->lockForUpdate()->firstOrFail();

            if ($demande->user_id === null) {
                throw new TransitionRefusee(__('Cette demande n\'a pas de compte habitant destinataire : la réponse n\'est pas possible depuis la plateforme.'));
            }
            if ($texte === '') {
                throw new TransitionRefusee(__('La réponse ne peut pas être vide.'));
            }

            $reponse = new DemandeReponse;
            $reponse->demande_id = $demande->id;
            $reponse->agent_id = $agent->id;
            $reponse->agent_nom = $agent->name;   // copie : reste lisible si le compte est supprimé
            $reponse->texte = $texte;
            $reponse->created_at = now();
            $reponse->save();

            Journal::enregistrer($agent, ActionJournal::ReponseEnvoyee, $demande);

            return $reponse;
        });
    }
}
