<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Synchronisation des demandes de l'API toutes les 30 s (nécessite `php artisan schedule:work`).
// Inactive tant que la clé API est vide.
Schedule::command('novaterra:sync')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->when(fn () => filled(config('services.webcup.key')));
