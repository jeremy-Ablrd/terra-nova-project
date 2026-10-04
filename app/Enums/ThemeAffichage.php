<?php

namespace App\Enums;

/** Thème d'affichage : standard (jour), nuit, ou « contraste renforcé » (texte noir, bordures marquées, focus épais). */
enum ThemeAffichage: string
{
    case Standard = 'standard';
    case Nuit = 'nuit';
    case Contraste = 'contraste';

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('Standard'),
            self::Nuit => __('Nuit'),
            self::Contraste => __('Contraste renforcé'),
        };
    }

    /** Classe posée sur <html> (voir resources/css/app.css). Le thème nuit passe par data-theme (terra-nova.css). */
    public function classe(): string
    {
        return match ($this) {
            self::Standard, self::Nuit => '',
            self::Contraste => 'theme-contraste',
        };
    }

    /** Valeur de <html data-theme> : « nuit » seulement pour le thème nuit, « jour » sinon. */
    public function dataTheme(): string
    {
        return $this === self::Nuit ? 'nuit' : 'jour';
    }
}
