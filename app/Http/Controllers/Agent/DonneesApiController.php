<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\ApiRequest;
use App\Services\NovaTerraApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Données de l'API Terra Nova pour les agents (D19) : lecture seule, depuis la base (jamais l'API directement). */
class DonneesApiController extends Controller
{
    private const PAR_PAGE = 25;

    /** Colonnes de tri autorisées (liste blanche : jamais une valeur de l'URL dans la requête SQL). */
    public const TRIS = ['sort_order', 'xp_total', 'difficulty_level', 'visible_since_wave', 'first_seen_at'];

    public function index(Request $request): View
    {
        // Valeurs inconnues (ou tableaux) ignorées : tri par défaut, jamais d'erreur.
        $valeur = $request->query('tri');
        $tri = is_string($valeur) && in_array($valeur, self::TRIS, true) ? $valeur : 'sort_order';
        $sens = $request->query('sens') === 'desc' ? 'desc' : 'asc';

        // Compteurs : une requête groupée par filtre, indépendante des filtres actifs.
        $parType = ApiRequest::query()->toBase()->selectRaw('requester_type, count(*) as total')
            ->groupBy('requester_type')->orderBy('requester_type')->pluck('total', 'requester_type');
        $parVague = ApiRequest::query()->toBase()->selectRaw('visible_since_wave, count(*) as total')
            ->groupBy('visible_since_wave')->orderBy('visible_since_wave')->pluck('total', 'visible_since_wave');

        // Un filtre n'est actif que s'il correspond à une valeur existante.
        $valeurType = $request->query('type');
        $type = is_string($valeurType) && $parType->has($valeurType) ? $valeurType : null;
        $valeurVague = $request->query('vague');
        $vague = is_string($valeurVague) && ctype_digit($valeurVague) && $parVague->has((int) $valeurVague) ? (int) $valeurVague : null;

        $lignes = ApiRequest::query()
            ->when($type !== null, fn ($q) => $q->where('requester_type', $type))
            ->when($vague !== null, fn ($q) => $q->where('visible_since_wave', $vague))
            ->orderBy($tri, $sens)
            ->orderBy('id')
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        $lastSync = Cache::get(NovaTerraApi::CACHE_LAST_SYNC);

        return view('agent.donnees-api.index', [
            'lignes' => $lignes,
            'tri' => $tri,
            'sens' => $sens,
            'type' => $type,
            'vague' => $vague,
            'parType' => $parType,
            'parVague' => $parVague,
            'total' => $parType->sum(),
            'vuesAt' => $request->user()->donnees_api_vues_at,
            'lastSync' => $lastSync ? Carbon::parse($lastSync) : null,
            'erreur' => Cache::get(NovaTerraApi::CACHE_LAST_ERROR),
        ]);
    }

    /** Préférence personnelle de l'agent (pas une modification de la plateforme) : pas d'entrée de journal. */
    public function marquerVues(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['donnees_api_vues_at' => now()])->save();

        return back()->with('succes', __('Les données actuelles sont marquées comme vues.'));
    }
}
