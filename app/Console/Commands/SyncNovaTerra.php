<?php

namespace App\Console\Commands;

use App\Services\NovaTerraApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncNovaTerra extends Command
{
    protected $signature = 'novaterra:sync';

    protected $description = 'Récupère les demandes de l\'API Nova Terra et les enregistre en base';

    public function handle(NovaTerraApi $api): int
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
