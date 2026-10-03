<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDemandeRequest;
use App\Models\Demande;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact.create', [
            'services' => Service::where('actif', true)->orderBy('ordre')->get(),
        ]);
    }

    public function store(StoreDemandeRequest $request): RedirectResponse
    {
        // user_id vient de la session et le statut initial (« nouvelle ») est fixé côté serveur ;
        // l'événement `created` du modèle génère la référence.
        $demande = $request->user()->demandes()->create($request->validated());

        return redirect()->route('contact.confirmation', $demande);
    }

    public function confirmation(Demande $demande): View
    {
        Gate::authorize('view', $demande);

        return view('contact.confirmation', compact('demande'));
    }
}
