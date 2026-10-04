<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutContribution;
use App\Enums\TypeContribution;
use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Services\TransitionContribution;
use App\Services\TransitionRefusee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Traitement des contributions des habitants (F65, F66, F68, F76) : admin seul (role:admin du groupe + ContributionPolicy).
 * L'auteur n'est pas montré : la ville répond à une contribution, pas à une personne. Les nombres affichés sont des nombres
 * de travail (à examiner, à répondre), jamais un résultat ni un décompte d'opinions.
 */
class ParticipationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Contribution::class);

        // Filtres ?type= et ?statut= : une valeur inconnue (ou un tableau) est ignorée.
        $valeurType = $request->query('type');
        $type = is_string($valeurType) ? TypeContribution::tryFrom($valeurType) : null;
        $valeurStatut = $request->query('statut');
        $statut = is_string($valeurStatut) ? StatutContribution::tryFrom($valeurStatut) : null;

        $contributions = Contribution::query()
            ->with(['projet', 'service'])
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->when($statut, fn ($q) => $q->where('statut', $statut->value))
            ->latest()->latest('id')
            ->paginate(20)->withQueryString();

        // Une requête groupée pour les compteurs de travail.
        $parStatut = Contribution::toBase()->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        return view('admin.participation.index', [
            'contributions' => $contributions,
            'type' => $type,
            'statut' => $statut,
            'parStatut' => $parStatut,
        ]);
    }

    public function show(Contribution $contribution): View
    {
        Gate::authorize('administrer', $contribution);

        $contribution->load(['projet', 'service', 'etapes']);

        return view('admin.participation.show', ['contribution' => $contribution]);
    }

    /** Seul le statut suivant est possible, via TransitionContribution (étape, réponse et journal dans la même transaction). */
    public function statut(Request $request, Contribution $contribution, TransitionContribution $transition): RedirectResponse
    {
        Gate::authorize('administrer', $contribution);

        $donnees = $request->validate([
            'statut_affiche' => ['required', Rule::enum(StatutContribution::class)],
            'reponse' => ['nullable', 'string', 'max:1000'],
        ], [], ['reponse' => __('réponse de la ville')]);

        try {
            $transition->passer($contribution, StatutContribution::from($donnees['statut_affiche']), $request->user(), $donnees['reponse'] ?? null);
        } catch (TransitionRefusee $e) {
            return redirect()->route('admin.participation.show', $contribution)->withInput()->withErrors(['transition' => $e->getMessage()]);
        }

        return redirect()->route('admin.participation.show', $contribution)
            ->with('success', __('La contribution :reference est maintenant « :statut ».', ['reference' => $contribution->reference, 'statut' => $contribution->fresh()->statut->label()]));
    }
}
