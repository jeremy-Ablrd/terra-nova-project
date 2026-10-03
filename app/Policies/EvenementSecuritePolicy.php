<?php

namespace App\Policies;

use App\Enums\TypeEvenementSecurite;
use App\Models\EvenementSecurite;
use App\Models\User;

class EvenementSecuritePolicy
{
    /** Journal de sécurité : administrateur seul. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Répondre à une alerte « nouvel appareil » : seulement la personne concernée. */
    public function gerer(User $user, EvenementSecurite $evenement): bool
    {
        return $evenement->user_id !== null
            && $evenement->user_id === $user->id
            && $evenement->type === TypeEvenementSecurite::NouvelAppareil;
    }
}
