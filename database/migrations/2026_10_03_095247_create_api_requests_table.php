<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandes reçues de l'API Nova Terra (sans rapport avec la table `demandes` des habitants).
     * Presque tout est nullable : un champ absent de l'API ne doit pas faire échouer l'upsert.
     */
    public function up(): void
    {
        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();
            $table->unsignedInteger('api_id')->nullable();
            $table->string('requester_name')->nullable();
            $table->string('requester_type')->nullable();
            $table->text('message_public')->nullable();
            $table->tinyInteger('difficulty_level')->nullable();
            $table->string('difficulty')->nullable();
            $table->unsignedInteger('xp_base')->default(0);
            $table->unsignedInteger('xp_time_bonus')->default(0);
            $table->unsignedInteger('xp_total')->default(0);
            $table->unsignedInteger('xp_available')->default(0);
            $table->string('group_name')->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            // Vague à partir de laquelle la demande est visible (0 = dès le lancement).
            $table->unsignedInteger('visible_since_wave')->nullable();
            $table->string('arrival_type')->nullable();
            // Délai depuis le début du concours (ex. 02:00:00 = H+2), pas une heure du jour.
            $table->string('arrival_time')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
};
