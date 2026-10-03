<?php

namespace Database\Seeders;

use App\Enums\Niveau;
use App\Models\Alerte;
use App\Models\User;
use Illuminate\Database\Seeder;

class AlerteSeeder extends Seeder
{
    /**
     * Deux alertes de démonstration (F29 montée des eaux, F31 canicule).
     * Les dates sont RELATIVES à maintenant : relancer le seeder les remet « en cours », elles n'expirent jamais.
     * Relançable sans doublon (clé : le titre).
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@novaterra.test')->value('id');

        $alertes = [
            [
                'titre' => 'Montée du niveau de l\'eau dans le quartier sud',
                'niveau' => Niveau::Urgent,
                'secteur' => 'Quartier sud',
                'ce_qui_se_passe' => 'Le Centre de surveillance environnementale observe une montée inhabituelle du niveau de l\'eau dans le quartier sud.',
                'ce_quil_faut_faire' => 'Évitez les rues basses et les parkings souterrains du quartier sud, ne traversez pas une zone inondée et suivez les consignes des agents sur place.',
                'consignes_vulnerables' => null,
                'starts_at' => now()->subHours(2),
                'ends_at' => now()->addDays(2),
            ],
            [
                'titre' => 'Vague de chaleur extrême',
                'niveau' => Niveau::Vigilance,
                'secteur' => 'Plusieurs secteurs de la ville',
                'ce_qui_se_passe' => 'Une vague de chaleur extrême touche actuellement plusieurs secteurs de Nova Terra. Les températures restent très élevées jour et nuit.',
                'ce_quil_faut_faire' => 'Buvez régulièrement, évitez les efforts physiques aux heures chaudes et passez du temps dans des lieux frais ou climatisés.',
                'consignes_vulnerables' => 'Personnes âgées, jeunes enfants, personnes malades ou isolées : restez au frais, buvez même sans soif, faites-vous rendre visite deux fois par jour et appelez les secours au moindre malaise.',
                'starts_at' => now()->subHours(6),
                'ends_at' => now()->addDays(3),
            ],
        ];

        foreach ($alertes as $donnees) {
            $alerte = Alerte::firstOrNew(['titre' => $donnees['titre']]);
            $alerte->fill($donnees);
            $alerte->user_id = $adminId; // « publiée par » : fixé explicitement, hors fillable
            $alerte->save();
        }
    }
}
