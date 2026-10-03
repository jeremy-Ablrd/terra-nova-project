<?php

namespace App\Models;

use App\Enums\CategorieService;
use App\Enums\Disponibilite;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nom', 'slug', 'resume', 'description', 'horaires', 'lieu', 'contact', 'icone', 'ordre', 'actif',
    'categorie', 'prioritaire', 'disponibilite', 'motif_interruption', 'retour_estime_at', 'alternative',
    'adresse', 'quartier', 'repere', 'telephone', 'urgence', 'organisme', 'horaires_semaine',
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
            'horaires_semaine' => 'array',
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

        // F74 : horaires d'ouverture et organisme, seulement si le formulaire les envoie (la coupure d'urgence n'y touche pas).
        if (array_key_exists('organisme', $donnees)) {
            $this->organisme = filled($donnees['organisme']) ? $donnees['organisme'] : null;
        }
        if (array_key_exists('horaires', $donnees)) {
            $this->horaires_semaine = self::normaliserHoraires((array) $donnees['horaires']);
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

    /** Jours de la semaine, dans l'ordre ISO (lundi = 1). */
    public const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    /**
     * Horaires saisis dans le formulaire → donnée stockée : 7 jours, au plus 2 plages complètes par jour, triées.
     * Aucune plage du tout : null (le texte `horaires` d'origine sert alors de repli).
     *
     * @param  array<string, mixed>  $saisie
     * @return array<string, list<array{0: string, 1: string}>>|null
     */
    public static function normaliserHoraires(array $saisie): ?array
    {
        $semaine = [];
        foreach (self::JOURS as $jour) {
            $plages = [];
            foreach (array_slice((array) ($saisie[$jour] ?? []), 0, 2) as $plage) {
                $ouverture = $plage['ouverture'] ?? null;
                $fermeture = $plage['fermeture'] ?? null;
                if (filled($ouverture) && filled($fermeture)) {
                    $plages[] = [(string) $ouverture, (string) $fermeture];
                }
            }
            usort($plages, fn ($a, $b) => strcmp($a[0], $b[0]));
            $semaine[$jour] = $plages;
        }

        return collect($semaine)->flatten()->isEmpty() ? null : $semaine;
    }

    /** @return list<array{0: string, 1: string}> plages d'ouverture d'un jour ISO (1 = lundi) */
    public function plagesDuJour(int $jourIso): array
    {
        return $this->horaires_semaine[self::JOURS[$jourIso - 1]] ?? [];
    }

    public function aHorairesStructures(): bool
    {
        return is_array($this->horaires_semaine);
    }

    /**
     * État « ouvert / fermé » à un instant donné (par défaut : maintenant, dans le fuseau de l'application), en texte.
     * Calculé à chaque appel, jamais mis en cache. Null sans horaires structurés.
     *
     * @return array{ouvert: bool, texte: string}|null
     */
    public function etatOuverture(?CarbonInterface $maintenant = null): ?array
    {
        if (! $this->aHorairesStructures()) {
            return null;
        }

        $maintenant = ($maintenant ?? now())->copy()->timezone(config('app.timezone'));
        $heure = $maintenant->format('H:i');
        $plages = $this->plagesDuJour($maintenant->dayOfWeekIso);

        foreach ($plages as [$ouverture, $fermeture]) {
            if ($heure >= $ouverture && $heure < $fermeture) {
                return ['ouvert' => true, 'texte' => __('ouverture_service.etat.ouvert', ['heure' => $fermeture])];
            }
        }

        // Plus tard aujourd'hui : avant la première plage, ou pendant la pause entre deux plages.
        foreach ($plages as $rang => [$ouverture]) {
            if ($ouverture > $heure) {
                return ['ouvert' => false, 'texte' => __($rang === 0 ? 'ouverture_service.etat.ouvre_aujourdhui' : 'ouverture_service.etat.pause', ['heure' => $ouverture])];
            }
        }

        // Prochain jour d'ouverture, dans les 7 jours qui suivent.
        for ($decalage = 1; $decalage <= 7; $decalage++) {
            $jour = $maintenant->copy()->addDays($decalage);
            $suivantes = $this->plagesDuJour($jour->dayOfWeekIso);
            if ($suivantes === []) {
                continue;
            }

            $heureOuverture = $suivantes[0][0];
            if ($decalage === 1) {
                $cle = $plages === [] ? 'ferme_aujourdhui_demain' : 'rouvre_demain';

                return ['ouvert' => false, 'texte' => __('ouverture_service.etat.'.$cle, ['heure' => $heureOuverture])];
            }

            return ['ouvert' => false, 'texte' => __('ouverture_service.etat.'.($plages === [] ? 'ferme_aujourdhui_jour' : 'rouvre_jour'),
                ['jour' => __('ouverture_service.jours.'.self::JOURS[$jour->dayOfWeekIso - 1]), 'heure' => $heureOuverture])];
        }

        return ['ouvert' => false, 'texte' => __('ouverture_service.etat.jamais')];
    }

    /**
     * Les 7 lignes du tableau des horaires (le jour courant est marqué en texte, jamais par la couleur seule).
     *
     * @return list<array{jour: string, horaires: string, aujourdhui: bool}>
     */
    public function lignesHoraires(?CarbonInterface $maintenant = null): array
    {
        $aujourdhui = ($maintenant ?? now())->copy()->timezone(config('app.timezone'))->dayOfWeekIso;

        return collect(self::JOURS)->map(function (string $jour, int $i) use ($aujourdhui) {
            $plages = $this->plagesDuJour($i + 1);

            return [
                'jour' => __('ouverture_service.jours_titre.'.$jour),
                'horaires' => $plages === [] ? __('ouverture_service.ferme') : collect($plages)->map(fn ($p) => $p[0].' – '.$p[1])->implode(', '),
                'aujourdhui' => $i + 1 === $aujourdhui,
            ];
        })->all();
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
