<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal de sécurité : jamais de mot de passe, de contenu de message ni de donnée d'habitant (e-mail masqué seulement).
     * appareil_id et vu_at servent à l'alerte « nouvel appareil » (F54) ; supprimé après 30 jours (novaterra:purger-securite).
     */
    public function up(): void
    {
        Schema::create('evenements_securite', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email_masque')->nullable();
            $table->string('ip', 45);
            $table->string('route')->nullable();
            $table->string('detail')->nullable();
            $table->unsignedBigInteger('appareil_id')->nullable();
            $table->dateTime('vu_at')->nullable();
            $table->dateTime('created_at');
            $table->index('created_at');
            $table->index('type');
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements_securite');
    }
};
