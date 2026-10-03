<?php

namespace App\Http\Controllers;

use App\Models\Demande;
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

        return view('demandes.show', compact('demande'));
    }
}
