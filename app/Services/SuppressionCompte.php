<?php

namespace App\Services;

use App\Enums\ActionJournal;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seul chemin de suppression d'un compte citoyen (F33 ; réutilisable par la gestion des comptes citoyens par un agent, F34).
 *
 * Règles : le compte, ses sessions, ses préférences et ses jetons de réinitialisation sont supprimés. Ses demandes sont
 * ANONYMISÉES, jamais supprimées : user_id nul, objet et message remplacés, anonymisee_at renseigné ; référence, service,
 * statut, dates et étapes restent (aucune donnée personnelle). Tout est écrit dans UNE transaction avec le journal :
 * si une écriture échoue, rien n'est modifié.
 */
class SuppressionCompte
{
    public const TEXTE_SUPPRIME = "[Contenu supprimé à la demande de l'habitant]";

    /**
     * @param  User|null  $acteur  null ou le compte lui-même : suppression par son titulaire ; un agent : suppression par un agent
     *
     * @throws SuppressionRefusee si le compte n'est pas un compte citoyen
     */
    public function supprimer(User $compte, ?User $acteur = null): void
    {
        $this->verifier($compte);
        $parSonTitulaire = $acteur === null || $acteur->is($compte);

        DB::transaction(function () use ($compte, $acteur, $parSonTitulaire) {
            // Le rôle est revérifié sous verrou : un compte devenu agent entre-temps ne doit jamais être supprimé ici.
            $compte = User::whereKey($compte->id)->lockForUpdate()->firstOrFail();
            $this->verifier($compte);

            // Anonymisation : updated_at est conservé tel quel (la date de dernière mise à jour fait partie de ce qui reste).
            Demande::where('user_id', $compte->id)->update([
                'objet' => self::TEXTE_SUPPRIME,
                'message' => self::TEXTE_SUPPRIME,
                'user_id' => null,
                'anonymisee_at' => now(),
                'updated_at' => DB::raw('updated_at'),
            ]);

            // Toutes les sessions du compte (tous les appareils) et ses jetons de réinitialisation de mot de passe.
            $sessions = config('session.table', 'sessions');
            if (Schema::hasTable($sessions)) {
                DB::table($sessions)->where('user_id', $compte->id)->delete();
            }
            DB::table('password_reset_tokens')->where('email', $compte->email)->delete();

            // Numéro de compte seulement : ni nom ni e-mail dans le journal.
            Journal::enregistrer($parSonTitulaire ? $compte : $acteur, ActionJournal::CompteSupprime, $compte,
                'Compte n° '.$compte->id.($parSonTitulaire ? ' supprimé par son titulaire' : ' supprimé par un agent'));

            $compte->delete(); // préférences supprimées avec la ligne
        });
    }

    private function verifier(User $compte): void
    {
        if (! $compte->isCitoyen()) {
            throw new SuppressionRefusee(__('Seul un compte citoyen peut être supprimé par ce parcours.'));
        }
    }
}
