<?php

namespace App\Http\Controllers\Agent;

use App\Enums\Statut;
use App\Http\Controllers\Controller;
use App\Models\Demande;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DemandeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Demande::class);

        // Filtre ?statut=… : une valeur inconnue (ou un tableau) est ignorée → liste complète, jamais d'erreur.
        $valeur = $request->query('statut');
        $statut = is_string($valeur) ? Statut::tryFrom($valeur) : null;

        // user et service sont chargés d'avance (pas de N+1) ; id en second critère pour un ordre stable entre les pages.
        $demandes = Demande::with(['user', 'service'])
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Compteurs : UNE requête groupée, indépendante du filtre actif.
        $parStatut = Demande::query()->toBase()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $compteurs = collect(Statut::cases())
            ->mapWithKeys(fn (Statut $s) => [$s->value => (int) ($parStatut[$s->value] ?? 0)]);

        return view('agent.demandes.index', [
            'demandes' => $demandes,
            'statut' => $statut,
            'compteurs' => $compteurs,
            'total' => $compteurs->sum(),
        ]);
    }
}
