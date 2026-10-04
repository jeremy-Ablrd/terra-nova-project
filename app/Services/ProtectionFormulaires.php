<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Protection des formulaires (F81 envois automatiques, F82 envois en double), sans CAPTCHA.
 *
 * Chaque affichage d'un formulaire porte un jeton signé (HMAC avec la clé de l'application) qui contient un identifiant
 * unique et l'heure d'affichage. Le jeton sert à trois choses : refuser un envoi plus rapide que le délai minimal
 * (un robot remplit et envoie en une fraction de seconde), refuser un jeton falsifié ou expiré, et n'accepter qu'UN SEUL
 * succès par affichage (le résultat est mémorisé : un renvoi mène au résultat d'origine). Le champ leurre, invisible
 * pour l'habitant, complète le dispositif. Activée par NOVATERRA_PROTECTION_FORMULAIRES (config/securite.php).
 */
class ProtectionFormulaires
{
    public const CHAMP_JETON = '_jeton_formulaire';

    public function active(): bool
    {
        return (bool) config('securite.protection_formulaires');
    }

    /** Nom du champ leurre : un nom plausible pour un robot, jamais un champ réel des formulaires. */
    public function nomLeurre(): string
    {
        return (string) config('securite.formulaires.champ_leurre');
    }

    /** Nouveau jeton, pour un affichage : « identifiant.heure.signature ». */
    public function jeton(): string
    {
        $charge = bin2hex(random_bytes(12)).'.'.now()->timestamp;

        return $charge.'.'.$this->signer($charge);
    }

    /** @return array{nonce: string, heure: int}|null null si le jeton est absent, mal formé ou falsifié */
    public function lire(mixed $jeton): ?array
    {
        if (! is_string($jeton) || substr_count($jeton, '.') !== 2) {
            return null;
        }

        [$nonce, $heure, $signature] = explode('.', $jeton);
        if (! ctype_xdigit($nonce) || ! ctype_digit($heure) || ! hash_equals($this->signer($nonce.'.'.$heure), $signature)) {
            return null;
        }

        return ['nonce' => $nonce, 'heure' => (int) $heure];
    }

    /** Secondes écoulées entre l'affichage du formulaire et maintenant. */
    public function age(array $jeton): int
    {
        return now()->timestamp - $jeton['heure'];
    }

    public function delaiMinimal(): int
    {
        return (int) config('securite.formulaires.delai_minimal');
    }

    public function expire(array $jeton): bool
    {
        return $this->age($jeton) > (int) config('securite.formulaires.validite_minutes') * 60;
    }

    /** Résultat d'origine d'un jeton déjà consommé : ['url' => …], ou null. */
    public function dejaFait(string $nonce): ?array
    {
        return Cache::get($this->cle($nonce));
    }

    public function marquerFait(string $nonce, string $url): void
    {
        Cache::put($this->cle($nonce), ['url' => $url], now()->addMinutes((int) config('securite.formulaires.validite_minutes')));
    }

    /** Verrou d'un jeton : deux envois simultanés du même jeton (double clic) ne s'exécutent jamais en même temps. */
    public function verrou(string $nonce): \Illuminate\Contracts\Cache\Lock
    {
        return Cache::lock('formulaire:verrou:'.$nonce, 20);
    }

    private function cle(string $nonce): string
    {
        return 'formulaire:fait:'.$nonce;
    }

    private function signer(string $charge): string
    {
        return hash_hmac('sha256', $charge, (string) config('app.key'));
    }
}
