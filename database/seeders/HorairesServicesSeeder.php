<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Horaires d'ouverture structurés (F74) pour les services de démonstration, par slug. Données fictives.
 * Idempotent : ne touche que les services dont les horaires structurés sont vides, jamais ceux saisis par l'admin.
 * Sur un serveur existant : php artisan db:seed --class=HorairesServicesSeeder --force (ServiceSeeder n'est pas relancé).
 */
class HorairesServicesSeeder extends Seeder
{
    public function run(): void
    {
        $semaine = fn (array $parJour) => collect(Service::JOURS)->mapWithKeys(fn ($jour) => [$jour => $parJour[$jour] ?? []])->all();
        $joursOuvres = fn (array $plages, array $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']) => collect($jours)->mapWithKeys(fn ($j) => [$j => $plages])->all();

        $horaires = [
            'etat-civil' => $semaine($joursOuvres([['08:30', '17:00']])),
            'urbanisme' => $semaine($joursOuvres([['09:00', '12:00'], ['14:00', '17:00']], ['lundi', 'mardi', 'mercredi', 'jeudi'])),
            'voirie-proprete' => $semaine($joursOuvres([['07:30', '16:30']])),
            'transports' => $semaine($joursOuvres([['08:00', '18:00']], ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'])),
            'culture-loisirs' => $semaine($joursOuvres([['10:00', '18:00']], ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'])),
            'aides-sociales' => $semaine($joursOuvres([['09:00', '12:00'], ['13:30', '17:00']])),
            'centre-sante-municipal' => $semaine($joursOuvres([['08:00', '12:00'], ['13:30', '18:00']]) + ['samedi' => [['08:00', '12:00']]]),
            'prevention-vaccination' => $semaine($joursOuvres([['09:00', '12:00'], ['14:00', '16:30']])),
            // Urgences : ouvertes en continu (une plage ne passe pas minuit : 00:00 – 23:59).
            'hopital-nova-terra' => $semaine($joursOuvres([['00:00', '23:59']], Service::JOURS)),
            'urgences-nova-terra' => $semaine($joursOuvres([['00:00', '23:59']], Service::JOURS)),
        ];

        foreach ($horaires as $slug => $semaineDuService) {
            $service = Service::where('slug', $slug)->whereNull('horaires_semaine')->first();

            if ($service) {
                $service->horaires_semaine = $semaineDuService;
                $service->save();
            }
        }
    }
}
