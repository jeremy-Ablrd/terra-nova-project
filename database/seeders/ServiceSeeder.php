<?php

namespace Database\Seeders;

use App\Enums\CategorieService;
use App\Enums\Disponibilite;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Catalogue de démonstration : 8 services dont 2 de santé et 2 prioritaires, avec un service interrompu.
     * Les dates sont RELATIVES à maintenant (le retour estimé reste dans le futur à chaque passage).
     * Relançable sans doublon (clé : le slug) ; relancer remet aussi l'état de démonstration des services.
     */
    public function run(): void
    {
        $services = [
            [
                'nom' => 'État civil',
                'slug' => 'etat-civil',
                'resume' => 'Actes de naissance, mariage, décès et pièces d\'identité.',
                'description' => 'Le service de l\'état civil délivre les actes officiels (naissance, mariage, décès), enregistre les déclarations et instruit les demandes de cartes d\'identité et de passeports.',
                'horaires' => 'Du lundi au vendredi, 8h30 - 17h00',
                'lieu' => 'Hôtel de ville, 1 place de la République',
                'contact' => 'etat-civil@novaterra.test',
                'icone' => 'identification',
                'categorie' => CategorieService::Administratif,
                'prioritaire' => true,
            ],
            [
                'nom' => 'Urbanisme',
                'slug' => 'urbanisme',
                'resume' => 'Permis de construire, déclarations de travaux et plan local d\'urbanisme.',
                'description' => 'Le service urbanisme instruit les permis de construire et les déclarations préalables, renseigne sur les règles du plan local d\'urbanisme et suit la conformité des travaux.',
                'horaires' => 'Du lundi au jeudi, 9h00 - 12h00 et 14h00 - 17h00',
                'lieu' => 'Maison de l\'aménagement, 14 avenue des Bâtisseurs',
                'contact' => 'urbanisme@novaterra.test',
                'icone' => 'building',
                'categorie' => CategorieService::Administratif,
            ],
            [
                'nom' => 'Voirie et propreté',
                'slug' => 'voirie-proprete',
                'resume' => 'Entretien des routes, éclairage public, collecte des déchets et encombrants.',
                'description' => 'Ce service entretient la voirie et l\'éclairage public, organise la collecte des déchets et des encombrants et traite les signalements de dégradations ou de dépôts sauvages.',
                'horaires' => 'Du lundi au vendredi, 7h30 - 16h30',
                'lieu' => 'Centre technique municipal, 8 rue des Ateliers',
                'contact' => 'voirie@novaterra.test',
                'icone' => 'truck',
                'categorie' => CategorieService::Autre,
            ],
            [
                'nom' => 'Transports',
                'slug' => 'transports',
                'resume' => 'Bus, stationnement, pistes cyclables et mobilité douce.',
                'description' => 'Le service transports gère le réseau de bus, le stationnement, les pistes cyclables et les abonnements, et répond aux questions sur les déplacements dans la ville.',
                'horaires' => 'Du lundi au samedi, 8h00 - 18h00',
                'lieu' => 'Maison de la mobilité, 3 boulevard du Port',
                'contact' => 'transports@novaterra.test',
                'icone' => 'bus',
                'categorie' => CategorieService::Transport,
                // Démonstration de F38 : interruption pour maintenance, retour estimé dans un jour.
                'disponibilite' => Disponibilite::Interrompu,
                'motif_interruption' => 'Maintenance du réseau de bus : les lignes 4 et 6 sont à l\'arrêt.',
                'retour_estime_at' => now()->addDay(),
                'alternative' => 'Utilisez les lignes 2 et 8, ou renseignez-vous à la Maison de la mobilité, 3 boulevard du Port.',
            ],
            [
                'nom' => 'Culture et loisirs',
                'slug' => 'culture-loisirs',
                'resume' => 'Bibliothèque, équipements sportifs, associations et événements.',
                'description' => 'Le service culture et loisirs gère la bibliothèque, les équipements sportifs et culturels, soutient les associations et programme les événements de la ville.',
                'horaires' => 'Du mardi au samedi, 10h00 - 18h00',
                'lieu' => 'Médiathèque Nova, 20 rue des Arts',
                'contact' => 'culture@novaterra.test',
                'icone' => 'book',
                'categorie' => CategorieService::Autre,
            ],
            [
                'nom' => 'Aides sociales',
                'slug' => 'aides-sociales',
                'resume' => 'Accompagnement des familles, aides financières et solidarité.',
                'description' => 'Le centre d\'action sociale accompagne les habitants dans leurs démarches : aides financières, logement, petite enfance et solidarité envers les personnes âgées.',
                'horaires' => 'Du lundi au vendredi, 9h00 - 12h00 et 13h30 - 17h00',
                'lieu' => 'Centre d\'action sociale, 5 rue de la Solidarité',
                'contact' => 'social@novaterra.test',
                'icone' => 'heart',
                'categorie' => CategorieService::Autre,
            ],
            [
                'nom' => 'Centre de santé municipal',
                'slug' => 'centre-sante-municipal',
                'resume' => 'Consultations de médecine générale, soins infirmiers et orientation vers un spécialiste.',
                'description' => 'Le centre de santé municipal propose des consultations de médecine générale sans dépassement d\'honoraires, des soins infirmiers, le suivi des maladies chroniques et l\'orientation vers les spécialistes et l\'hôpital.',
                'horaires' => 'Du lundi au samedi, 8h00 - 19h00',
                'lieu' => 'Centre de santé, 12 avenue de la Santé',
                'contact' => 'sante@novaterra.test',
                'icone' => 'heart',
                'categorie' => CategorieService::Sante,
                'prioritaire' => true,
            ],
            [
                'nom' => 'Prévention et vaccination',
                'slug' => 'prevention-vaccination',
                'resume' => 'Vaccinations, dépistages et conseils de prévention pour toute la famille.',
                'description' => 'Ce service organise les campagnes de vaccination et de dépistage, délivre les carnets de santé et informe sur la prévention : canicule, maladies saisonnières, santé des enfants et des seniors.',
                'horaires' => 'Du mardi au vendredi, 9h00 - 16h00',
                'lieu' => 'Centre de santé, 12 avenue de la Santé (aile est)',
                'contact' => 'prevention@novaterra.test',
                'icone' => 'shield',
                'categorie' => CategorieService::Sante,
            ],
        ];

        foreach ($services as $ordre => $donnees) {
            $service = Service::firstOrNew(['slug' => $donnees['slug']]);
            $service->fill($donnees + [
                'ordre' => $ordre + 1,
                'actif' => true,
                'prioritaire' => false,
                'disponibilite' => Disponibilite::Disponible,
                'motif_interruption' => null,
                'retour_estime_at' => null,
                'alternative' => null,
            ]);
            $service->save();
        }
    }
}
