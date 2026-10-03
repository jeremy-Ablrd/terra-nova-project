<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Appareils (navigateurs) déjà utilisés pour se connecter à un compte (F54). Le jeton du cookie n'est jamais stocké : seulement son SHA-256. */
    public function up(): void
    {
        Schema::create('appareils_connus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jeton_hash', 64);
            $table->string('libelle');
            $table->dateTime('premiere_vue_at');
            $table->dateTime('derniere_vue_at');
            $table->unique(['user_id', 'jeton_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appareils_connus');
    }
};
