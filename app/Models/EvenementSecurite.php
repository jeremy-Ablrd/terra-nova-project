<?php

namespace App\Models;

use App\Enums\TypeEvenementSecurite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne du journal de sécurité. Écrite par JournalSecurite, avec save() : jamais d'insert() en masse. */
class EvenementSecurite extends Model
{
    protected $table = 'evenements_securite';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => TypeEvenementSecurite::class, 'vu_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
