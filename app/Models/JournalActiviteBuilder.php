<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Requêtes sur le journal : lecture et insertion seulement. Toute mise à jour ou suppression en masse est refusée
 * (le modèle refuse déjà celles d'une entrée isolée). Une suppression directement en base reste possible : le
 * journal est protégé côté application, pas par le moteur de base de données.
 */
class JournalActiviteBuilder extends Builder
{
    private static function refuser(): never
    {
        throw new LogicException('Le journal d\'activité est en lecture seule : aucune mise à jour ni suppression.');
    }

    public function update(array $values)
    {
        self::refuser();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        self::refuser();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        self::refuser();
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        self::refuser();
    }

    public function delete()
    {
        self::refuser();
    }

    public function forceDelete()
    {
        self::refuser();
    }

    public function truncate()
    {
        self::refuser();
    }
}
