<?php

namespace App\Services;

use App\Enums\TypeEvenementSecurite;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Limitation des tentatives de connexion (F37). Trois limites, toujours temporaires :
 *  - e-mail + IP : 5 échecs, puis blocage de 10 minutes ;
 *  - IP seule    : 20 échecs en 10 minutes (désactivable : NOVATERRA_LIMITE_IP=false si l'IP est celle d'un proxy) ;
 *  - e-mail seul : 15 échecs en 15 minutes.
 * Un succès ne remet à zéro que la clé e-mail + IP. Chaque échec et chaque blocage crée un événement de sécurité.
 */
class SecuriteConnexion
{
    public const MAX_EMAIL_IP = 5;

    public const BLOCAGE_EMAIL_IP = 600;

    public const MAX_IP = 20;

    public const FENETRE_IP = 600;

    public const MAX_EMAIL = 15;

    public const FENETRE_EMAIL = 900;

    public function __construct(private readonly JournalSecurite $journal) {}

    /** E-mail normalisé : espaces retirés, minuscules. */
    public static function normaliser(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    /** Secondes à attendre avant une nouvelle tentative, 0 si aucune limite n'est atteinte. Identique que l'e-mail existe ou non. */
    public function secondesRestantes(string $email, string $ip): int
    {
        $restantes = [0];

        $finBlocage = (int) Cache::get($this->cleBlocage($email, $ip), 0);
        if ($finBlocage > now()->getTimestamp()) {
            $restantes[] = $finBlocage - now()->getTimestamp();
        }
        if ($this->limiteIpActive() && RateLimiter::tooManyAttempts($this->cleIp($ip), self::MAX_IP)) {
            $restantes[] = RateLimiter::availableIn($this->cleIp($ip));
        }
        if (RateLimiter::tooManyAttempts($this->cleEmail($email), self::MAX_EMAIL)) {
            $restantes[] = RateLimiter::availableIn($this->cleEmail($email));
        }

        return max($restantes);
    }

    /** Enregistre un échec : compteurs, événement « échec », et événement « blocage » si une limite vient d'être atteinte. */
    public function echec(string $email, string $ip): void
    {
        $compte = User::where('email', $email)->first();
        $this->journal->enregistrer(TypeEvenementSecurite::EchecConnexion, $compte, $email, 'identifiants incorrects');

        if (RateLimiter::hit($this->cleEmailIp($email, $ip), self::BLOCAGE_EMAIL_IP) >= self::MAX_EMAIL_IP) {
            Cache::put($this->cleBlocage($email, $ip), now()->addSeconds(self::BLOCAGE_EMAIL_IP)->getTimestamp(), self::BLOCAGE_EMAIL_IP);
            RateLimiter::clear($this->cleEmailIp($email, $ip));
            $this->journal->enregistrer(TypeEvenementSecurite::Blocage, $compte, $email, 'e-mail et adresse IP : blocage de 10 minutes');
        }

        if ($this->limiteIpActive() && RateLimiter::hit($this->cleIp($ip), self::FENETRE_IP) === self::MAX_IP) {
            $this->journal->enregistrer(TypeEvenementSecurite::Blocage, null, null, 'adresse IP : trop d\'échecs');
        }

        if (RateLimiter::hit($this->cleEmail($email), self::FENETRE_EMAIL) === self::MAX_EMAIL) {
            $this->journal->enregistrer(TypeEvenementSecurite::Blocage, $compte, $email, 'e-mail : trop d\'échecs');
        }
    }

    /** Un succès ne remet à zéro que la clé e-mail + IP (jamais l'IP seule ni l'e-mail seul). */
    public function succes(string $email, string $ip): void
    {
        RateLimiter::clear($this->cleEmailIp($email, $ip));
        Cache::forget($this->cleBlocage($email, $ip));
    }

    public function limiteIpActive(): bool
    {
        return (bool) config('securite.limite_ip');
    }

    private function cleEmailIp(string $email, string $ip): string
    {
        return 'connexion:email-ip:'.sha1($email.'|'.$ip);
    }

    private function cleBlocage(string $email, string $ip): string
    {
        return 'connexion:blocage:'.sha1($email.'|'.$ip);
    }

    private function cleIp(string $ip): string
    {
        return 'connexion:ip:'.sha1($ip);
    }

    private function cleEmail(string $email): string
    {
        return 'connexion:email:'.sha1($email);
    }
}
