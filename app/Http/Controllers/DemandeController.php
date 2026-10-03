<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\DemandeEtape;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DemandeController extends Controller
{
    public function index(): View
    {
        $demandes = auth()->user()->demandes()->latest()->get();

        return view('demandes.index', compact('demandes'));
    }

    public function show(Demande $demande): View
    {
        Gate::authorize('view', $demande);

        $demande->load('etapes');

        // F49 : ouvrir sa propre demande vaut accusé de lecture (un agent qui la consulte ne marque rien).
        if (Gate::allows('acquitter', $demande)) {
            $demande->etapes()->whereNull('vu_at')->update(['vu_at' => now()]);
        }

        return view('demandes.show', compact('demande'));
    }

    /** F49 : bouton « Compris » d'un changement d'état. */
    public function acquitter(DemandeEtape $etape): RedirectResponse
    {
        $demande = $etape->demande;
        Gate::authorize('acquitter', $demande);

        if ($etape->vu_at === null) {
            $etape->forceFill(['vu_at' => now()])->save();
        }

        return back();
    }
}
