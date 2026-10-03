<?php

namespace App\Http\Controllers;

use App\Services\DonneesPersonnelles;
use App\Support\DateLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * F56 : récapitulatif de MES demandes (user_id strict). Livrable principal : le document lisible (page imprimable et
 * fichier .html autonome). Le CSV est la « version tableur ». Citoyen seulement (role:citoyen).
 */
class RecapitulatifDemandesController extends Controller
{
    /** Version tableur : colonnes lisibles, statuts en texte, dates locales, calculs prêts à l'emploi. */
    public function csv(Request $request, DonneesPersonnelles $donnees): Response
    {
        $date = fn ($d) => $d ? DateLocale::format($d) : '';

        $flux = fopen('php://temp', 'w+');
        fwrite($flux, "\xEF\xBB\xBF"); // BOM UTF-8 : Excel affiche correctement les accents

        fputcsv($flux, [
            __('Référence'), __('Objet'), __('Service'), __('Statut'), __('Créée le'), __('Dernière mise à jour'), __('Traitée le'),
            __('Âge (jours)'), __('Délai de traitement (jours)'), __('Étape actuelle'), __('Ce que cela signifie'),
        ], ';', '"', '');

        foreach ($donnees->dossier($request->user())['lignes'] as $ligne) {
            $demande = $ligne['demande'];

            fputcsv($flux, array_map(self::cellule(...), [
                (string) $demande->reference,
                $demande->objet,
                $ligne['service'],
                $demande->statut->label(),
                $date($demande->created_at),
                $date($demande->updated_at),
                $date($demande->traitee_at),
                (string) $ligne['age_jours'],
                $ligne['delai_jours'] === null ? '' : number_format($ligne['delai_jours'], 1, ',', ''),
                $ligne['etape'],
                $ligne['signification'],
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

    /** Protection contre l'injection de formules : une cellule qui commence par = + - @ tabulation ou retour chariot est préfixée d'une apostrophe. */
    public static function cellule(string $valeur): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valeur) === 1 ? "'".$valeur : $valeur;
    }
}
