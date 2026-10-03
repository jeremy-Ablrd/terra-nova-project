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
    'adresse', 'quartier', 'repere', 'telephone', 'urgence',
])]
class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

    /** Champs de localisation modifiables depuis le formulaire de gestion. */
    public const CHAMPS_LOCALISATION = ['adresse', 'quartier', 'repere', 'telephone'];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'actif' => 'boolean',
            'prioritaire' => 'boolean',
            'urgence' => 'boolean',
            'categorie' => CategorieService::class,
            'disponibilite' => Disponibilite::class,
            'retour_estime_at' => 'datetime',
            'desactive_at' => 'datetime',
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

    /** F46 : services d'urgence (urgence = vrai) ou de la catégorie santé, services d'urgence d'abord. */
    public function scopeUrgences(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->where('urgence', true)->orWhere('categorie', CategorieService::Sante->value))
            ->orderByDesc('urgence')
            ->orderBy('ordre')
            ->orderBy('nom');
    }

    /** Liste publique : prioritaires en tête (tri d'origine), puis les services désactivés et interrompus avant les disponibles. */
    public function scopeParPrioriteEtEtat(Builder $query): Builder
    {
        return $query->orderByDesc('prioritaire')
            ->orderByRaw("case disponibilite when 'desactive' then 0 when 'interrompu' then 1 else 2 end")
            ->orderBy('ordre')
            ->orderBy('nom');
    }

    /** Services qu'on peut choisir dans /contact : tout sauf les services désactivés (coupure d'urgence). */
    public function scopeChoisissables(Builder $query): Builder
    {
        return $query->where('disponibilite', '!=', Disponibilite::Desactive->value);
    }

    public function estInterrompu(): bool
    {
        return $this->disponibilite === Disponibilite::Interrompu;
    }

    public function estDesactive(): bool
    {
        return $this->disponibilite === Disponibilite::Desactive;
    }

    /** Vrai pour « interrompu » et « désactivé » : un motif (et une alternative) ont alors un sens. */
    public function estIndisponible(): bool
    {
        return $this->disponibilite !== Disponibilite::Disponible;
    }

    /** Adresse à afficher : l'adresse renseignée, à défaut le « lieu » historique du service. */
    public function adresseAffichee(): ?string
    {
        return $this->adresse ?? $this->lieu;
    }

    /** Lien tel: (chiffres et « + » seulement) ; null sans téléphone. */
    public function telephoneHref(): ?string
    {
        if ($this->telephone === null) {
            return null;
        }

        $numero = preg_replace('/[^\d+]/', '', $this->telephone);

        return $numero === '' ? null : 'tel:'.$numero;
    }

    /**
     * Applique le formulaire de gestion (données validées) : disponibilité, priorité, urgence et localisation.
     * Remettre un service en service efface le motif, le retour estimé et l'alternative : ils n'ont de sens
     * que pendant une interruption.
     *
     * @param  array<string, mixed>  $donnees
     * @return array{disponibilite?: array{0: Disponibilite, 1: Disponibilite}, prioritaire?: array{0: bool, 1: bool}, champs?: list<string>}
     *                                                                                                       ce qui a réellement changé (vide si rien) : avant → après pour la disponibilité et la priorité, noms pour le reste
     */
    public function mettreAJour(array $donnees): array
    {
        $avantDisponibilite = $this->disponibilite;
        $avantPriorite = (bool) $this->prioritaire;

        $this->prioritaire = (bool) ($donnees['prioritaire'] ?? false);
        $this->urgence = (bool) ($donnees['urgence'] ?? false);
        $this->disponibilite = Disponibilite::from($donnees['disponibilite']);

        foreach (self::CHAMPS_LOCALISATION as $champ) {
            $this->{$champ} = $donnees[$champ] ?? null;
        }

        if ($this->estInterrompu()) {
            $this->motif_interruption = $donnees['motif_interruption'];
            $this->retour_estime_at = $donnees['retour_estime_at'] ?? null;
            $this->alternative = $donnees['alternative'] ?? null;
        } elseif ($this->estDesactive()) {
            $this->motif_interruption = $donnees['motif_interruption'];
            $this->retour_estime_at = null;
            $this->alternative = $donnees['alternative'] ?? null;
        } else {
            $this->motif_interruption = null;
            $this->retour_estime_at = null;
            $this->alternative = null;
        }

        // Date de la coupure : posée au passage à « désactivé », conservée tant que le service le reste, effacée sinon.
        if ($this->estDesactive()) {
            $this->desactive_at ??= now();
        } else {
            $this->desactive_at = null;
        }

        $changements = [];
        if ($avantDisponibilite !== $this->disponibilite) {
            $changements['disponibilite'] = [$avantDisponibilite, $this->disponibilite];
        }
        if ($avantPriorite !== (bool) $this->prioritaire) {
            $changements['prioritaire'] = [$avantPriorite, (bool) $this->prioritaire];
        }
        $autres = array_values(array_diff(array_keys($this->getDirty()), ['disponibilite', 'prioritaire', 'desactive_at', 'updated_at']));
        if ($autres !== []) {
            $changements['champs'] = $autres;
        }

        $this->save();

        return $changements;
    }

    /**
     * Coupure d'urgence (F63) : le service ne peut plus être choisi dans /contact. Motif obligatoire, alternative facultative.
     * Même résultat que mettreAJour() : ce qui a réellement changé, pour le journal.
     *
     * @return array<string, mixed>
     */
    public function desactiver(string $motif, ?string $alternative = null): array
    {
        return $this->mettreAJour([
            'disponibilite' => Disponibilite::Desactive->value,
            'motif_interruption' => $motif,
            'alternative' => $alternative,
            'prioritaire' => $this->prioritaire,
            'urgence' => $this->urgence,
        ] + collect(self::CHAMPS_LOCALISATION)->mapWithKeys(fn ($champ) => [$champ => $this->{$champ}])->all());
    }

    /** Remise en service : efface motif, retour estimé et alternative, comme la remise en service existante. */
    public function reactiver(): array
    {
        return $this->mettreAJour([
            'disponibilite' => Disponibilite::Disponible->value,
            'prioritaire' => $this->prioritaire,
            'urgence' => $this->urgence,
        ] + collect(self::CHAMPS_LOCALISATION)->mapWithKeys(fn ($champ) => [$champ => $this->{$champ}])->all());
    }

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }
}
