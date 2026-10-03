<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Date à laquelle l'agent a marqué les données API comme vues (nulle : jamais) ; sert au badge « Nouvelle ». */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('donnees_api_vues_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('donnees_api_vues_at');
        });
    }
};
