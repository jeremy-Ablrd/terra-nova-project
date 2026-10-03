<?php

namespace App\Enums;

/** Thème d'affichage : standard, ou « contraste renforcé » (texte noir, bordures marquées, focus épais). */
enum ThemeAffichage: string
{
    case Standard = 'standard';
    case Contraste = 'contraste';

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('Standard'),
            self::Contraste => __('Contraste renforcé'),
        };
    }

    /** Classe posée sur <html> (voir resources/css/app.css). */
    public function classe(): string
    {
        return match ($this) {
            self::Standard => '',
            self::Contraste => 'theme-contraste',
        };
    }
}
