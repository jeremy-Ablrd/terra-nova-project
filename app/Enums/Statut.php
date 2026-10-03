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

    /** Classes Tailwind du badge (réutilisées côté citoyen et côté agent). */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Nouvelle => 'bg-blue-100 text-blue-800',
            self::EnCours => 'bg-amber-100 text-amber-800',
            self::Traitee => 'bg-green-100 text-green-800',
        };
    }
}
