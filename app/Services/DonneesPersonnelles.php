<?php

namespace App\Services;

use App\Enums\Statut;
use App\Enums\TailleTexte;
use App\Enums\ThemeAffichage;
use App\Models\Contribution;
use App\Models\Demande;
use App\Models\DemandeEtape;
use App\Models\DemandeReponse;
use App\Models\User;
use App\Support\DateLocale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Données personnelles d'un habitant (F55, F56) : une seule source pour la page « Mes données » et les documents lisibles, le
 * récapitulatif. Ne lit que les données du compte demandé (user_id strict) : jamais mot de passe, jeton, session, nom d'agent.
 */
class DonneesPersonnelles
{
    /**
     * Résumé affiché sur la page : 2 requêtes (demandes groupées par statut, derniers changements d'état).
     *
     * @return array<string, mixed>
     */
    public function resume(User $user): array
    {
        $lignes = Demande::where('user_id', $user->id)->toBase()
            ->selectRaw('statut, count(*) as total, max(created_at) as derniere_creation, max(updated_at) as derniere_maj')
            ->groupBy('statut')
            ->get()
            ->keyBy('statut');

        $parStatut = collect(Statut::cases())->mapWithKeys(fn (Statut $s) => [$s->value => (int) ($lignes[$s->value]->total ?? 0)]);

        $dernierChangement = DemandeEtape::query()
            ->join('demandes', 'demandes.id', '=', 'demande_etapes.demande_id')
            ->where('demandes.user_id', $user->id)
            ->where('demande_etapes.statut', '!=', Statut::Nouvelle->value)
            ->max('demande_etapes.created_at');

        return [
            'nom' => $user->name,
            'email' => $user->email,
            'role' => $user->role->label(),
            'inscrit_le' => DateLocale::format($user->created_at),
            'profil_mis_a_jour_le' => DateLocale::format($user->updated_at),
            'taille_texte' => (TailleTexte::tryFrom((string) ($user->preferences['taille'] ?? '')) ?? TailleTexte::Normal)->label(),
            'theme' => (ThemeAffichage::tryFrom((string) ($user->preferences['theme'] ?? '')) ?? ThemeAffichage::Standard)->label(),
            'par_statut' => $parStatut,
            'total' => $parStatut->sum(),
            'derniere_demande' => $lignes->max('derniere_creation'),
            'derniere_mise_a_jour' => $lignes->max('derniere_maj'),
            'dernier_changement' => $dernierChangement,
        ];
    }

    /**
     * Les documents lisibles (« Mon dossier », « Récapitulatif de mes demandes ») : chiffres et phrases calculés une seule fois
     * à partir de MES demandes (user_id strict), service et étapes chargés d'avance. Aucun nom d'agent, aucune donnée d'un autre.
     *
     * @return array<string, mixed>
     */
    public function dossier(User $user): array
    {
        $maintenant = now();
        $demandes = $this->demandes($user);
        $date = fn ($d) => $d ? DateLocale::format($d) : null;

        $lignes = $demandes->map(function (Demande $d) use ($maintenant, $date) {
            $delai = $d->statut === Statut::Traitee && $d->traitee_at
                ? max(0.0, round($d->created_at->diffInMinutes($d->traitee_at, true) / 1440, 1))
                : null;

            return [
                'demande' => $d,
                'service' => $d->service?->nom ?? __('À orienter'),
                'age_jours' => (int) floor($d->created_at->diffInDays($maintenant, true)),
                'delai_jours' => $delai,
                'etape' => __('dossier.etapes.'.$d->statut->value),
                'signification' => __('dossier.statuts.'.$d->statut->value),
                'chronologie' => $d->etapes->map(fn (DemandeEtape $e) => __('dossier.chronologie.'.$e->statut->value, ['date' => DateLocale::format($e->created_at)]))->all(),
            ];
        });

        $total = $lignes->count();
        $parStatut = collect(Statut::cases())->mapWithKeys(fn (Statut $s) => [$s->value => $lignes->filter(fn ($l) => $l['demande']->statut === $s)->count()]);
        $delais = $lignes->pluck('delai_jours')->filter(fn ($v) => $v !== null);
        $plusAncienneAttente = $lignes->filter(fn ($l) => $l['demande']->statut === Statut::Nouvelle)->min(fn ($l) => $l['demande']->created_at);
        $activites = $demandes->flatMap(fn (Demande $d) => $d->etapes->pluck('created_at')->push($d->updated_at));
        $nonLus = $demandes->sum(fn (Demande $d) => $d->etapes->filter(fn (DemandeEtape $e) => $e->statut !== Statut::Nouvelle && $e->vu_at === null)->count());

        $resume = $this->resume($user);

        // Réponses de la mairie à MES demandes (user_id strict), de la plus ancienne à la plus récente : une requête.
        $reponses = DemandeReponse::query()
            ->join('demandes', 'demandes.id', '=', 'demande_reponses.demande_id')
            ->where('demandes.user_id', $user->id)
            ->select('demande_reponses.created_at', 'demande_reponses.texte', 'demandes.reference')
            ->orderBy('demande_reponses.created_at')->orderBy('demande_reponses.id')
            ->toBase()->get()
            ->map(fn ($r) => ['reference' => $r->reference, 'date' => DateLocale::format($r->created_at), 'texte' => $r->texte]);

        // Participation : MES contributions (user_id strict), de la plus ancienne à la plus récente, une requête.
        $contributions = Contribution::where('user_id', $user->id)->with(['projet', 'service'])->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (Contribution $c) => [
                'reference' => $c->reference,
                'type' => $c->type->label(),
                'intitule' => $c->intitule(),
                'date' => DateLocale::format($c->created_at),
                'statut' => $c->statut->label(),
                'message' => $c->message,
                'reponse' => $c->reponse,
                'prise_en_compte' => $c->statut === \App\Enums\StatutContribution::PriseEnCompte,
            ]);

