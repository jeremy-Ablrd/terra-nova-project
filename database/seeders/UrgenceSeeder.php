<?php

namespace Database\Seeders;

use App\Enums\CategorieService;
use App\Enums\Disponibilite;
use App\Models\Service;
use Illuminate\Database\Seeder;

class UrgenceSeeder extends Seeder
{
    /**
     * F46 : un hôpital (quartier sud) et un service d'urgence (quartier est), avec adresse, repère et téléphone.
     * Ne touche qu'à ces deux services : l'état des autres (disponibilité, priorité…) n'est jamais modifié.
     * Relançable sans doublon (clé : le slug).
     */
    public function run(): void
    {
        $services = [
            [
                'nom' => 'Hôpital de Nova Terra',
                'slug' => 'hopital-nova-terra',
                'resume' => 'Hôpital général : consultations spécialisées, hospitalisation, maternité et imagerie médicale.',
                'description' => 'L\'hôpital de Nova Terra accueille les patients pour les consultations spécialisées, les hospitalisations, la maternité et l\'imagerie médicale. Pour une urgence, rendez-vous directement aux urgences de la ville.',
                'horaires' => 'Accueil 24h/24 ; consultations de 8h00 à 18h00',
                'adresse' => '45 boulevard de l\'Hôpital',
                'quartier' => 'Quartier sud',
                'repere' => 'Face au parc des Flamboyants, arrêt de bus « Hôpital »',
                'telephone' => '0262 55 01 15',
                'contact' => 'hopital@novaterra.test',
                'icone' => 'heart',
                'categorie' => CategorieService::Sante,
                'urgence' => false,
                'ordre' => 9,
            ],
            [
                'nom' => 'Urgences de Nova Terra',
                'slug' => 'urgences-nova-terra',
                'resume' => 'Accueil des urgences médicales et chirurgicales, jour et nuit.',
                'description' => 'Le service des urgences accueille jour et nuit toute personne dont l\'état nécessite une prise en charge rapide. En cas de détresse vitale, appelez le 15 avant de vous déplacer.',
                'horaires' => 'Ouvert 24h/24, 7j/7',
                'adresse' => '8 avenue des Alizés',
                'quartier' => 'Quartier est',
                'repere' => 'Entrée des ambulances côté rue du Phare, à 200 m du rond-point des Alizés',
                'telephone' => '15',
                'contact' => 'urgences@novaterra.test',
                'icone' => 'siren',
                'categorie' => CategorieService::Sante,
                'urgence' => true,
                'ordre' => 10,
            ],
        ];

        foreach ($services as $donnees) {
            $service = Service::firstOrNew(['slug' => $donnees['slug']]);
            $service->fill($donnees + [
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
