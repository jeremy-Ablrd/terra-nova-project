<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Priorité d'une demande (F80, F86) : normale par défaut, y compris pour toutes les demandes existantes.
        Schema::table('demandes', function (Blueprint $table) {
            $table->string('priorite', 20)->default('normale')->after('statut');
            $table->index(['priorite', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropIndex(['priorite', 'statut']);
            $table->dropColumn('priorite');
        });
    }
};