        return [
            'compte' => [
                'nom' => $user->name,
                'email' => $user->email,
                'inscrit_le' => $resume['inscrit_le'],
                'anciennete_jours' => (int) floor($user->created_at->diffInDays($maintenant, true)),
                'role' => __('dossier.roles.'.$user->role->value),
            ],
            'preferences' => ['taille_texte' => $resume['taille_texte'], 'theme' => $resume['theme']],
            'lignes' => $lignes,
            'total' => $total,
            'par_statut' => $parStatut,
            'premiere_demande' => $date($demandes->min('created_at')),
            'derniere_demande' => $date($demandes->max('created_at')),
            'delai_moyen_jours' => $delais->isEmpty() ? null : round($delais->avg(), 1),
            'attente_jours' => $plusAncienneAttente ? (int) floor(Carbon::parse($plusAncienneAttente)->diffInDays($maintenant, true)) : null,
            'derniere_activite' => $date($activites->max()),
            'non_lus' => (int) $nonLus,
            'contributions' => $contributions,
            'reponses' => $reponses,
            'phrases_contributions' => $this->phrasesContributions($contributions),
            'genere_le' => DateLocale::format($maintenant),
            'conservation' => config('dossier.conservation'),
            'phrases' => $this->phrases($total, $parStatut, $plusAncienneAttente ? (int) floor(Carbon::parse($plusAncienneAttente)->diffInDays($maintenant, true)) : null,
                $delais->isEmpty() ? null : round($delais->avg(), 1), $date($activites->max()), (int) $nonLus),
        ];
    }

    /** @return array<string, string> phrases de synthèse de la participation (accords compris) */
    private function phrasesContributions(Collection $contributions): array
    {
        if ($contributions->isEmpty()) {
            return ['bilan' => __('suivi_participation.dossier.aucune')];
        }

        return [
            'bilan' => trans_choice('suivi_participation.dossier.total', $contributions->count()).' '
                .trans_choice('suivi_participation.dossier.prises_en_compte', $contributions->where('prise_en_compte', true)->count()),
        ];
    }

    /**
     * Phrases de synthèse (accords singulier/pluriel compris). Sans demande : une phrase propre, aucun calcul sur zéro.
     *
     * @return array<string, string|null>
     */
    private function phrases(int $total, Collection $parStatut, ?int $attenteJours, ?float $delaiMoyen, ?string $derniereActivite, int $nonLus): array
    {
        if ($total === 0) {
            return ['bilan' => __('dossier.synthese.aucune'), 'attente' => null, 'delai' => null, 'activite' => null, 'non_lus' => trans_choice('dossier.synthese.non_lus', 0)];
        }

        return [
            'bilan' => trans_choice('dossier.synthese.total', $total).' : '
                .trans_choice('dossier.synthese.nouvelles', $parStatut['nouvelle']).', '
                .trans_choice('dossier.synthese.en_cours', $parStatut['en_cours']).', '
                .trans_choice('dossier.synthese.traitees', $parStatut['traitee']).'.',
            'attente' => $attenteJours === null ? __('dossier.synthese.aucune_attente') : trans_choice('dossier.synthese.attente', $attenteJours),
            'delai' => $delaiMoyen === null ? __('dossier.synthese.aucun_delai') : __('dossier.synthese.delai', ['jours' => number_format($delaiMoyen, 1, ',', ' ')]),
            'activite' => __('dossier.synthese.activite', ['date' => $derniereActivite]),
            'non_lus' => trans_choice('dossier.synthese.non_lus', $nonLus),
        ];
    }

    /**
     * Rend un document .html autonome. Ses liens absolus partent de APP_URL (config('app.url')) : jamais de l'hôte de la requête,
     * qui peut être interne (proxy, serveur local) alors que le fichier sera ouvert bien après, hors de la plateforme.
     */
    public function rendreAutonome(string $vue, array $donnees): string
    {
        URL::forceRootUrl(config('app.url'));
        URL::forceScheme(parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: null);

        try {
            return view($vue, $donnees)->render();
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }

    /** Mes demandes (user_id strict), de la plus ancienne à la plus récente, avec service et étapes chargés d'avance. */
    public function demandes(User $user): Collection
    {
        return Demande::where('user_id', $user->id)->with(['service', 'etapes'])->orderBy('created_at')->orderBy('id')->get();
    }

}
