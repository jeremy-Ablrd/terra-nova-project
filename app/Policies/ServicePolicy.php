<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Gestion du catalogue des services (disponibilité, mise en avant) : réservée à l'admin.
 * Pour l'ouvrir aussi à l'agent, il suffit d'ajouter `|| $user->isAgent()` dans ces deux méthodes.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->isAdmin();
    }
}
