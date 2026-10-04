<?php

namespace App\Models;

use App\Enums\EtatConsultation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// slug et publie_at sont fixés côté serveur (contrôleur d'administration), jamais depuis la saisie directe.
#[Fillable(['titre', 'resume', 'description', 'consultation_debut_at', 'consultation_fin_at', 'bilan'])]
class Projet extends Model
{
    /** @use HasFactory<\Database\Factories\ProjetFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'publie_at' => 'datetime',
            'consultation_debut_at' => 'datetime',
            'consultation_fin_at' => 'datetime',
        ];
    }

    /** Projets visibles du public : publiés (un brouillon n'a pas de date de publication). */
    public function scopePublies(Builder $query): Builder
    {
        return $query->whereNotNull('publie_at')->where('publie_at', '<=', now());
    }

    public function estPublie(): bool
    {
        return $this->publie_at !== null && $this->publie_at->lte(now());
    }

    /** État de la consultation à l'instant présent (APP_TIMEZONE), recalculé à chaque appel : jamais mis en cache. */
    public function etatConsultation(): EtatConsultation
    {
        if ($this->consultation_debut_at === null && $this->consultation_fin_at === null) {
            return EtatConsultation::Aucune;
        }

        $maintenant = now();

        if ($this->consultation_debut_at !== null && $maintenant->lt($this->consultation_debut_at)) {
            return EtatConsultation::AVenir;
        }
        if ($this->consultation_fin_at !== null && $maintenant->gt($this->consultation_fin_at)) {
            return EtatConsultation::Close;
        }

        return EtatConsultation::Ouverte;
    }

    public function consultationOuverte(): bool
    {
        return $this->estPublie() && $this->etatConsultation() === EtatConsultation::Ouverte;
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }
}
