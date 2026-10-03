<?php

namespace App\Http\Controllers\Agent;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Journal d'activité : qui a fait quoi, quand, sur quel objet. Lecture seule, réservée à l'agent (JournalActivitePolicy). */
class JournalController extends Controller
{
    private const PAR_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', JournalActivite::class);

        // Filtres ?action=… et ?role=… : une valeur inconnue (ou un tableau) est ignorée → journal complet.
        $valeurAction = $request->query('action');
        $action = is_string($valeurAction) ? ActionJournal::tryFrom($valeurAction) : null;

        $valeurRole = $request->query('role');
        $role = is_string($valeurRole) ? Role::tryFrom($valeurRole) : null;
        $roles = [Role::Agent, Role::Admin]; // seuls les agents et les admins agissent (donc apparaissent)
        $role = in_array($role, $roles, true) ? $role : null;

        // Plus récent d'abord ; id en second critère pour un ordre stable entre les pages. Aucune relation à charger :
        // l'acteur est copié dans l'entrée (nom, rôle), l'objet par son libellé.
        $entrees = JournalActivite::query()
            ->when($action, fn ($query) => $query->where('action', $action->value))
            ->when($role, fn ($query) => $query->where('acteur_role', $role->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        // Compteurs : UNE seule requête groupée (action × rôle). Chaque filtre est compté en tenant compte de l'AUTRE
        // filtre mais pas du sien : les compteurs d'action ne dépendent pas de l'action active.
        $lignes = JournalActivite::query()->toBase()
            ->selectRaw('action, acteur_role, count(*) as total')
            ->groupBy('action', 'acteur_role')
            ->get();

        $compteursAction = collect(ActionJournal::cases())->mapWithKeys(fn (ActionJournal $a) => [
            $a->value => (int) $lignes
                ->filter(fn ($l) => $l->action === $a->value && ($role === null || $l->acteur_role === $role->value))
                ->sum('total'),
        ]);

        $compteursRole = collect($roles)->mapWithKeys(fn (Role $r) => [
            $r->value => (int) $lignes
                ->filter(fn ($l) => $l->acteur_role === $r->value && ($action === null || $l->action === $action->value))
                ->sum('total'),
        ]);

        return view('agent.journal.index', [
            'entrees' => $entrees,
            'action' => $action,
            'role' => $role,
            'roles' => $roles,
            'compteursAction' => $compteursAction,
            'totalAction' => $compteursAction->sum(),
            'compteursRole' => $compteursRole,
            'totalRole' => $compteursRole->sum(),
        ]);
    }
}
