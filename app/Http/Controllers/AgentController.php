<?php

namespace App\Http\Controllers;

use App\Enums\Disponibilite;
use App\Enums\Priorite;
use App\Enums\Role;
use App\Enums\Statut;
use App\Models\Alerte;
use App\Models\Demande;
use App\Models\JournalActivite;
use App\Models\Service;
use App\Models\User;
use App\Services\NovaTerraApi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Tableau de bord agent (F50) : il lit les données, il n'en modifie aucune.
 * Nombre de requêtes borné : 1 agrégat sur les demandes, 3 comptages, 5 entrées de journal au plus.
 */
class AgentController extends Controller
{
    public function index(): View
    {
        // Une seule requête groupée sur les demandes citoyennes : statuts, créées sur 7 jours, plus ancienne « nouvelle ».
        $agregat = Demande::citoyennes()->toBase()
            ->selectRaw('sum(case when statut = ? then 1 else 0 end) as nouvelles', [Statut::Nouvelle->value])
            ->selectRaw('sum(case when statut = ? then 1 else 0 end) as en_cours', [Statut::EnCours->value])
            ->selectRaw('sum(case when statut = ? then 1 else 0 end) as traitees', [Statut::Traitee->value])
            ->selectRaw('sum(case when priorite = ? and statut != ? then 1 else 0 end) as urgences_medicales', [Priorite::UrgenceMedicale->value, Statut::Traitee->value])
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as recentes', [now()->subDays(7)->toDateTimeString()])
            ->selectRaw('min(case when statut = ? then created_at end) as plus_ancienne', [Statut::Nouvelle->value])
            ->first();

        $plusAncienne = $agregat->plus_ancienne ? Carbon::parse($agregat->plus_ancienne) : null;

        // Services interrompus et désactivés : UNE requête groupée.
        $servicesParEtat = Service::actifs()
            ->whereIn('disponibilite', [Disponibilite::Interrompu->value, Disponibilite::Desactive->value])
            ->toBase()->selectRaw('disponibilite, count(*) as total')->groupBy('disponibilite')->pluck('total', 'disponibilite');

        $lastSync = Cache::get(NovaTerraApi::CACHE_LAST_SYNC);
        $session = Cache::get(NovaTerraApi::CACHE_SESSION);

        return view('agent.index', [
            'nouvelles' => (int) $agregat->nouvelles,
            'enCours' => (int) $agregat->en_cours,
            'traitees' => (int) $agregat->traitees,
            'recentes' => (int) $agregat->recentes,
            'urgencesMedicales' => (int) $agregat->urgences_medicales,
            'ancienneteJours' => $plusAncienne ? (int) floor($plusAncienne->diffInDays(now(), true)) : null,
            'alertesActives' => Alerte::active()->count(),
            'servicesInterrompus' => (int) ($servicesParEtat[Disponibilite::Interrompu->value] ?? 0),
            'servicesDesactives' => (int) ($servicesParEtat[Disponibilite::Desactive->value] ?? 0),
            'comptesCitoyens' => User::where('role', Role::Citoyen->value)->count(),
            'entrees' => JournalActivite::query()->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
            // Valeurs du cache à la dernière synchro : affichées telles quelles, jamais recalculées.
            'sync' => [
                'at' => $lastSync ? Carbon::parse($lastSync) : null,
                'erreur' => Cache::get(NovaTerraApi::CACHE_LAST_ERROR),
                'session' => is_array($session) ? $session : null,
            ],
        ]);
    }
}
