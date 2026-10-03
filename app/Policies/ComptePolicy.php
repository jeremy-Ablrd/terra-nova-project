<?php

namespace App\Policies;

use App\Models\User;

/** Gestion des comptes (modèle User) : administrateur seul. Enregistrée dans AppServiceProvider. */
class ComptePolicy
{
    public function updateRole(User $acteur, User $compte): bool
    {
        return $acteur->isAdmin();
    }
}
