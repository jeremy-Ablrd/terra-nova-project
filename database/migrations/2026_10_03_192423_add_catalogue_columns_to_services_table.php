<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive : catégorie, mise en avant et disponibilité des services (D05, F28, F32, F38).
     * `description` existe déjà. Les services existants deviennent « autre », non prioritaires, disponibles.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('categorie')->default('autre')->after('icone');
            $table->boolean('prioritaire')->default(false)->after('categorie');
            $table->string('disponibilite')->default('disponible')->after('prioritaire');
            $table->text('motif_interruption')->nullable()->after('disponibilite');
            $table->timestamp('retour_estime_at')->nullable()->after('motif_interruption');
            $table->text('alternative')->nullable()->after('retour_estime_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['categorie', 'prioritaire', 'disponibilite', 'motif_interruption', 'retour_estime_at', 'alternative']);
        });
    }
};
