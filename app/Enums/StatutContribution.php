<?php

namespace App\Enums;

/** Statut d'une contribution : reçue → examinée → prise en compte. Jamais de retour en arrière, jamais de saut. */
enum StatutContribution: string
{
    case Recue = 'recue';
    case Examinee = 'examinee';
    case PriseEnCompte = 'prise_en_compte';

    public function label(): string
    {
        return match ($this) {
            self::Recue => __('Reçue'),
            self::Examinee => __('Examinée'),
            self::PriseEnCompte => __('Prise en compte'),
        };
    }

    /** Phrase lue par l'habitant sur la frise et dans l'encart de suivi. */
    public function phrase(): string
    {
        return __('suivi_participation.statut.'.$this->value);
    }

    public function suivant(): ?self
    {
        return match ($this) {
            self::Recue => self::Examinee,
            self::Examinee => self::PriseEnCompte,
            self::PriseEnCompte => null,
        };
    }

    /** Glyphe du badge : il double la couleur (jamais la couleur seule). */
    public function glyphe(): string
    {
        return match ($this) {
            self::Recue => '◆',
            self::Examinee => '▲',
            self::PriseEnCompte => '✓',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Recue => 'tn-badge--info',
            self::Examinee => 'tn-badge--warn',
            self::PriseEnCompte => 'tn-badge--success',
        };
    }
}
