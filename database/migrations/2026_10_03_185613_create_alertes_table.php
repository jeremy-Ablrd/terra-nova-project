<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Alertes publiées par l'admin et affichées à tous les visiteurs pendant leur fenêtre [starts_at, ends_at). */
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->text('ce_qui_se_passe');
            $table->text('ce_quil_faut_faire');
            $table->string('secteur')->nullable();
            $table->string('niveau')->default('info');
            $table->text('consignes_vulnerables')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            // Publiée par : l'admin ; conservée (null) si son compte est supprimé.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};
