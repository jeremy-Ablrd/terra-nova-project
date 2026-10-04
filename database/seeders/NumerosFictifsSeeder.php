<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Remplace par des numéros fictifs les numéros de démonstration de UrgenceSeeder qui ressemblent à de vrais numéros :
 * « 15 » (SAMU) et « 0262 55 01 15 » (indicatif de La Réunion). Les nouveaux numéros sont dans la plage 02 61 91 xx xx,
 * que l'ARCEP réserve à la fiction (à vérifier avant publication).
 * Idempotent, et prudent : un numéro n'est remplacé que s'il vaut encore l'ancienne valeur de démonstration, jamais s'il a
 * été saisi par l'admin. UrgenceSeeder n'est pas relancé.
 * Sur un serveur existant : php artisan db:seed --class=NumerosFictifsSeeder --force
 */
class NumerosFictifsSeeder extends Seeder
{
    /** slug => [ancien numéro de démonstration, numéro fictif] */
    private const NUMEROS = [
        'hopital-nova-terra' => ['0262 55 01 15', '02 61 91 55 01'],
        'urgences-nova-terra' => ['15', '02 61 91 55 15'],
    ];

    public function run(): void
    {
        foreach (self::NUMEROS as $slug => [$ancien, $fictif]) {
            $service = Service::where('slug', $slug)->where('telephone', $ancien)->first();

            if ($service) {
                $service->telephone = $fictif;
                $service->save();
            }
        }
    }
}
