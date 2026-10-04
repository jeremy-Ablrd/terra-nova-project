<?php

namespace App\Policies;

use App\Models\Contribution;
use App\Models\User;

/**
 * Contributions des habitants : le citoyen ne voit que les siennes (user_id strict, jamais pour une contribution anonymisée) ;
 * l'admin les traite ; l'agent n'a aucun accès.
 */
class ContributionPolicy
{
    /** Liste d'administration : admin seul. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Un habitant lit sa propre contribution. */
    public function view(User $user, Contribution $contribution): bool
    {
        return $user->isCitoyen() && $contribution->user_id !== null && $contribution->user_id === $user->id;
    }

    /** L'admin lit et traite les contributions (changement de statut, réponse de la ville). */
    public function administrer(User $user, Contribution $contribution): bool
    {
        return $user->isAdmin();
    }
}
