<?php

namespace App\Enums;

/** Catégorie d'un service municipal (filtre du catalogue). L'ordre des cas est celui des liens de filtre. */
enum CategorieService: string
{
    case Sante = 'sante';
    case Administratif = 'administratif';
    case Transport = 'transport';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Sante => __('Santé'),
            self::Administratif => __('Administratif'),
            self::Transport => __('Transport'),
            self::Autre => __('Autre'),
        };
    }
}
