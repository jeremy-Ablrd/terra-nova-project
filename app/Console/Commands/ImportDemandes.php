<?php

namespace App\Console\Commands;

use App\Services\ImporteDemandesApi;
use App\Services\NovaTerraApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportDemandes extends Command
{
    protected $signature = 'novaterra:import-demandes';

    protected $description = 'Importe dans les demandes les demandes « Citoyen » déjà présentes dans api_requests (sans appel réseau)';

    public function handle(NovaTerraApi $api, ImporteDemandesApi $importeur): int
    {
        try {
            // Même verrou que la synchronisation : jamais d'import en parallèle d'une synchro.
            $result = $api->withLock(fn () => ['imported' => $importeur->import()]);
        } catch (Throwable $e) {
            Log::error('novaterra:import-demandes a échoué : '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['busy']) {
            $this->info('Une synchronisation est déjà en cours : rien n\'a été lancé.');

            return self::SUCCESS;
        }

        $this->info(trans_choice(NovaTerraApi::IMPORT_MESSAGE, $result['imported']));

        return self::SUCCESS;
    }
}
