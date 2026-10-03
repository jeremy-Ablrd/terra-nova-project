<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Date à laquelle la demande a été anonymisée (suppression du compte de l'habitant) ; nulle sinon. */
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dateTime('anonymisee_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->dropColumn('anonymisee_at');
        });
    }
};
