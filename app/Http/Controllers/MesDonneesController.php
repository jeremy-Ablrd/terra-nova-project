<?php

namespace App\Http\Controllers;

use App\Services\DonneesPersonnelles;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** F55 : mes informations personnelles, à lire sur la page et à télécharger en JSON. Citoyen seulement (role:citoyen). */
class MesDonneesController extends Controller
{
    public function index(Request $request, DonneesPersonnelles $donnees): View
    {
        return view('mes-donnees.index', ['resume' => $donnees->resume($request->user())]);
    }

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
