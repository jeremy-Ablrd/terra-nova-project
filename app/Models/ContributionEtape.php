<?php

namespace App\Models;

use App\Enums\StatutContribution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une étape de la frise d'une contribution : le statut atteint et quand, sans nom d'acteur.
 * Créée uniquement par l'événement `created` de Contribution et par TransitionContribution ; vu_at : l'habitant en a pris connaissance.
 */
class ContributionEtape extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'statut' => StatutContribution::class,
            'vu_at' => 'datetime',
        ];
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }
}
