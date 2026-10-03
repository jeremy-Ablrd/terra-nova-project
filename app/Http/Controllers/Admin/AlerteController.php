<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Niveau;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlerteRequest;
use App\Models\Alerte;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Gestion des alertes : réservée à l'admin (groupe de routes role:admin). */
class AlerteController extends Controller
{
    public function index(): View
    {
        return view('admin.alertes.index', [
            'alertes' => Alerte::latest('starts_at')->latest('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.alertes.create', ['niveaux' => Niveau::cases()]);
    }

    public function store(StoreAlerteRequest $request): RedirectResponse
    {
        $alerte = new Alerte;
        $alerte->fill($request->validated()); // seuls les champs validés et fillable
        $alerte->user_id = $request->user()->id; // « publiée par » : fixé côté serveur
        $alerte->save();

        return redirect()->route('admin.alertes.index')->with('success', __('L\'alerte « :titre » est publiée.', ['titre' => $alerte->titre]));
    }

    /** « Terminer maintenant » : fin immédiate d'une alerte en cours, ou annulation d'une alerte programmée. */
    public function terminer(Alerte $alerte): RedirectResponse
    {
        if ($alerte->etat() === Alerte::ETAT_TERMINEE) {
            return redirect()->route('admin.alertes.index')->with('success', __('Cette alerte est déjà terminée.'));
        }

        if ($alerte->starts_at->isFuture()) {
            $alerte->starts_at = now(); // programmée : on garde début <= fin
        }
        $alerte->ends_at = now();
        $alerte->save();

        return redirect()->route('admin.alertes.index')->with('success', __('L\'alerte « :titre » est terminée.', ['titre' => $alerte->titre]));
    }
}
