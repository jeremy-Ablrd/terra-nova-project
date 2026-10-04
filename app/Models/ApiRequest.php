<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Demande reçue de l'API Terra Nova. À ne pas confondre avec Demande (demandes des habitants).
 * Alimenté uniquement par App\Services\NovaTerraApi (upsert), jamais par un formulaire.
 */
class ApiRequest extends Model
{
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
