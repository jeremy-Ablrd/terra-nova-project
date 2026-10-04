<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contributions des habitants (F65, F66, F68, F76) : avis sur un projet, idée, commentaire sur un service.
        // Jamais supprimées : à la suppression du compte, user_id devient nul et le texte est remplacé (anonymisee_at).
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();   // PA-2026-00001, générée par l'événement `created`
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->foreignId('projet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('titre', 120)->nullable();   // idées seulement
            $table->text('message');
            $table->string('statut', 20)->default('recue');
            $table->text('reponse')->nullable();         // réponse de la ville
            $table->dateTime('reponse_at')->nullable();
            $table->dateTime('anonymisee_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'statut']);
            $table->index(['user_id', 'created_at']);
        });

        // Étapes de la frise : le statut atteint et quand. Aucun nom d'acteur (« la ville »). vu_at : l'habitant en a pris connaissance.
        Schema::create('contribution_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribution_id')->constrained()->cascadeOnDelete();
            $table->string('statut', 20);
            $table->dateTime('vu_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->index(['contribution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_etapes');
        Schema::dropIfExists('contributions');
    }
};
