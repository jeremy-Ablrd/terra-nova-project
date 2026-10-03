<?php

namespace App\Enums;

/** Niveau d'une alerte. Toujours affiché en texte (jamais la couleur seule). */
enum Niveau: string
{
    case Info = 'info';
    case Vigilance = 'vigilance';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Info => __('Information'),
            self::Vigilance => __('Vigilance'),
            self::Urgent => __('Urgent'),
        };
    }

    /** Classe CSS de mise en avant (bordure pleine, double ou pointillée + variables du thème). */
    public function classe(): string
    {
        return match ($this) {
            self::Info => 'alerte-info',
            self::Vigilance => 'alerte-vigilance',
            self::Urgent => 'alerte-urgent',
        };
    }

    /** Rôle ARIA : « alert » (annoncé tout de suite) uniquement pour l'urgent, « status » sinon. */
    public function role(): string
    {
        return $this === self::Urgent ? 'alert' : 'status';
    }
}
