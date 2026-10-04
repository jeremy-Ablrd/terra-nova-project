<?php

namespace App\Http\Controllers\Agent;

use App\Enums\Priorite;
use App\Enums\Statut;
use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Services\PrioriteDemande;
use App\Services\RepondreDemande;
use App\Services\TransitionDemande;
use App\Services\TransitionRefusee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DemandeController extends Controller
{
    /** Filtres de priorité de la liste : « prioritaires » (urgences médicales comprises) et « urgence_medicale ». */
    public const FILTRES_PRIORITE = ['prioritaires', 'urgence_medicale'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Demande::class);

        // Filtre ?statut=… : une valeur inconnue (ou un tableau) est ignorée → liste complète, jamais d'erreur.
        $valeur = $request->query('statut');
        $statut = is_string($valeur) ? Statut::tryFrom($valeur) : null;
        $valeurPriorite = $request->query('priorite');
        $priorite = is_string($valeurPriorite) && in_array($valeurPriorite, self::FILTRES_PRIORITE, true) ? $valeurPriorite : null;

        // Le Centre technique ne montre que les demandes citoyennes (formulaire de contact et import « Citoyen »).
        // user et service sont chargés d'avance (pas de N+1). Tri : urgences médicales, puis prioritaires, puis les plus récentes ;
        // id en dernier critère pour un ordre stable entre les pages.
        $demandes = Demande::citoyennes()
            ->with(['user', 'service'])
            ->when($statut, fn ($query) => $query->where('statut', $statut))
            ->when($priorite === 'prioritaires', fn ($query) => $query->where('priorite', '!=', Priorite::Normale->value))
            ->when($priorite === 'urgence_medicale', fn ($query) => $query->where('priorite', Priorite::UrgenceMedicale->value))
            ->parPriorite()
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Compteurs : UNE seule requête groupée (statut et priorité), indépendante des filtres actifs.
        $lignes = Demande::citoyennes()->toBase()
            ->selectRaw('statut, priorite, count(*) as total')
            ->groupBy('statut', 'priorite')
            ->get();

        $compteurs = collect(Statut::cases())
            ->mapWithKeys(fn (Statut $s) => [$s->value => (int) $lignes->where('statut', $s->value)->sum('total')]);
        $compteursPriorite = [
            'prioritaires' => (int) $lignes->where('priorite', '!=', Priorite::Normale->value)->sum('total'),
            'urgence_medicale' => (int) $lignes->where('priorite', Priorite::UrgenceMedicale->value)->sum('total'),
        ];

        return view('agent.demandes.index', [
            'demandes' => $demandes,
            'statut' => $statut,
            'priorite' => $priorite,
            'compteurs' => $compteurs,
            'compteursPriorite' => $compteursPriorite,
            'total' => $compteurs->sum(),
        ]);
    }

    public function show(Demande $demande): View
    {
        Gate::authorize('view', $demande);

        $demande->load(['user', 'service', 'agent', 'etapes', 'reponses']);

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

    /** F80 : l'agent fixe la priorité (boutons POST, sans JavaScript), via PrioriteDemande (journal dans la même transaction). */
    public function priorite(Request $request, Demande $demande, PrioriteDemande $service): RedirectResponse
    {
        Gate::authorize('changerPriorite', $demande);

        $cible = is_string($request->input('priorite')) ? Priorite::tryFrom($request->input('priorite')) : null;
        $affichee = is_string($request->input('priorite_affichee')) ? Priorite::tryFrom($request->input('priorite_affichee')) : null;

        try {
            if ($cible === null || $affichee === null) {
                throw new TransitionRefusee(__('Priorité invalide : rechargez la page.'));
            }
            $demande = $service->definir($demande, $affichee, $cible, $request->user());
        } catch (TransitionRefusee $e) {
            return redirect()->route('agent.demandes.show', $demande)->with('erreur', $e->getMessage());
        }

        return redirect()->route('agent.demandes.show', $demande)
            ->with('succes', __('La priorité de la demande :reference est maintenant « :priorite ».', ['reference' => $demande->reference, 'priorite' => $demande->priorite->label()]));
    }

    /** F84 : réponse directe à l'habitant, via RepondreDemande (réponse et journal dans la même transaction). */
    public function repondre(Request $request, Demande $demande, RepondreDemande $service): RedirectResponse
    {
        Gate::authorize('repondre', $demande);

        $donnees = $request->validate(['reponse' => ['required', 'string', 'min:5', 'max:2000']], [], ['reponse' => __('réponse')]);

        try {
            $service->envoyer($demande, $request->user(), $donnees['reponse']);
        } catch (TransitionRefusee $e) {
            return redirect()->route('agent.demandes.show', $demande)->with('erreur', $e->getMessage());
        }

        return redirect()->route('agent.demandes.show', $demande)->with('succes', __('Votre réponse est envoyée : l\'habitant en sera averti sur son espace.'));
    }
}
