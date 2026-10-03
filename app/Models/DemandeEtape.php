<?php

namespace App\Models;

use App\Enums\Statut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une étape de l'historique d'une demande (D11/F26) : le statut atteint, quand, et par quel agent.
 * Créée uniquement par l'événement `created` de Demande et par TransitionDemande ; vu_at sert à F49.
 */
class DemandeEtape extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'statut' => Statut::class,
            'vu_at' => 'datetime',
        ];
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }
}
