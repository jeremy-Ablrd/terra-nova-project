<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionJournal;
use App\Http\Controllers\Controller;
use App\Models\ApiRequest;
use App\Services\Journal;
use App\Services\NovaTerraApi;
use App\Services\NovaTerraApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class SynchronisationController extends Controller
{
    public function index(): View
    {
        $lastSync = Cache::get(NovaTerraApi::CACHE_LAST_SYNC);

        return view('admin.synchronisation.index', [
            'lastSync' => $lastSync ? Carbon::parse($lastSync) : null,
            'lastError' => Cache::get(NovaTerraApi::CACHE_LAST_ERROR),
            'lastResult' => Cache::get(NovaTerraApi::CACHE_LAST_RESULT),
            'session' => Cache::get(NovaTerraApi::CACHE_SESSION),
            'total' => ApiRequest::count(),
            // Résultat de l'action qui vient d'être lancée (affiché dans la page, pas dans le bandeau flash).
            'resultat' => session('sync_result'),
        ]);
    }

    /** Même code que la commande novaterra:sync : NovaTerraApi::sync(). */
    public function store(Request $request, NovaTerraApi $api): RedirectResponse
    {
        try {
            $result = $api->sync();
            $resultat = $result['busy']
                ? ['ok' => null, 'message' => __('Une synchronisation est déjà en cours. Réessayez dans quelques instants.')]
                : ['ok' => true, 'message' => __(':received demandes reçues, :new nouvelles.', ['received' => $result['received'], 'new' => $result['new']]).' '.trans_choice(NovaTerraApi::IMPORT_MESSAGE, $result['imported'])];
            $detail = $result['busy']
                ? 'non exécutée : une synchronisation était déjà en cours'
                : "{$result['received']} demandes reçues, {$result['new']} nouvelles, {$result['imported']} importées";
        } catch (NovaTerraApiException $e) {
            $resultat = ['ok' => false, 'message' => $e->getMessage()];
            $detail = 'échec : erreur de l\'API';
        } catch (Throwable $e) {
            // Jamais de page 500 : on journalise et on affiche l'erreur dans la page (last_error est écrit par le service).
            Log::error('Synchronisation manuelle échouée : '.$e->getMessage());
            $resultat = ['ok' => false, 'message' => NovaTerraApi::unexpectedErrorMessage()];
            $detail = 'échec : erreur inattendue';
        }

        // Journal d'activité. La synchronisation appelle l'API et enchaîne plusieurs transactions sous un verrou de cache
        // (stocké en base) : l'envelopper dans une seule transaction rendrait ce verrou invisible aux autres connexions.
        // L'entrée est donc écrite juste après, qu'elle réussisse, échoue ou soit refusée (verrou pris).
        Journal::enregistrer($request->user(), ActionJournal::SynchronisationLancee, null, $detail);

        return redirect()->route('admin.synchronisation.index')->with('sync_result', $resultat);
    }
}
