<?php

namespace App\Enums;

enum Statut: string
{
    case Nouvelle = 'nouvelle';
    case EnCours = 'en_cours';
    case Traitee = 'traitee';

    public function label(): string
    {
        return match ($this) {
            self::Nouvelle => 'Nouvelle',
            self::EnCours => 'En cours',
            self::Traitee => 'Traitée',
        };
    }

    /** Le seul statut atteignable depuis celui-ci (pas de retour en arrière, pas de saut) ; null si terminé. */
    public function suivant(): ?self
    {
        return match ($this) {
            self::Nouvelle => self::EnCours,
            self::EnCours => self::Traitee,
            self::Traitee => null,
        };
    }

    /** Glyphe du badge : il double la couleur (jamais la couleur seule). */
    public function glyphe(): string
    {
        return match ($this) {
            self::Nouvelle => '◆',
            self::EnCours => '▲',
            self::Traitee => '✓',
        };
    }

    /** Classes Tailwind du badge (réutilisées côté citoyen et côté agent). */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Nouvelle => 'tn-badge--info',
            self::EnCours => 'tn-badge--warn',
            self::Traitee => 'tn-badge--success',
        };
    }
}
