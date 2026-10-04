<?php

namespace App\Policies;

use App\Enums\TypeDemande;
use App\Models\Demande;
use App\Models\User;

class DemandePolicy
{
    /** Liste de toutes les demandes : réservée à l'agent. */
    public function viewAny(User $user): bool
    {
        return $user->isAgent();
    }

    /** Le citoyen ne voit que ses demandes ; l'agent voit toutes les demandes citoyennes ; l'admin n'a aucun accès aux demandes des habitants. */
    public function view(User $user, Demande $demande): bool
    {
        // user_id null (demande importée de l'API) ne correspond jamais à un utilisateur : comparaison stricte.
        if ($user->isAgent()) {
            return ($demande->type ?? TypeDemande::Citoyen) === TypeDemande::Citoyen;
        }

        return $demande->user_id !== null && $demande->user_id === $user->id;
    }

    /** F49 : seul l'habitant propriétaire acquitte un changement d'état (comparaison stricte, jamais pour user_id null). */
    public function acquitter(User $user, Demande $demande): bool
    {
        return $demande->user_id !== null && $demande->user_id === $user->id;
    }

    /** F83 : l'accusé de réception n'est remis qu'à l'habitant qui a déposé la demande (user_id strict, jamais pour user_id nul). */
    public function accuserReception(User $user, Demande $demande): bool
    {
        return $user->isCitoyen() && $demande->user_id !== null && $demande->user_id === $user->id;
    }

    /** Seul l'agent peut modifier le statut d'une demande. */
    public function updateStatus(User $user, Demande $demande): bool
    {
        return $user->isAgent();
    }
}
