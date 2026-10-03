<?php

namespace App\Models;

use App\Enums\Statut;
use App\Enums\TypeDemande;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// user_id, statut, type, agent_id, traitee_at, request_code et demandeur_nom sont fixés côté serveur, jamais depuis un formulaire.
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

            // Historique (D11/F26) : toute demande, importée ou non, commence par une étape « nouvelle ».
            $etape = new DemandeEtape;
            $etape->demande_id = $demande->id;
            $etape->statut = Statut::Nouvelle;
            $etape->created_at = $demande->created_at;
            $etape->save();
        });
    }

    /** Demandes citoyennes : les seules affichées et comptées dans le Centre technique municipal. */
    public function scopeCitoyennes(Builder $query): Builder
    {
        return $query->where('type', TypeDemande::Citoyen->value);
    }

    /** D17 : une demande « en attente » n'est pas encore prise en charge (statut nouvelle) ; demandes citoyennes seulement. */
    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->citoyennes()->where('statut', Statut::Nouvelle->value);
    }

    /** Demande importée de l'API : reconnue à son request_code (user_id nul ne suffit pas : une demande anonymisée l'est aussi). */
    public function estImportee(): bool
    {
        return $this->request_code !== null;
    }

    /** Demande dont l'habitant a supprimé le compte : contenu remplacé, plus aucun lien avec une personne. */
    public function estAnonymisee(): bool
    {
        return $this->anonymisee_at !== null;
    }

    /** Nom du demandeur : « Demandeur supprimé » si anonymisée, sinon le compte, sinon le nom donné par l'API, sinon « Non précisé ». */
    protected function nomDemandeur(): Attribute
    {
        return Attribute::get(fn () => $this->estAnonymisee()
            ? __('Demandeur supprimé')
            : ($this->user?->name ?? $this->demandeur_nom ?? __('Non précisé')));
    }

    protected function casts(): array
    {
        return [
            'anonymisee_at' => 'datetime',
            'statut' => Statut::class,
            'type' => TypeDemande::class,
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

    /** Étapes de l'historique, de la plus ancienne à la plus récente. */
    public function etapes(): HasMany
    {
        return $this->hasMany(DemandeEtape::class)->orderBy('created_at')->orderBy('id');
    }

    /** L'agent qui prend en charge la demande. */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
