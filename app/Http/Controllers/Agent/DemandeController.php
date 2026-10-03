<?php

namespace App\Http\Controllers\Agent;

use App\Enums\Statut;
use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Services\TransitionDemande;
use App\Services\TransitionRefusee;
use Illuminate\Http\RedirectResponse;
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

        // Le Centre technique ne montre que les demandes citoyennes (formulaire de contact et import « Citoyen »).
        // user et service sont chargés d'avance (pas de N+1) ; id en second critère pour un ordre stable entre les pages.
        $demandes = Demande::citoyennes()
            ->with(['user', 'service'])
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Compteurs : UNE seule requête groupée, indépendante du filtre actif.
        $parStatut = Demande::citoyennes()->toBase()
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

    public function show(Demande $demande): View
    {
        Gate::authorize('view', $demande);

        $demande->load(['user', 'service', 'agent', 'etapes']);

        return view('agent.demandes.show', compact('demande'));
    }

    /** F22 étape 3 : passe la demande au statut suivant (le seul possible), via le service de transition. */
    public function updateStatut(Request $request, Demande $demande, TransitionDemande $transition): RedirectResponse
    {
        Gate::authorize('view', $demande);
        Gate::authorize('updateStatus', $demande);

        // Seul le statut affiché est lu ; la cible est toujours le statut suivant, jamais une valeur du formulaire.
        $affiche = is_string($request->input('statut')) ? Statut::tryFrom($request->input('statut')) : null;

        try {
            if ($affiche === null) {
                throw new TransitionRefusee(__('Statut affiché invalide : rechargez la page.'));
            }
            $demande = $transition->passer($demande, $affiche, $request->user());
        } catch (TransitionRefusee $e) {
            return redirect()->route('agent.demandes.show', $demande)->with('erreur', $e->getMessage());
        }

        return redirect()->route('agent.demandes.show', $demande)
            ->with('succes', __('Le statut de la demande :reference est maintenant « :statut ».', [
                'reference' => $demande->reference,
                'statut' => $demande->statut->label(),
            ]));
    }
}
