<?php

namespace App\Policies;

use App\Models\Projet;
use App\Models\User;

/** Gestion des projets : admin seul. La consultation publique (liste et fiche) ne passe pas par une policy. */
class ProjetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Projet $projet): bool
    {
        return $user->isAdmin();
    }
}
