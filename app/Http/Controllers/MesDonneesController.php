<?php

namespace App\Http\Controllers;

use App\Services\DonneesPersonnelles;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * F55 : mes informations personnelles. Livrable principal : « Mon dossier », un document lisible (page imprimable et
 * fichier .html autonome). Le JSON est le format informatique, en second plan. Citoyen seulement (role:citoyen).
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

    /** Format informatique (JSON), pour réutiliser ses données dans un autre outil. */
    public function exportJson(Request $request, DonneesPersonnelles $donnees): Response
    {
        $contenu = json_encode(
            $donnees->export($request->user()),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return new Response($contenu."\n", 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mes-donnees-nova-terra-'.now()->format('Y-m-d').'.json"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
