<?php

namespace App\Enums;

/** Disponibilité d'un service. Toujours affichée en texte (jamais la couleur seule). */
enum Disponibilite: string
{
    case Disponible = 'disponible';
    case Interrompu = 'interrompu';

    public function label(): string
    {
        return match ($this) {
            self::Disponible => __('Disponible'),
            self::Interrompu => __('Service interrompu'),
        };
    }
}
