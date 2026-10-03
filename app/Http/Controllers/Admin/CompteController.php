<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        // Garde minimale sur cette action sensible, en attendant le middleware de rôle (D09).
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
        ]);
        $role = Role::from($data['role']);

        // Un admin ne peut pas se retirer son propre rôle (sinon plus personne ne gère les comptes).
        if ($user->is($request->user()) && $role !== Role::Admin) {
            return back()->withErrors(['role' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.']);
        }

        // Mise à jour champ par champ : role n'est volontairement pas dans $fillable.
        $user->role = $role;
        $user->save();

        return back()->with('success', 'Le rôle de '.$user->name.' est maintenant « '.$role->label().' ».');
    }
}
