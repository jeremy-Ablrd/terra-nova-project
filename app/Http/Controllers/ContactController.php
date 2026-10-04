<?php

namespace App\Http\Controllers;

use App\Enums\Priorite;
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
            'numeros' => $this->numerosDeSecours(),
        ]);
    }

    public function store(StoreDemandeRequest $request): RedirectResponse
    {
        // user_id vient de la session et le statut initial (« nouvelle ») est fixé côté serveur ;
        // l'événement `created` du modèle génère la référence.
        // La priorité est fixée côté serveur : « urgence médicale » seulement si l'habitant a coché la case, sinon normale.
        $demande = $request->user()->demandes()->make($request->safe()->except('urgence_medicale'));
        $demande->priorite = $request->boolean('urgence_medicale') ? Priorite::UrgenceMedicale : Priorite::Normale;
        $demande->save();

        return redirect()->route('contact.confirmation', $demande);
    }

    public function confirmation(Demande $demande): View
    {
        Gate::authorize('view', $demande);

        return view('contact.confirmation', [
            'demande' => $demande,
            // La consigne d'urgence est répétée (avec les numéros) quand l'habitant a signalé une urgence médicale.
            'numeros' => $demande->priorite === Priorite::UrgenceMedicale ? $this->numerosDeSecours() : null,
        ]);
    }

    /** Numéros des services d'urgence et de santé (les mêmes que la page /urgences) : une requête. */
    private function numerosDeSecours()
    {
        return Service::actifs()->urgences()->get()->filter(fn (Service $s) => $s->telephone && $s->telephoneHref())->values();
    }
}
