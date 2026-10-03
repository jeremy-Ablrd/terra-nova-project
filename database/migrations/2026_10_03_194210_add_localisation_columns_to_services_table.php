<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive : où trouver le service et comment le joindre (F46). Les services existants gardent
     * ces champs à null et `urgence` à false : rien ne change pour eux.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('adresse')->nullable()->after('lieu');
            $table->string('quartier', 100)->nullable()->after('adresse');
            $table->string('repere', 150)->nullable()->after('quartier');
            $table->string('telephone', 30)->nullable()->after('repere');
            $table->boolean('urgence')->default(false)->index()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['urgence']);
            $table->dropColumn(['adresse', 'quartier', 'repere', 'telephone', 'urgence']);
        });
    }
};
