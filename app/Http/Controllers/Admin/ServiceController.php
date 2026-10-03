<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Disponibilite;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Gestion du catalogue : disponibilité, motif, retour estimé, alternative, priorité. Admin seul (ServicePolicy). */
class ServiceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Service::class);

        return view('admin.services.index', ['services' => Service::parPriorite()->get()]);
    }

    public function edit(Service $service): View
    {
        Gate::authorize('update', $service);

        return view('admin.services.edit', [
            'service' => $service,
            'disponibilites' => Disponibilite::cases(),
            'retourSaisie' => $service->retour_estime_at?->format('Y-m-d\TH:i'), // valeur d'un champ datetime-local
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        // L'autorisation est vérifiée par la requête (ServicePolicy) ; la règle métier est dans le modèle.
        $service->mettreAJourDisponibilite($request->validated());

        return redirect()->route('admin.services.index')
            ->with('success', __('Le service « :nom » est mis à jour.', ['nom' => $service->nom]));
    }
}
