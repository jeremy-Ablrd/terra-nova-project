<?php

namespace App\Services;

use App\Enums\TypeEvenementSecurite;
use App\Models\AppareilConnu;
use App\Models\EvenementSecurite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Appareils connus d'un compte et alerte « nouvelle connexion » (F54). Pas d'e-mail : l'alerte est affichée dans le site.
 * Le navigateur garde un cookie aléatoire de 40 caractères ; seule son empreinte SHA-256 est stockée.
 */
class AppareilsConnus
{
    public const COOKIE = 'appareil_nt';

    private const UN_AN_EN_MINUTES = 525600;

    public function __construct(private readonly JournalSecurite $journal) {}

    /**
     * À appeler après chaque connexion réussie (et à l'inscription). Appareil connu : date mise à jour. Appareil inconnu :
     * il est enregistré, et si le compte avait déjà un appareil, un événement « nouvel appareil » déclenche l'alerte.
     * Le tout premier appareil d'un compte ne déclenche rien.
     */
    public function enregistrerConnexion(Request $request, User $utilisateur): ?EvenementSecurite
    {
        $jeton = $request->cookie(self::COOKIE);
        if (! is_string($jeton) || strlen($jeton) !== 40) {
            $jeton = Str::random(40);
        }
        $hash = hash('sha256', $jeton);

        $evenement = null;
        $appareil = AppareilConnu::where('user_id', $utilisateur->id)->where('jeton_hash', $hash)->first();

        if ($appareil) {
            $appareil->derniere_vue_at = now();
            $appareil->save();
        } else {
            $dejaUnAppareil = AppareilConnu::where('user_id', $utilisateur->id)->exists();

            $appareil = new AppareilConnu;
            $appareil->user_id = $utilisateur->id;
            $appareil->jeton_hash = $hash;
            $appareil->libelle = self::libelle((string) $request->userAgent());
            $appareil->premiere_vue_at = now();
            $appareil->derniere_vue_at = now();
            $appareil->save();

            if ($dejaUnAppareil) {
                $evenement = $this->journal->enregistrer(TypeEvenementSecurite::NouvelAppareil, $utilisateur, $utilisateur->email, $appareil->libelle, $appareil->id, $request);
            }
        }

        // Cookie httpOnly, SameSite=Lax, Secure en production, un an.
        Cookie::queue(Cookie::make(self::COOKIE, $jeton, self::UN_AN_EN_MINUTES, '/', null, app()->environment('production'), true, false, 'lax'));

        return $evenement;
    }

    /** Empreinte de l'appareil courant, pour le repérer dans la liste. */
    public function empreinteCourante(Request $request): ?string
    {
        $jeton = $request->cookie(self::COOKIE);

        return is_string($jeton) && strlen($jeton) === 40 ? hash('sha256', $jeton) : null;
    }

    /**
     * Alertes « nouvelle connexion » pas encore vues par l'utilisateur : une seule requête par requête HTTP.
     *
     * @return Collection<int, EvenementSecurite>
     */
    public function alertesNonVues(?User $utilisateur): Collection
    {
        if ($utilisateur === null) {
            return collect();
        }

        $requete = request();
        $cle = 'alertes_connexion_'.$utilisateur->id;

        if (! $requete->attributes->has($cle)) {
            $requete->attributes->set($cle, EvenementSecurite::query()
                ->where('user_id', $utilisateur->id)
                ->where('type', TypeEvenementSecurite::NouvelAppareil->value)
                ->whereNull('vu_at')
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit(10)
                ->get());
        }

        return $requete->attributes->get($cle);
    }

    /** Déconnecte tous les autres appareils : sessions (table sessions) et jeton « se souvenir de moi ». */
    public function deconnecterAutres(User $utilisateur, string $sessionCourante): void
    {
        $table = config('session.table', 'sessions');
        if (Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $utilisateur->id)->where('id', '!=', $sessionCourante)->delete();
        }

        $utilisateur->forceFill(['remember_token' => Str::random(60)])->save();
    }

    /** « Firefox sur Windows » : navigateur et système déduits de l'en-tête User-Agent. */
    public static function libelle(string $agent): string
    {
        $navigateur = match (true) {
            str_contains($agent, 'Edg/') || str_contains($agent, 'Edge/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => __('Navigateur inconnu'),
        };

        $systeme = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Macintosh') || str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') || str_contains($agent, 'X11') => 'Linux',
            default => __('système inconnu'),
        };

        return __(':navigateur sur :systeme', ['navigateur' => $navigateur, 'systeme' => $systeme]);
    }
}
