<?php

namespace App\Policies;

use App\Models\Demande;
use App\Models\User;

class DemandePolicy
{
    /** Liste de toutes les demandes : réservée à l'agent. */
    public function viewAny(User $user): bool
    {
        return $user->isAgent();
    }

    /** Le citoyen ne voit que ses demandes ; l'agent voit toutes les demandes ; l'admin n'a aucun accès aux demandes des habitants. */
    public function view(User $user, Demande $demande): bool
    {
        return $user->isAgent() || $demande->user_id === $user->id;
    }

    /** Seul l'agent peut modifier le statut d'une demande. */
    public function updateStatus(User $user, Demande $demande): bool
    {
        return $user->isAgent();
    }
}
