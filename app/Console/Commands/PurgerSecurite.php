<?php

namespace App\Console\Commands;

use App\Models\EvenementSecurite;
use Illuminate\Console\Command;

class PurgerSecurite extends Command
{
    protected $signature = 'novaterra:purger-securite';

    protected $description = 'Supprime les événements du journal de sécurité de plus de 30 jours';

    public const JOURS = 30;

    public function handle(): int
    {
        $supprimes = EvenementSecurite::where('created_at', '<', now()->subDays(self::JOURS))->delete();

        $this->info("{$supprimes} événement(s) de sécurité de plus de ".self::JOURS.' jours supprimé(s).');

        return self::SUCCESS;
    }
}
