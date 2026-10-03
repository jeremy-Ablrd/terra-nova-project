<?php

namespace App\Support;

use App\Enums\TailleTexte;
use App\Enums\ThemeAffichage;

/**
 * Réglages d'affichage de la requête en cours : le compte de l'utilisateur connecté s'il en a un, sinon le cookie,
 * sinon la valeur par défaut. Aucune requête SQL (l'utilisateur est déjà chargé par l'authentification).
 */
class Affichage
{
    public const COOKIE_TAILLE = 'affichage_taille';

    public const COOKIE_THEME = 'affichage_theme';

    /** Durée de mémorisation du cookie : un an (en minutes). */
    public const COOKIE_MINUTES = 525600;

    public static function taille(): TailleTexte
    {
        return TailleTexte::tryFrom(self::valeur('taille', self::COOKIE_TAILLE)) ?? TailleTexte::Normal;
    }

    public static function theme(): ThemeAffichage
    {
        return ThemeAffichage::tryFrom(self::valeur('theme', self::COOKIE_THEME)) ?? ThemeAffichage::Standard;
    }

    /** Classes à poser sur <html>, séparées par des espaces (vide pour l'affichage par défaut). */
    public static function classes(): string
    {
        return trim(self::taille()->classe().' '.self::theme()->classe());
    }

    private static function valeur(string $cle, string $cookie): string
    {
        $request = request();
        $compte = $request->user()?->preferences[$cle] ?? null;
        $valeur = is_string($compte) ? $compte : $request->cookie($cookie);

        return is_string($valeur) ? $valeur : '';
    }
}
