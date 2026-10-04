<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réponse directe d'un agent à l'habitant (F84). Créée uniquement par RepondreDemande ; vu_at sert à la notification « Compris ».
 * Le nom de l'agent n'est montré qu'aux agents : l'habitant lit « la mairie ».
 */
class DemandeReponse extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['vu_at' => 'datetime'];
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }
}
