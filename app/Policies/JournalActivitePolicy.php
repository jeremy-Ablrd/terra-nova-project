<?php

namespace App\Policies;

use App\Models\User;

/**
 * Le journal d'activité se lit dans l'espace agent. Séparation stricte : l'admin n'a pas de vue ici.
 * Aucune autre capacité (création, modification, suppression) : elles sont refusées par défaut, et n'existent pas.
 */
class JournalActivitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAgent();
    }
}
