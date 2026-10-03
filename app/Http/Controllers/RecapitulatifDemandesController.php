<?php

namespace App\Http\Controllers;

use App\Services\DonneesPersonnelles;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * F56 : récapitulatif de MES demandes (user_id strict), remis sous forme d'un document lisible et imprimable (page et
 * fichier .html autonome). Aucune donnée brute n'est proposée en téléchargement. Citoyen seulement (role:citoyen).
 */
class RecapitulatifDemandesController extends Controller
{
    /** Page du site : synthèse, tableau et chronologies, avec une mise en page d'impression. */
    public function recapitulatif(Request $request, DonneesPersonnelles $donnees): View
    {
        return view('demandes.recapitulatif', ['d' => $donnees->dossier($request->user())]);
    }

    /** Le même document, en fichier .html autonome (CSS en ligne, aucun script, aucune ressource externe). */
    public function telecharger(Request $request, DonneesPersonnelles $donnees): Response
    {
        $html = $donnees->rendreAutonome('documents.recapitulatif', ['d' => $donnees->dossier($request->user())]);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="recapitulatif-demandes-nova-terra-'.now()->format('Y-m-d').'.html"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
