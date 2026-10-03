<?php

namespace App\Policies;

use App\Models\AppareilConnu;
use App\Models\User;

class AppareilConnuPolicy
{
    /** Oublier un appareil : seulement le sien. */
    public function supprimer(User $user, AppareilConnu $appareil): bool
    {
        return $appareil->user_id === $user->id;
    }
}
