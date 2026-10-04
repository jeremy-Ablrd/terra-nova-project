<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Projets de la ville (F67), éventuellement soumis à l'avis des habitants (F65, F66).
        // publie_at vide = brouillon (invisible du public). Les dates de consultation sont saisies en heure locale.
        Schema::create('projets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('titre', 150);
            $table->string('resume', 255);
            $table->text('description');
            $table->dateTime('publie_at')->nullable();
            $table->dateTime('consultation_debut_at')->nullable();
            $table->dateTime('consultation_fin_at')->nullable();
            $table->text('bilan')->nullable();   // ce que la ville retient de la consultation
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projets');
    }
};
