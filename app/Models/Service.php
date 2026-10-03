<?php

namespace App\Models;

use App\Enums\CategorieService;
use App\Enums\Disponibilite;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nom', 'slug', 'resume', 'description', 'horaires', 'lieu', 'contact', 'icone', 'ordre', 'actif',
    'categorie', 'prioritaire', 'disponibilite', 'motif_interruption', 'retour_estime_at', 'alternative',
])]
class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'actif' => 'boolean',
            'prioritaire' => 'boolean',
            'categorie' => CategorieService::class,
            'disponibilite' => Disponibilite::class,
            'retour_estime_at' => 'datetime',
        ];
    }

    /** Services visibles du public. */
    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    /** Services prioritaires en tête (F28), puis l'ordre du catalogue, puis le nom. */
    public function scopeParPriorite(Builder $query): Builder
    {
        return $query->orderByDesc('prioritaire')->orderBy('ordre')->orderBy('nom');
    }

    public function estInterrompu(): bool
    {
        return $this->disponibilite === Disponibilite::Interrompu;
    }

    /**
     * Applique le formulaire de gestion (données validées). Remettre un service en service efface le motif,
     * le retour estimé et l'alternative : ils n'ont de sens que pendant une interruption.
     *
     * @param  array{disponibilite: string, prioritaire?: mixed, motif_interruption?: ?string, retour_estime_at?: ?string, alternative?: ?string}  $donnees
     */
    public function mettreAJourDisponibilite(array $donnees): void
    {
        $this->prioritaire = (bool) ($donnees['prioritaire'] ?? false);
        $this->disponibilite = Disponibilite::from($donnees['disponibilite']);

        if ($this->estInterrompu()) {
            $this->motif_interruption = $donnees['motif_interruption'];
            $this->retour_estime_at = $donnees['retour_estime_at'] ?? null;
            $this->alternative = $donnees['alternative'] ?? null;
        } else {
            $this->motif_interruption = null;
            $this->retour_estime_at = null;
            $this->alternative = null;
        }

        $this->save();
    }

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }
}
