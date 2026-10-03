<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Format d'affichage unique des dates dans les vues : jj/mm/aaaa HH:mm, en heure locale (APP_TIMEZONE).
 * Les vues n'affichent jamais une date brute : elles passent toutes par ici.
 */
class DateLocale
{
    public const FORMAT = 'd/m/Y H:i';

    public static function format(CarbonInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return Carbon::parse($date)->timezone(config('app.timezone'))->format(self::FORMAT);
    }
}
