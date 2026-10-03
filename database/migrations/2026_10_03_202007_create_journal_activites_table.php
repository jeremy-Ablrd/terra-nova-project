<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal d'activité des agents et des admins : qui, quoi, quand, sur quel objet. Lecture seule (aucune mise à jour,
     * aucune suppression côté application) : pas de updated_at. L'acteur est aussi copié (nom, rôle) pour que la trace
     * reste lisible si son compte est supprimé (acteur_id passe alors à null).
     */
    public function up(): void
    {
        Schema::create('journal_activites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('acteur_id')->nullable();
            $table->string('acteur_nom');
            $table->string('acteur_role');
            $table->string('action');
            $table->string('objet_type');
            $table->unsignedBigInteger('objet_id')->nullable();
            // Libellé public de l'objet (référence de demande, nom de service, titre d'alerte) : jamais de donnée personnelle.
            $table->string('objet_libelle');
            $table->string('detail')->nullable();
            $table->timestamp('created_at');

            $table->index('created_at');
            $table->index('acteur_id');
            $table->index('action');
            $table->foreign('acteur_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_activites');
    }
};
