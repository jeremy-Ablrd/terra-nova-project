<?php

namespace App\Services;

use App\Models\Alerte;
use Illuminate\Support\Collection;

/**
 * Alertes en cours, triées par priorité. UNE seule requête par requête HTTP, quel que soit le nombre de
 * composants qui la demandent (bandeau, page /alertes) : le résultat est mémorisé sur l'objet Request, pas
 * dans un singleton (une nouvelle requête, ou un nouveau test, repart d'une base à jour).
 */
class AlertesActives
{
    private const CLE = 'alertes_actives';

    /** @return Collection<int, Alerte> */
    public function get(): Collection
    {
        $attributs = request()->attributes;

        if (! $attributs->has(self::CLE)) {
            $attributs->set(self::CLE, Alerte::active()->parPriorite()->get());
        }

        return $attributs->get(self::CLE);
    }
}
