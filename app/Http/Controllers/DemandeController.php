<?php

namespace App\Http\Controllers;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\DemandeEtape;
use App\Models\DemandeReponse;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DemandeController extends Controller
{
    private const PAR_PAGE = 10;

    private const RECHERCHE_MAX = 100;

    /** F26 : historique de MES demandes (user_id strict : les demandes importées, user_id nul, n'y sont jamais). */
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        // Filtre ?statut=… : valeur inconnue (ou tableau) ignorée. Recherche ?q=… : texte, 100 caractères au plus.
        $valeur = $request->query('statut');
        $statut = is_string($valeur) ? Statut::tryFrom($valeur) : null;
        $q = $request->query('q');
        $recherche = is_string($q) ? mb_substr(trim($q), 0, self::RECHERCHE_MAX) : '';

        $correspond = fn (Builder $query) => $query->when($recherche !== '', function (Builder $query) use ($recherche) {
            // % et _ sont des caractères littéraux : échappés avec « ! » (ESCAPE valable sur MySQL comme sur SQLite).
            $motif = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $recherche).'%';

            $query->where(fn (Builder $q) => $q
                ->whereRaw("reference like ? escape '!'", [$motif])
                ->orWhereRaw("objet like ? escape '!'", [$motif]));
        });

        // F79 : services (sujets) de MES demandes, avec leur nombre (deux requêtes). La liste des services ne dépend ni du statut ni de
        // la recherche (un service choisi reste proposé) ; les nombres, eux, sont ceux qu'on obtient en le choisissant avec le statut et
        // la recherche en cours. Valeur inconnue ignorée ; « aucun » = demandes sans service.
        $nombres = $correspond(Demande::query()->where('user_id', $userId))->toBase()
            ->selectRaw('service_id, count(*) as total, sum(case when statut = ? then 1 else 0 end) as dans_statut', [$statut?->value ?? ''])
            ->groupBy('service_id')
            ->get()
            ->keyBy(fn ($ligne) => $ligne->service_id === null ? 'aucun' : (string) $ligne->service_id);
        $optionsService = Demande::query()->where('user_id', $userId)->toBase()
            ->leftJoin('services', 'services.id', '=', 'demandes.service_id')
            ->selectRaw('demandes.service_id as service_id, services.nom as nom')
            ->groupBy('demandes.service_id', 'services.nom')
            ->get()
            ->map(function ($ligne) use ($nombres, $statut) {
                $valeur = $ligne->service_id === null ? 'aucun' : (string) $ligne->service_id;
                $compte = $nombres->get($valeur);

                return (object) [
                    'valeur' => $valeur,
                    'nom' => $ligne->nom ?? __('À orienter'),
                    'total' => (int) ($compte === null ? 0 : ($statut ? $compte->dans_statut : $compte->total)),
                ];
            })
            ->sortBy(fn ($o) => [$o->valeur === 'aucun' ? 1 : 0, mb_strtolower($o->nom)])
            ->values();
        $valeurService = $request->query('service');
        $service = is_string($valeurService) && $optionsService->contains('valeur', $valeurService) ? $valeurService : null;
        $filtreService = fn (Builder $query) => $query->when($service !== null, fn (Builder $q) => $service === 'aucun' ? $q->whereNull('service_id') : $q->where('service_id', (int) $service));

        // Plus récente d'abord ; id en second critère pour un ordre stable entre les pages.
        $demandes = $filtreService($correspond(Demande::query()->where('user_id', $userId)))
            ->with('service')
            ->when($statut, fn ($query) => $query->where('statut', $statut->value))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        // Compteurs : UNE requête groupée, limitée à mes demandes (et à la recherche en cours), indépendante du filtre de statut.
        $parStatut = $filtreService($correspond(Demande::query()->where('user_id', $userId)))->toBase()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $compteurs = collect(Statut::cases())
            ->mapWithKeys(fn (Statut $s) => [$s->value => (int) ($parStatut[$s->value] ?? 0)]);

        return view('demandes.index', [
            'demandes' => $demandes,
            'statut' => $statut,
            'recherche' => $recherche,
            'optionsService' => $optionsService,
            'service' => $service,
            'compteurs' => $compteurs,
            'total' => $compteurs->sum(),
        ]);
    }

    public function show(Demande $demande): View
    {
        Gate::authorize('view', $demande);

        $demande->load(['etapes', 'reponses']);

        // F49 : ouvrir sa propre demande vaut accusé de lecture (un agent qui la consulte ne marque rien).
        if (Gate::allows('acquitter', $demande)) {
            $demande->etapes()->whereNull('vu_at')->update(['vu_at' => now()]);
            $demande->reponses()->whereNull('vu_at')->update(['vu_at' => now()]);
        }

        return view('demandes.show', compact('demande'));
    }

    /** F84 : bouton « Compris » d'une réponse de la mairie (habitant propriétaire seulement). */
    public function acquitterReponse(DemandeReponse $reponse): RedirectResponse
    {
        Gate::authorize('acquitter', $reponse->demande);

        if ($reponse->vu_at === null) {
            $reponse->forceFill(['vu_at' => now()])->save();
        }

        return back();
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
