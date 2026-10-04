<?php

namespace App\Console\Commands;

use App\Services\NovaTerraApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncNovaTerra extends Command
{
    protected $signature = 'novaterra:sync';

    protected $description = 'Récupère les demandes de l\'API Terra Nova et les enregistre en base';

    public function handle(NovaTerraApi $api): int
    {
        // Fin de la synchro, réussie ou non : purge du journal de sécurité (au plus une fois par heure, la synchro tourne toutes les 30 s).
        try {
            return $this->synchroniser($api);
        } finally {
            if (Cache::add('novaterra.securite.purge', true, 3600)) {
                $this->callSilently('novaterra:purger-securite');
            }
        }
    }

    private function synchroniser(NovaTerraApi $api): int
    {
        try {
            // Toute exception (API ou autre) est mémorisée dans le cache last_error par le service.
            $result = $api->sync();
        } catch (Throwable $e) {
            Log::error('novaterra:sync a échoué : '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['busy']) {
            $this->info('Une synchronisation est déjà en cours : rien n\'a été lancé.');

            return self::SUCCESS;
        }

        $this->info("{$result['received']} demandes reçues, {$result['new']} nouvelles.");
        $this->info(trans_choice(NovaTerraApi::IMPORT_MESSAGE, $result['imported']));

        return self::SUCCESS;
    }
}
