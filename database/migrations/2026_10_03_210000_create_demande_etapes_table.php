<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demande_etapes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained()->cascadeOnDelete();
            $table->string('statut');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agent_nom')->nullable();   // copie du nom : reste lisible si le compte est supprimé
            $table->dateTime('vu_at')->nullable();      // F49 : l'habitant a pris connaissance du changement
            $table->dateTime('created_at')->nullable();
            $table->index(['demande_id', 'created_at']);
        });

        // Historique des demandes existantes : étapes cohérentes avec leur statut (dates approximatives).
        // Les dates sont recopiées telles quelles (heure locale). vu_at rempli : aucune notification pour l'existant.
        $maintenant = now()->toDateTimeString();
        $agents = DB::table('users')->pluck('name', 'id');

        foreach (DB::table('demandes')->orderBy('id')->get() as $d) {
            $etapes = [['nouvelle', $d->created_at, null]];

            if ($d->statut === 'en_cours') {
                $etapes[] = ['en_cours', $d->updated_at ?? $d->created_at, $d->agent_id];
            } elseif ($d->statut === 'traitee') {
                $fin = $d->traitee_at ?? $d->updated_at ?? $d->created_at;
                $etapes[] = ['en_cours', $d->created_at, $d->agent_id];
                $etapes[] = ['traitee', $fin, $d->agent_id];
            }

            foreach ($etapes as [$statut, $date, $agentId]) {
                DB::table('demande_etapes')->insert([
                    'demande_id' => $d->id,
                    'statut' => $statut,
                    'agent_id' => $agentId,
                    'agent_nom' => $agentId ? ($agents[$agentId] ?? null) : null,
                    'vu_at' => $maintenant,
                    'created_at' => $date,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demande_etapes');
    }
};
