<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\ContributionEtape;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** « Mes contributions » : l'habitant retrouve ses avis, idées et commentaires et leur suivi. Citoyen seulement (role:citoyen), user_id strict. */
class MesContributionsController extends Controller
{
    public function index(Request $request): View
    {
        return view('mes-contributions.index', [
            'contributions' => Contribution::where('user_id', $request->user()->id)
                ->with(['projet', 'service'])
                ->latest()->latest('id')
                ->paginate(10),
        ]);
    }

    public function show(Contribution $contribution): View
    {
        Gate::authorize('view', $contribution);

        // Ouvrir sa contribution vaut accusé de lecture des changements d'état (comme pour les demandes).
        ContributionEtape::where('contribution_id', $contribution->id)->whereNull('vu_at')->update(['vu_at' => now()]);

        $contribution->load(['projet', 'service', 'etapes']);

        return view('mes-contributions.show', ['contribution' => $contribution]);
    }
}
