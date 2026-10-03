<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionJournal;
use App\Enums\Disponibilite;
use App\Http\Controllers\Controller;
use App\Http\Requests\DesactiverServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Services\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Journal d'activité : l'entrée est écrite dans la même transaction, seulement si quelque chose a changé.
        DB::transaction(function () use ($request, $service) {
            $changements = $service->mettreAJour($request->validated());

            if ($changements !== []) {
                Journal::enregistrer($request->user(), ActionJournal::ServiceModifie, $service, Journal::detailService($changements));
            }
        });

        return redirect()->route('admin.services.index')
            ->with('success', __('Le service « :nom » est mis à jour.', ['nom' => $service->nom]));
    }

    /** F63 : coupure d'urgence en un clic depuis la liste. Même transaction que la trace dans le journal. */
    public function desactiver(DesactiverServiceRequest $request, Service $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service) {
            $changements = $service->desactiver($request->validated('motif_interruption'), $request->validated('alternative'));

            if ($changements !== []) {
                Journal::enregistrer($request->user(), ActionJournal::ServiceModifie, $service, Journal::detailService($changements));
            }
        });

        return redirect()->route('admin.services.index')
            ->with('success', __('Le service « :nom » est désactivé : plus aucune demande ne peut le choisir.', ['nom' => $service->nom]));
    }

    /** F63 : remise en service, un clic. Motif, retour estimé et alternative sont effacés. */
    public function reactiver(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize('update', $service);

        DB::transaction(function () use ($request, $service) {
            $changements = $service->reactiver();

            if ($changements !== []) {
                Journal::enregistrer($request->user(), ActionJournal::ServiceModifie, $service, Journal::detailService($changements));
            }
        });

        return redirect()->route('admin.services.index')
            ->with('success', __('Le service « :nom » est de nouveau disponible.', ['nom' => $service->nom]));
    }
}
