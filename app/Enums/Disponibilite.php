<?php

namespace App\Enums;

/**
 * État d'un service, toujours écrit en texte (jamais la couleur seule).
 *  - Disponible ;
 *  - Interrompu : une information, le service reste utilisable (la mention prévient) ;
 *  - Désactivé : coupure d'urgence réservée à l'admin, le service ne peut plus être choisi dans /contact.
 */
enum Disponibilite: string
{
    case Disponible = 'disponible';
    case Interrompu = 'interrompu';
    case Desactive = 'desactive';

    public function label(): string
    {
        return match ($this) {
            self::Disponible => __('Disponible'),
            self::Interrompu => __('Service interrompu'),
            self::Desactive => __('Service désactivé'),
        };
    }

    /** Rang d'affichage : les services désactivés, puis interrompus, remontent avant les disponibles. */
    public function rang(): int
    {
        return match ($this) {
            self::Desactive => 0,
            self::Interrompu => 1,
            self::Disponible => 2,
        };
    }
}
