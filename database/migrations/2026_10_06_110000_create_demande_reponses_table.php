<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Réponses directes d'un agent à l'habitant (F84) : le texte, la date et l'agent (nom copié, comme demande_etapes).
        // vu_at : l'habitant en a pris connaissance (bouton « Compris » ou ouverture de la demande).
        Schema::create('demande_reponses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agent_nom')->nullable();
            $table->text('texte');
            $table->dateTime('vu_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->index(['demande_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demande_reponses');
    }
};
