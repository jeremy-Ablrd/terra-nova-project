<?php

namespace App\Http\Controllers;

use App\Models\Projet;
use Illuminate\View\View;

/** Projets de la ville (F67) : pages publiques, visibles sans connexion. Un brouillon n'existe pas pour le public (404). */
class ProjetController extends Controller
{
    public function index(): View
    {
        // UNE requête ; l'état de chaque consultation est calculé à l'affichage (ouvertes d'abord, puis à venir, sans consultation, terminées).
        $projets = Projet::publies()->get()
            ->sort(fn (Projet $a, Projet $b) => [$a->etatConsultation()->rang(), $b->publie_at->getTimestamp()] <=> [$b->etatConsultation()->rang(), $a->publie_at->getTimestamp()])
            ->values();

        return view('projets.index', ['projets' => $projets]);
    }

    public function show(Projet $projet): View
    {
        abort_unless($projet->estPublie(), 404);

        return view('projets.show', ['projet' => $projet]);
    }
}
