<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRequest;
use App\Services\NovaTerraApi;
use App\Services\NovaTerraApiException;
use Illuminate\Http\RedirectResponse;
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
    public function store(NovaTerraApi $api): RedirectResponse
    {
        try {
            $result = $api->sync();
            $resultat = $result['busy']
                ? ['ok' => null, 'message' => __('Une synchronisation est déjà en cours. Réessayez dans quelques instants.')]
                : ['ok' => true, 'message' => __(':received demandes reçues, :new nouvelles.', ['received' => $result['received'], 'new' => $result['new']]).' '.trans_choice(NovaTerraApi::IMPORT_MESSAGE, $result['imported'])];
        } catch (NovaTerraApiException $e) {
            $resultat = ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            // Jamais de page 500 : on journalise et on affiche l'erreur dans la page (last_error est écrit par le service).
            Log::error('Synchronisation manuelle échouée : '.$e->getMessage());
            $resultat = ['ok' => false, 'message' => NovaTerraApi::unexpectedErrorMessage()];
        }

        return redirect()->route('admin.synchronisation.index')->with('sync_result', $resultat);
    }
}
