<?php

namespace App\Enums;

/** Priorité d'une demande. Le citoyen ne fixe que « urgence médicale » (case du formulaire de contact) ; l'agent fixe les trois niveaux. */
enum Priorite: string
{
    case Normale = 'normale';
    case Prioritaire = 'prioritaire';
    case UrgenceMedicale = 'urgence_medicale';

    public function label(): string
    {
        return match ($this) {
            self::Normale => __('Normale'),
            self::Prioritaire => __('Prioritaire'),
            self::UrgenceMedicale => __('Urgence médicale'),
        };
    }

    /** Glyphe du badge : il double la couleur (jamais la couleur seule). */
    public function glyphe(): string
    {
        return match ($this) {
            self::Normale => '○',
            self::Prioritaire => '▲',
            self::UrgenceMedicale => '✚',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Normale => '',
            self::Prioritaire => 'tn-badge--warn',
            self::UrgenceMedicale => 'tn-badge--danger',
        };
    }
}
