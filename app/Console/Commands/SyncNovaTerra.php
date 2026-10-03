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
            $result = $api->sync();
        } catch (Throwable $e) {
            Log::error('novaterra:sync a échoué : '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$result['received']} demandes reçues, {$result['new']} nouvelles.");

        return self::SUCCESS;
    }
}
