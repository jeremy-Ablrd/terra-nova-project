<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive : type de la demande (citoyen, institution, alerte).
     * Les demandes existantes (formulaire de contact et import « Citoyen ») prennent « citoyen ».
     */
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->string('type')->default('citoyen')->index()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
