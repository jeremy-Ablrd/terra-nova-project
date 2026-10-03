<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive (F63) : l'état « désactivé » est une nouvelle valeur de la colonne `disponibilite` (texte, aucun
     * changement de schéma) ; seule la date de désactivation est ajoutée. Aucune donnée n'est modifiée ni supprimée.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dateTime('desactive_at')->nullable()->after('alternative');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('desactive_at');
        });
    }
};
