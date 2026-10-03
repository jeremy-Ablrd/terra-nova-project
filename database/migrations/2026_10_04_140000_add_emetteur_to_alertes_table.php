<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive (F73) : l'émetteur officiel d'une alerte, stocké sous forme de clé (haut_conseil, ville,
     * service_communication). Les alertes existantes deviennent « ville ». Aucune donnée n'est supprimée.
     */
    public function up(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->string('emetteur', 40)->default('ville')->after('niveau');
        });
    }

    public function down(): void
    {
        Schema::table('alertes', function (Blueprint $table) {
            $table->dropColumn('emetteur');
        });
    }
};
