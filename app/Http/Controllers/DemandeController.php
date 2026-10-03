<?php

namespace App\Http\Controllers;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\DemandeEtape;
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

        // Plus récente d'abord ; id en second critère pour un ordre stable entre les pages.
        $demandes = $correspond(Demande::query()->where('user_id', $userId))
            ->with('service')
            ->when($statut, fn ($query) => $query->where('statut', $statut->value))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        // Compteurs : UNE requête groupée, limitée à mes demandes (et à la recherche en cours), indépendante du filtre de statut.
        $parStatut = $correspond(Demande::query()->where('user_id', $userId))->toBase()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $compteurs = collect(Statut::cases())
            ->mapWithKeys(fn (Statut $s) => [$s->value => (int) ($parStatut[$s->value] ?? 0)]);

        return view('demandes.index', [
            'demandes' => $demandes,
            'statut' => $statut,
            'recherche' => $recherche,
            'compteurs' => $compteurs,
            'total' => $compteurs->sum(),
        ]);
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
