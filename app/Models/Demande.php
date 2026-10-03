<?php

namespace App\Models;

use App\Enums\Statut;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id, statut, agent_id, traitee_at, request_code et demandeur_nom sont fixés côté serveur, jamais depuis un formulaire.
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

    /** D17 : une demande « en attente » n'est pas encore prise en charge (statut nouvelle). */
    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', Statut::Nouvelle->value);
    }

    /** Demande importée de l'API : aucun compte utilisateur associé (user_id null). */
    public function estImportee(): bool
    {
        return $this->user_id === null;
    }

    /** Nom du demandeur : le compte s'il existe, sinon le nom donné par l'API, sinon « Non précisé ». */
    protected function nomDemandeur(): Attribute
    {
        return Attribute::get(fn () => $this->user?->name ?? $this->demandeur_nom ?? __('Non précisé'));
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
