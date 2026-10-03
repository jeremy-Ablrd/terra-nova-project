<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompteController extends Controller
{
    public function index(): View
    {
        return view('admin.comptes.index', [
            'users' => User::orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        // Action sensible : ComptePolicy (administrateur seul), en plus du middleware de rôle de la route.
        Gate::authorize('updateRole', $user);

        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
        ]);
        $role = Role::from($data['role']);

        // Un admin ne peut pas se retirer son propre rôle (sinon plus personne ne gère les comptes).
        if ($user->is($request->user()) && $role !== Role::Admin) {
            return back()->withErrors(['role' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.']);
        }

        // Mise à jour champ par champ : role n'est volontairement pas dans $fillable.
        // Journal d'activité : l'entrée est écrite dans la même transaction, seulement si le rôle change vraiment.
        // Le compte n'est désigné que par son numéro (ni nom ni e-mail dans le journal).
        $ancien = $user->role;
        DB::transaction(function () use ($request, $user, $role, $ancien) {
            $user->role = $role;

            if ($user->isDirty('role')) {
                $user->save();
                Journal::enregistrer($request->user(), ActionJournal::RoleModifie, $user, 'rôle : '.$ancien->label().' → '.$role->label());
            }
        });

        return back()->with('success', 'Le rôle de '.$user->name.' est maintenant « '.$role->label().' ».');
    }
}
