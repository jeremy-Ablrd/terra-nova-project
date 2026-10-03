<?php

namespace App\Services;

use App\Enums\Statut;
use App\Enums\TailleTexte;
use App\Enums\ThemeAffichage;
use App\Models\Demande;
use App\Models\DemandeEtape;
use App\Models\User;
use App\Support\DateLocale;
use Illuminate\Support\Collection;

/**
 * Données personnelles d'un habitant (F55, F56) : une seule source pour la page « Mes données », l'export JSON et le
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

    /** Mes demandes (user_id strict), de la plus ancienne à la plus récente, avec service et étapes chargés d'avance. */
    public function demandes(User $user): Collection
    {
        return Demande::where('user_id', $user->id)->with(['service', 'etapes'])->orderBy('created_at')->orderBy('id')->get();
    }

    /**
     * Contenu de l'export JSON : libellés en français, dates au format local, chronologie sans nom d'agent.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $resume = $this->resume($user);
        $date = fn ($d) => $d ? DateLocale::format($d) : null;

        return [
            'description' => [
                'objet' => __('Informations personnelles que la ville de Nova Terra conserve sur votre compte, et vos demandes avec leur chronologie.'),
                'champs' => [
                    'genere_le' => __('Date et heure de la création de ce fichier (heure de la Réunion).'),
                    'compte' => __('Votre nom, votre adresse e-mail, votre rôle et vos dates clés. Le mot de passe n\'est jamais inclus.'),
                    'preferences_affichage' => __('Vos réglages d\'affichage (taille du texte, thème).'),
                    'demandes_par_statut' => __('Nombre de vos demandes dans chaque statut.'),
                    'dernieres_activites' => __('Dates de vos dernières activités.'),
                    'demandes' => __('Vos demandes : référence, objet, message, service, statut, dates, et chronologie des étapes de suivi (le nom de l\'agent n\'est pas communiqué).'),
                ],
            ],
            'genere_le' => DateLocale::format(now()),
            'compte' => [
                'nom' => $resume['nom'],
                'adresse_email' => $resume['email'],
                'role' => $resume['role'],
                'inscrit_le' => $resume['inscrit_le'],
                'profil_mis_a_jour_le' => $resume['profil_mis_a_jour_le'],
            ],
            'preferences_affichage' => [
                'taille_du_texte' => $resume['taille_texte'],
                'theme' => $resume['theme'],
            ],
            'demandes_par_statut' => collect(Statut::cases())
                ->mapWithKeys(fn (Statut $s) => [$s->label() => $resume['par_statut'][$s->value]])
                ->put(__('Total'), $resume['total'])
                ->all(),
            'dernieres_activites' => [
                'derniere_demande_deposee_le' => $date($resume['derniere_demande']),
                'derniere_mise_a_jour_d_une_demande_le' => $date($resume['derniere_mise_a_jour']),
                'dernier_changement_d_etat_le' => $date($resume['dernier_changement']),
            ],
            'demandes' => $this->demandes($user)->map(fn (Demande $d) => [
                'reference' => $d->reference,
                'objet' => $d->objet,
                'message' => $d->message,
                'service' => $d->service?->nom,
                'statut' => $d->statut->label(),
                'creee_le' => $date($d->created_at),
                'mise_a_jour_le' => $date($d->updated_at),
                'traitee_le' => $date($d->traitee_at),
                'chronologie' => $d->etapes->map(fn (DemandeEtape $e) => [
                    'statut' => $e->statut->label(),
                    'date' => $date($e->created_at),
                    'par' => $e->statut === Statut::Nouvelle ? null : __('Un agent'),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
