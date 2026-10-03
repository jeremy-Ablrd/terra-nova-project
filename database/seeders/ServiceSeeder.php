<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
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
            ],
        ];

        foreach ($services as $ordre => $service) {
            Service::updateOrCreate(['slug' => $service['slug']], $service + ['ordre' => $ordre + 1, 'actif' => true]);
        }
    }
}
