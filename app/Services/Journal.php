<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Enums\Disponibilite;
use App\Models\Alerte;
use App\Models\Demande;
use App\Models\JournalActivite;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Journal d'activité : qui a fait quoi, sur quel objet, quand.
 *
 * Règle : toute action de modification faite par un agent ou un admin appelle Journal::enregistrer() DANS LA MÊME
 * DB::transaction que la modification, pour qu'aucune modification ne reste sans trace.
 *
 * Confidentialité : jamais de mot de passe, de contenu de message, d'e-mail ni de donnée personnelle d'habitant dans
 * le libellé de l'objet ou le détail. Seulement qui, quoi, quand, sur quel objet.
 */
class Journal
{
    /** Noms lisibles des champs d'un service modifié (le détail ne cite que ces noms, jamais les valeurs). */
    private const CHAMPS_SERVICE = [
        'motif_interruption' => 'motif',
        'retour_estime_at' => 'retour estimé',
        'alternative' => 'alternative',
        'adresse' => 'adresse',
        'quartier' => 'quartier',
        'repere' => 'repère',
        'telephone' => 'téléphone',
        'urgence' => 'urgence',
    ];

    /**
     * Crée une entrée (save()). Seuls les agents et les admins sont journalisés.
     *
     * @throws InvalidArgumentException si l'acteur n'est ni agent ni admin
     */
    public static function enregistrer(User $acteur, ActionJournal $action, ?Model $objet = null, ?string $detail = null): JournalActivite
    {
        if (! $acteur->isAgent() && ! $acteur->isAdmin()) {
            throw new InvalidArgumentException('Seules les actions des agents et des admins sont journalisées.');
        }

        [$type, $id, $libelle] = self::decrire($action, $objet);

        $entree = new JournalActivite;
        $entree->acteur_id = $acteur->id;
        $entree->acteur_nom = $acteur->name;         // copie : reste lisible si le compte est supprimé
        $entree->acteur_role = $acteur->role;
        $entree->action = $action;
        $entree->objet_type = $type;
        $entree->objet_id = $id;
        $entree->objet_libelle = Str::limit($libelle, 255, '');
        $entree->detail = $detail === null ? null : Str::limit($detail, 254, '…');
        $entree->save();

        return $entree;
    }

    /**
     * Détail d'un service modifié : « disponibilité : A → B ; priorité : non → oui ; autres champs : motif, adresse ».
     * Les valeurs ne sont données que pour la disponibilité et la priorité ; pour le reste, seulement les noms de champs.
     *
     * @param  array{disponibilite?: array{0: Disponibilite, 1: Disponibilite}, prioritaire?: array{0: bool, 1: bool}, champs?: list<string>}  $changements
     */
    public static function detailService(array $changements): string
    {
        $parties = [];

        if (isset($changements['disponibilite'])) {
            [$avant, $apres] = $changements['disponibilite'];
            $parties[] = 'disponibilité : '.$avant->label().' → '.$apres->label();
        }
        if (isset($changements['prioritaire'])) {
            [$avant, $apres] = $changements['prioritaire'];
            $parties[] = 'priorité : '.($avant ? 'oui' : 'non').' → '.($apres ? 'oui' : 'non');
        }
        if (! empty($changements['champs'])) {
            $noms = array_map(fn (string $champ) => self::CHAMPS_SERVICE[$champ] ?? $champ, $changements['champs']);
            $parties[] = 'autres champs : '.implode(', ', $noms);
        }

        return implode(' ; ', $parties);
    }

    /** @return array{0: string, 1: ?int, 2: string} type, identifiant et libellé de l'objet (sans donnée personnelle) */
    private static function decrire(ActionJournal $action, ?Model $objet): array
    {
        return match (true) {
            $objet === null => [$action->objetParDefaut()[0], null, $action->objetParDefaut()[1]],
            $objet instanceof Demande => ['demande', $objet->id, (string) $objet->reference],
            $objet instanceof Service => ['service', $objet->id, $objet->nom],
            $objet instanceof Alerte => ['alerte', $objet->id, $objet->titre],
            // Un compte n'est désigné que par son numéro : ni nom ni e-mail (données personnelles).
            $objet instanceof User => ['compte', $objet->id, 'Compte n° '.$objet->id],
            default => [Str::snake(class_basename($objet)), $objet->getKey(), class_basename($objet).' n° '.$objet->getKey()],
        };
    }
}
