<?php

namespace App\Models;

use App\Enums\Statut;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id, statut, agent_id et traitee_at sont fixés côté serveur, jamais depuis un formulaire.
#[Fillable(['objet', 'message', 'service_id'])]
class Demande extends Model
{
    /** @use HasFactory<\Database\Factories\DemandeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Numéro de référence lisible, dérivé de l'id : NT-2026-00042.
        static::created(function (Demande $demande) {
            if ($demande->reference === null) {
                $demande->forceFill([
                    'reference' => sprintf('NT-%s-%05d', $demande->created_at->format('Y'), $demande->id),
                ])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'statut' => Statut::class,
            'traitee_at' => 'datetime',
        ];
    }

    /** Le demandeur (habitant). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** L'agent qui prend en charge la demande. */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
