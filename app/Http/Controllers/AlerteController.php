<?php

namespace App\Http\Controllers;

use App\Models\Alerte;
use App\Services\AlertesActives;
use Illuminate\View\View;

/** Pages publiques des alertes (visibles sans connexion). */
class AlerteController extends Controller
{
    public function index(AlertesActives $actives): View
    {
        return view('alertes.index', ['alertes' => $actives->get()]);
    }

    public function show(Alerte $alerte): View
    {
        // Une alerte programmée n'est pas encore publique ; une alerte terminée reste lisible, avec une mention.
        abort_if($alerte->etat() === Alerte::ETAT_PROGRAMMEE, 404);

        return view('alertes.show', [
            'alerte' => $alerte,
            'terminee' => $alerte->etat() === Alerte::ETAT_TERMINEE,
        ]);
    }
}
