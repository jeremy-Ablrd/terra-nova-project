<?php

namespace App\Models;

use App\Enums\Niveau;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id (publiée par) est fixé côté serveur, jamais depuis un formulaire.
#[Fillable(['titre', 'ce_qui_se_passe', 'ce_quil_faut_faire', 'secteur', 'niveau', 'consignes_vulnerables', 'starts_at', 'ends_at'])]
class Alerte extends Model
{
    /** @use HasFactory<\Database\Factories\AlerteFactory> */
    use HasFactory;

    public const ETAT_ACTIVE = 'active';

    public const ETAT_PROGRAMMEE = 'programmee';

    public const ETAT_TERMINEE = 'terminee';

    /** Alertes en cours : commencées (starts_at <= maintenant) et pas finies (ends_at vide ou dans le futur). */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** Tri : urgent, puis vigilance, puis information ; à niveau égal, la plus récente d'abord. */
    public function scopeParPriorite(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case niveau when 'urgent' then 0 when 'vigilance' then 1 else 2 end")
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'niveau' => Niveau::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** L'admin qui a publié l'alerte. */
    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** active, programmee (pas encore commencée) ou terminee. */
    public function etat(): string
    {
        if ($this->starts_at->isFuture()) {
            return self::ETAT_PROGRAMMEE;
        }

        return $this->ends_at !== null && ! $this->ends_at->isFuture() ? self::ETAT_TERMINEE : self::ETAT_ACTIVE;
    }
}
