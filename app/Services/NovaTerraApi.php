<?php

namespace App\Services;

use App\Models\ApiRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NovaTerraApi
{
    public const CACHE_SESSION = 'novaterra.session';

    public const CACHE_LAST_SYNC = 'novaterra.last_sync_at';

    public const CACHE_LAST_ERROR = 'novaterra.last_error';

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
            throw new NovaTerraApiException('API Nova Terra injoignable : '.$e->getMessage(), previous: $e);
        }

        if ($response->status() === 403) {
            throw new NovaTerraApiException('API Nova Terra : accès refusé (403). Vérifiez la clé WEBCUP_API_KEY.');
        }

        if ($response->failed()) {
            throw new NovaTerraApiException('API Nova Terra : erreur HTTP '.$response->status().'.');
        }

        $data = $response->json();

        if (! is_array($data) || ! is_array($data['requests'] ?? null)) {
            throw new NovaTerraApiException('API Nova Terra : réponse inattendue (liste "requests" absente).');
        }

        return $data;
    }

    /**
     * Récupère les demandes, les enregistre et met à jour le cache.
     * En cas d'erreur, l'ancien contenu (base et cache) est conservé et le message est mémorisé.
     *
     * @return array{received: int, new: int}
     *
     * @throws NovaTerraApiException
     */
    public function sync(): array
    {
        try {
            $data = $this->fetch();
        } catch (NovaTerraApiException $e) {
            Cache::forever(self::CACHE_LAST_ERROR, $e->getMessage());

            throw $e;
        }

        $result = $this->store($data['requests']);

        if (is_array($data['session'] ?? null)) {
            Cache::forever(self::CACHE_SESSION, $data['session']);
        }
        Cache::forever(self::CACHE_LAST_SYNC, now()->toIso8601String());
        Cache::forget(self::CACHE_LAST_ERROR);

        return $result;
    }

    /**
     * Upsert sur request_code. first_seen_at n'est écrit qu'à l'insertion (exclu des colonnes mises à jour).
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
        $known = ApiRequest::whereIn('request_code', $codes)->pluck('request_code')->all();

        if ($rows !== []) {
            $updatable = array_values(array_diff(array_keys($rows[0]), ['request_code', 'first_seen_at']));

            foreach (array_chunk($rows, 200) as $chunk) {
                ApiRequest::upsert($chunk, ['request_code'], $updatable);
            }
        }

        return [
            'received' => count($rows),
            'new' => count(array_diff($codes, $known)),
        ];
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
