<?php

namespace App\Enums;

/** Taille du texte choisie par l'utilisateur : change la taille racine (rem) de toute l'interface. */
enum TailleTexte: string
{
    case Normal = 'normal';
    case Grand = 'grand';
    case TresGrand = 'tres_grand';

    public function label(): string
    {
        return match ($this) {
            self::Normal => __('Normal'),
            self::Grand => __('Grand'),
            self::TresGrand => __('Très grand'),
        };
    }

    /** Classe posée sur <html> (voir resources/css/app.css : 100 %, 125 %, 150 % de la taille de base). */
    public function classe(): string
    {
        return match ($this) {
            self::Normal => '',
            self::Grand => 'taille-grand',
            self::TresGrand => 'taille-tres-grand',
        };
    }
}
