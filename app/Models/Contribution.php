<?php

namespace App\Models;

use App\Enums\StatutContribution;
use App\Enums\TypeContribution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// user_id, type, projet_id, service_id, statut, reponse et anonymisee_at sont fixés côté serveur, jamais depuis un formulaire.
#[Fillable(['titre', 'message'])]
class Contribution extends Model
{
    /** @use HasFactory<\Database\Factories\ContributionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Numéro de référence lisible, dérivé de l'id : PA-2026-00042 (même mécanisme que les demandes).
        static::created(function (Contribution $contribution) {
            if ($contribution->reference === null) {
                $contribution->forceFill([
                    'reference' => sprintf('PA-%s-%05d', $contribution->created_at->format('Y'), $contribution->id),
                ])->saveQuietly();
            }

            // Toute contribution commence par une étape « reçue ».
            $etape = new ContributionEtape;
            $etape->contribution_id = $contribution->id;
            $etape->statut = StatutContribution::Recue;
            $etape->created_at = $contribution->created_at;
            $etape->save();
        });
    }

    protected function casts(): array
    {
        return [
            'type' => TypeContribution::class,
            'statut' => StatutContribution::class,
            'reponse_at' => 'datetime',
            'anonymisee_at' => 'datetime',
        ];
    }

    public function estAnonymisee(): bool
    {
        return $this->anonymisee_at !== null;
    }

    /** Ce dont il s'agit, en une phrase : jamais le texte libre d'un habitant (l'idée garde son titre tant qu'elle n'est pas anonymisée). */
    public function intitule(): string
    {
        return match ($this->type) {
            TypeContribution::Avis => __('Avis sur le projet « :projet »', ['projet' => $this->projet?->titre ?? __('projet retiré')]),
            TypeContribution::Commentaire => __('Commentaire sur le service « :service »', ['service' => $this->service?->nom ?? __('service retiré')]),
            default => $this->titre !== null && ! $this->estAnonymisee()
                ? __('Idée : :titre', ['titre' => $this->titre])
                : __('Idée'),
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Étapes de la frise, de la plus ancienne à la plus récente. */
    public function etapes(): HasMany
    {
        return $this->hasMany(ContributionEtape::class)->orderBy('created_at')->orderBy('id');
    }
}
