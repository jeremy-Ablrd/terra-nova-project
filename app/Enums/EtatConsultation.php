<?php

namespace App\Enums;

/** État de la consultation d'un projet, calculé à chaque requête (jamais stocké ni mis en cache). */
enum EtatConsultation: string
{
    case Aucune = 'aucune';
    case AVenir = 'a_venir';
    case Ouverte = 'ouverte';
    case Close = 'close';

    public function label(): string
    {
        return match ($this) {
            self::Aucune => __('Pas de consultation'),
            self::AVenir => __('Consultation à venir'),
            self::Ouverte => __('Consultation ouverte'),
            self::Close => __('Consultation terminée'),
        };
    }

    public function glyphe(): string
    {
        return match ($this) {
            self::Aucune => '○',
            self::AVenir => '◆',
            self::Ouverte => '▲',
            self::Close => '✓',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Aucune => '',
            self::AVenir => 'tn-badge--info',
            self::Ouverte => 'tn-badge--warn',
            self::Close => 'tn-badge--success',
        };
    }

    /** Ordre d'affichage sur /projets : ouvertes d'abord. */
    public function rang(): int
    {
        return match ($this) {
            self::Ouverte => 0,
            self::AVenir => 1,
            self::Aucune => 2,
            self::Close => 3,
        };
    }
}
