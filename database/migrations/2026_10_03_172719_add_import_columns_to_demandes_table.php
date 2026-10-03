<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive pour importer les demandes « Citoyen » de l'API dans `demandes`.
     * Les demandes existantes ne changent pas (nouvelles colonnes à null).
     * - user_id devient nullable (la clé étrangère est conservée) : une demande importée n'a pas de compte.
     * - service_id est déjà nullable ; objet est déjà un varchar(255) (80 caractères + « … » tiennent largement).
     */
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('demandes', function (Blueprint $table) {
            // Code de la demande dans l'API (ex. F21) : clé de dédoublonnage de l'import. Plusieurs NULL autorisés.
            $table->string('request_code')->nullable()->unique()->after('reference');
            // Nom du demandeur tel que donné par l'API (pas de compte utilisateur associé).
            $table->string('demandeur_nom')->nullable()->after('request_code');
        });
    }

    /**
     * user_id reste nullable au retour arrière : le remettre « not null » échouerait (ou supprimerait des
     * données) dès que des demandes importées existent.
     */
    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropUnique(['request_code']);
            $table->dropColumn(['request_code', 'demandeur_nom']);
        });
    }
};
