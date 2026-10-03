<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive (F74) : horaires d'ouverture structurés (7 jours, jusqu'à 2 plages par jour, au format JSON)
     * et organisme (par exemple « Association partenaire »). Le texte `horaires` existant est conservé tel quel et sert
     * de repli tant qu'aucun horaire structuré n'est saisi. Aucune donnée n'est modifiée ni supprimée.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('horaires_semaine')->nullable()->after('horaires');
            $table->string('organisme', 100)->nullable()->after('nom');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['horaires_semaine', 'organisme']);
        });
    }
};
