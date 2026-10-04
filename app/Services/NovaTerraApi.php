<?php

namespace App\Services;

use App\Models\ApiRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NovaTerraApi
{
    public const CACHE_SESSION = 'novaterra.session';

    public const CACHE_LAST_SYNC = 'novaterra.last_sync_at';

    public const CACHE_LAST_ERROR = 'novaterra.last_error';

    public const CACHE_LAST_RESULT = 'novaterra.last_result';

    /** Verrou unique : une seule synchronisation à la fois (commande, planificateur et bouton admin). */
    public const LOCK_KEY = 'novaterra.sync';

    public const LOCK_SECONDS = 120;

    private const CHUNK_SIZE = 200;

    /** Message (singulier/pluriel) du nombre de demandes citoyennes importées dans `demandes`. */
    public const IMPORT_MESSAGE = '{0} Aucune nouvelle demande citoyenne importée|{1} :count demande citoyenne importée|[2,*] :count demandes citoyennes importées';

    public function __construct(private readonly ImporteDemandesApi $importeur) {}

    /** Message mémorisé (et affiché) pour toute erreur qui n'est pas une erreur de l'API. */
    public static function unexpectedErrorMessage(): string
    {
        return __('Erreur inattendue pendant la synchronisation. Consultez les journaux.');
    }

    /**
     * Appelle l'API et retourne la réponse décodée.
     *
     * @return array<string, mixed>
     *
     * @throws NovaTerraApiException
     */
    public function fetch(): array
    {
        $key = config('services.webcup.key');

        if (blank($key)) {
            throw new NovaTerraApiException('Clé API absente : renseignez WEBCUP_API_KEY dans le .env.');
        }

        try {
            $response = Http::withHeaders(['X-Webcup-Api-Key' => $key])
                ->acceptJson()
                ->timeout(10)
                ->connectTimeout(5)
                ->get(config('services.webcup.url'));
        } catch (ConnectionException $e) {
            throw new NovaTerraApiException('API Terra Nova injoignable : '.$e->getMessage(), previous: $e);
        }

        if ($response->status() === 403) {
            throw new NovaTerraApiException('API Terra Nova : accès refusé (403). Vérifiez la clé WEBCUP_API_KEY.');
        }

        if ($response->failed()) {
            throw new NovaTerraApiException('API Terra Nova : erreur HTTP '.$response->status().'.');
        }

        $data = $response->json();

        if (! is_array($data) || ! is_array($data['requests'] ?? null)) {
            throw new NovaTerraApiException('API Terra Nova : réponse inattendue (liste "requests" absente).');
        }

        return $data;
    }

    /**
     * Synchronise les demandes de l'API puis importe les demandes « Citoyen » dans `demandes`, le tout sous verrou.
     * L'import part de la base locale : il a lieu même si la synchro échoue (réseau, 403…). L'erreur de la synchro
     * est alors relancée après l'import, et mémorisée dans last_error.
     *
     * @return array{busy: bool, received: int, new: int, imported: int}
     *
     * @throws Throwable
     */
    public function sync(): array
    {
        return $this->withLock(function () {
            $erreur = null;
            $result = ['received' => 0, 'new' => 0];

            try {
                $result = $this->fetchAndStore();
            } catch (Throwable $e) {
                $erreur = $e;
            }

            $importees = 0;
            try {
                $importees = $this->importeur->import();
            } catch (Throwable $e) {
                if ($erreur === null) {
                    $erreur = $e;
                } else {
                    Log::error('Import des demandes échoué après un échec de synchro : '.$e->getMessage());
                }
            }

            if ($erreur !== null) {
                throw $erreur;
            }

            return $result + ['imported' => $importees];
        });
    }

    /**
     * Point d'entrée unique du verrou : exécute $work seulement si aucune autre synchronisation ne tourne.
     * Prévu pour englober aussi les étapes suivantes (ex. import des demandes) : les appeler DEPUIS $work.
     *
     * - verrou pris : rien n'est lancé, résultat `busy = true` (pas d'attente, pas d'erreur) ;
     * - toute exception (API ou autre) est mémorisée dans le cache `last_error`, puis relancée ;
     * - le verrou est toujours libéré (finally).
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed> $work fusionné avec `busy` ; verrou pris : busy = true, received/new/imported = 0
     *
     * @throws Throwable
     */
    public function withLock(callable $work): array
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return ['busy' => true, 'received' => 0, 'new' => 0, 'imported' => 0];
        }

        try {
            return ['busy' => false] + $work();
        } catch (NovaTerraApiException $e) {
            Cache::forever(self::CACHE_LAST_ERROR, $e->getMessage());

            throw $e;
        } catch (Throwable $e) {
            Cache::forever(self::CACHE_LAST_ERROR, self::unexpectedErrorMessage());

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Récupère les demandes, les enregistre et met à jour le cache. À appeler depuis withLock().
     * En cas d'erreur, l'ancien contenu (base et cache) est conservé.
     *
     * @return array{received: int, new: int}
     *
     * @throws NovaTerraApiException
     */
    private function fetchAndStore(): array
    {
        $data = $this->fetch();

        $result = $this->store($data['requests']);

        if (is_array($data['session'] ?? null)) {
            Cache::forever(self::CACHE_SESSION, $data['session']);
        }
        Cache::forever(self::CACHE_LAST_SYNC, now()->toIso8601String());
        Cache::forever(self::CACHE_LAST_RESULT, $result);
        Cache::forget(self::CACHE_LAST_ERROR);

        return $result;
    }

    /**
     * Upsert sur request_code, tous les lots dans UNE transaction (échec en cours de route : rien n'est écrit).
     * first_seen_at n'est écrit qu'à l'insertion (exclu des colonnes mises à jour).
     *
     * @param  array<int, mixed>  $requests
     * @return array{received: int, new: int}
     */
    private function store(array $requests): array
    {
        $now = now()->toDateTimeString();
        $rows = [];

        foreach ($requests as $request) {
            if (! is_array($request) || blank($request['request_code'] ?? null)) {
                continue; // sans request_code, impossible de dédoublonner
            }

            $rows[(string) $request['request_code']] = [
                'request_code' => (string) $request['request_code'],
                'api_id' => $this->int($request['id'] ?? $request['api_id'] ?? null),
                'requester_name' => $this->string($request['requester_name'] ?? null),
                'requester_type' => $this->string($request['requester_type'] ?? null),
                'message_public' => $this->string($request['message_public'] ?? null),
                'difficulty_level' => $this->int($request['difficulty_level'] ?? null),
                'difficulty' => $this->string($request['difficulty'] ?? null),
                'xp_base' => $this->int($request['xp_base'] ?? null) ?? 0,
                'xp_time_bonus' => $this->int($request['xp_time_bonus'] ?? null) ?? 0,
                'xp_total' => $this->int($request['xp_total'] ?? null) ?? 0,
                'xp_available' => $this->int($request['xp_available'] ?? null) ?? 0,
                'group_name' => $this->string($request['group_name'] ?? null),
                'sort_order' => $this->int($request['sort_order'] ?? null),
                'visible_since_wave' => $this->int($request['visible_since_wave'] ?? null),
                'arrival_type' => $this->string($request['arrival_type'] ?? null),
                'arrival_time' => $this->string($request['arrival_time'] ?? null),
                'first_seen_at' => $now,
                'payload' => json_encode($request, JSON_UNESCAPED_UNICODE),
            ];
        }

        $rows = array_values($rows);
        $codes = array_column($rows, 'request_code');

        return DB::transaction(function () use ($rows, $codes) {
            // Le calcul des « nouvelles » se fait dans la transaction, donc sous le verrou de synchronisation.
            $known = ApiRequest::whereIn('request_code', $codes)->pluck('request_code')->all();

            if ($rows !== []) {
                $updatable = array_values(array_diff(array_keys($rows[0]), ['request_code', 'first_seen_at']));

                foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                    ApiRequest::upsert($chunk, ['request_code'], $updatable);
                }
            }

            return [
                'received' => count($rows),
                'new' => count(array_diff($codes, $known)),
            ];
        });
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function string(mixed $value): ?string
    {
        // L'API envoie "" pour « pas de valeur » (ex. arrival_time des demandes initiales) : on stocke null.
        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }
}
