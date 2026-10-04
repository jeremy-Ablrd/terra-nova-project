<?php

namespace Database\Seeders;

use App\Enums\StatutContribution;
use App\Enums\TypeContribution;
use App\Models\Contribution;
use App\Models\ContributionEtape;
use App\Models\Projet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Participation de démonstration (F65, F66, F67, F68, F76) : trois projets et quelques contributions du citoyen de démonstration.
 * Données fictives, dates relatives à maintenant. Idempotent : un projet est repéré par son slug, une contribution par
 * (compte, type, projet ou service, titre) ; rien n'est modifié sur ce qui existe déjà.
 * Sur un serveur existant : php artisan db:seed --class=ParticipationSeeder --force (ServiceSeeder n'est pas relancé).
 */
class ParticipationSeeder extends Seeder
{
    public function run(): void
    {
        $projets = [
            'amenagement-place-du-port' => [
                'titre' => 'Aménagement de la place du Port',
                'resume' => 'Plus d\'arbres, des bancs et un espace piéton devant le port.',
                'description' => "La ville envisage de rendre la place du Port aux piétons : plantation d'arbres, bancs, point d'eau et marché le samedi.\nLa consultation sert à recueillir vos avis avant que la ville ne décide. Un avis n'est pas un vote : chaque contribution est lue et reçoit une réponse.",
                'debut' => now()->subDays(5), 'fin' => now()->addDays(20), 'bilan' => null,
            ],
            'nouveau-reseau-de-bus' => [
                'titre' => 'Nouveau réseau de bus',
                'resume' => 'Revoir les lignes et les horaires des bus entre les quartiers.',
                'description' => "La ville prépare une refonte des lignes de bus pour relier plus directement les quartiers et l'hôpital.\nLa consultation s'ouvrira prochainement.",
                'debut' => now()->addDays(10), 'fin' => now()->addDays(40), 'bilan' => null,
            ],
            'jardins-partages' => [
                'titre' => 'Jardins partagés dans les quartiers',
                'resume' => 'Des parcelles cultivées ensemble sur des terrains communaux.',
                'description' => "Trois terrains communaux ont été proposés pour accueillir des jardins partagés.\nLa consultation est terminée : voici ce que la ville en retient.",
                'debut' => now()->subDays(60), 'fin' => now()->subDays(20),
                'bilan' => "Les habitants ont demandé des points d'eau et des outils partagés. La ville retient deux terrains sur trois et installera un point d'eau sur chacun.",
            ],
        ];

        foreach ($projets as $slug => $p) {
            if (Projet::where('slug', $slug)->exists()) {
                continue;
            }
            $projet = new Projet;
            $projet->fill(['titre' => $p['titre'], 'resume' => $p['resume'], 'description' => $p['description'],
                'consultation_debut_at' => $p['debut'], 'consultation_fin_at' => $p['fin'], 'bilan' => $p['bilan']]);
            $projet->slug = $slug;
            $projet->publie_at = now()->subDays(70);
            $projet->save();
        }

        $citoyen = User::where('email', 'citoyen@novaterra.test')->first();
        if (! $citoyen) {
            return;
        }

        $port = Projet::where('slug', 'amenagement-place-du-port')->first();
        $jardins = Projet::where('slug', 'jardins-partages')->first();
        $etatCivil = Service::where('slug', 'etat-civil')->first();

        $this->contribuer($citoyen, TypeContribution::Avis, $port, null, null,
            'Je suis favorable aux arbres, mais il faudrait garder quelques places de stationnement pour les personnes à mobilité réduite.',
            StatutContribution::Recue, null, now()->subDays(2));

        $this->contribuer($citoyen, TypeContribution::Idee, null, null, 'Une boîte à livres au marché',
            'Installer une boîte à livres près du marché du samedi pour échanger des livres entre habitants.',
            StatutContribution::Examinee, 'Merci pour cette idée : le service culture et loisirs l\'étudie avec les bibliothécaires.', now()->subDays(8));

        $this->contribuer($citoyen, TypeContribution::Avis, $jardins, null, null,
            'Pensez à prévoir un point d\'eau sur chaque terrain, c\'est indispensable pour arroser.',
            StatutContribution::PriseEnCompte, 'Merci : un point d\'eau sera installé sur chacun des deux terrains retenus.', now()->subDays(45));

        if ($etatCivil) {
            $this->contribuer($citoyen, TypeContribution::Commentaire, null, $etatCivil, null,
                'Accueil rapide et clair, j\'ai obtenu mon acte en vingt minutes.',
                StatutContribution::Recue, null, now()->subDay());
        }
    }

    /**
     * Crée la contribution par save() (l'événement du modèle génère la référence et l'étape « reçue »), puis ajoute les étapes
     * suivantes avec des dates cohérentes. Ne fait rien si elle existe déjà.
     */
    private function contribuer(User $user, TypeContribution $type, ?Projet $projet, ?Service $service, ?string $titre, string $message,
        StatutContribution $statut, ?string $reponse, \DateTimeInterface $date): void
    {
        $existe = Contribution::where('user_id', $user->id)->where('type', $type->value)
            ->where('projet_id', $projet?->id)->where('service_id', $service?->id)->where('titre', $titre)->exists();
        if ($existe || ($type === TypeContribution::Avis && ! $projet)) {
            return;
        }

        $c = new Contribution;
        $c->fill(['titre' => $titre, 'message' => $message]);
        $c->user_id = $user->id;
        $c->type = $type;
        $c->projet_id = $projet?->id;
        $c->service_id = $service?->id;
        $c->statut = $statut;
        $c->reponse = $reponse;
        $c->reponse_at = $reponse ? \Illuminate\Support\Carbon::instance($date)->addDays(3) : null;
        $c->created_at = $date;
        $c->updated_at = $date;
        $c->save();

        // Étapes suivantes (la première, « reçue », est créée par l'événement) ; déjà vues : pas d'encart pour les données de démonstration.
        $ordre = [StatutContribution::Recue, StatutContribution::Examinee, StatutContribution::PriseEnCompte];
        $atteint = array_search($statut, $ordre, true);
        foreach ([1, 2] as $rang) {
            if ($rang <= $atteint) {
                $e = new ContributionEtape;
                $e->contribution_id = $c->id;
                $e->statut = $ordre[$rang];
                $e->vu_at = now();
                $e->created_at = \Illuminate\Support\Carbon::instance($date)->addDays($rang + 1);
                $e->save();
            }
        }
    }
}
