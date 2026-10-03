<?php

namespace App\Http\Controllers;

use App\Services\DonneesPersonnelles;
use App\Support\DateLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** F56 : récapitulatif de MES demandes (user_id strict), en CSV et en version imprimable. Citoyen seulement. */
class RecapitulatifDemandesController extends Controller
{
    public function csv(Request $request, DonneesPersonnelles $donnees): Response
    {
        $date = fn ($d) => $d ? DateLocale::format($d) : '';

        $flux = fopen('php://temp', 'w+');
        fwrite($flux, "\xEF\xBB\xBF"); // BOM UTF-8 : Excel affiche correctement les accents

        fputcsv($flux, [__('Référence'), __('Objet'), __('Service'), __('Statut'), __('Créée le'), __('Dernière mise à jour'), __('Traitée le')], ';', '"', '');
        foreach ($donnees->demandes($request->user()) as $demande) {
            fputcsv($flux, array_map(self::cellule(...), [
                (string) $demande->reference,
                $demande->objet,
                $demande->service?->nom ?? __('À orienter'),
                $demande->statut->label(),
                $date($demande->created_at),
                $date($demande->updated_at),
                $date($demande->traitee_at),
            ]), ';', '"', '');
        }

        rewind($flux);
        $contenu = stream_get_contents($flux);
        fclose($flux);

        return new Response($contenu, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mes-demandes-nova-terra-'.now()->format('Y-m-d').'.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function recapitulatif(Request $request, DonneesPersonnelles $donnees): View
    {
        return view('demandes.recapitulatif', ['demandes' => $donnees->demandes($request->user())]);
    }

    /** Protection contre l'injection de formules : une cellule qui commence par = + - @ tabulation ou retour chariot est préfixée d'une apostrophe. */
    public static function cellule(string $valeur): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valeur) === 1 ? "'".$valeur : $valeur;
    }
}
