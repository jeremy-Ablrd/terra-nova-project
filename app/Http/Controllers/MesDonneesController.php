<?php

namespace App\Http\Controllers;

use App\Services\DonneesPersonnelles;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * F55 : mes informations personnelles, remises sous forme d'un document lisible et imprimable, « Mon dossier » (page et
 * fichier .html autonome). Aucune donnée brute n'est proposée en téléchargement. Citoyen seulement (role:citoyen).
 */
class MesDonneesController extends Controller
{
    public function index(): View
    {
        return view('mes-donnees.index');
    }

    public function dossier(Request $request, DonneesPersonnelles $donnees): View
    {
        return view('mes-donnees.dossier', ['d' => $donnees->dossier($request->user())]);
    }

    /** Le même document, en fichier .html autonome (CSS en ligne, aucun script, aucune ressource externe). */
    public function dossierTelecharger(Request $request, DonneesPersonnelles $donnees): Response
    {
        $html = $donnees->rendreAutonome('documents.dossier', ['d' => $donnees->dossier($request->user())]);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mon-dossier-nova-terra-'.now()->format('Y-m-d').'.html"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
