<?php

namespace App\Enums;

/**
 * Type d'une demande. Seules les demandes « citoyen » (formulaire de contact et import « Citoyen ») sont affichées
 * et comptées dans le Centre technique municipal. Les valeurs institution et alerte ne servent qu'à repérer, et
 * garder masquées, des lignes importées avant le retour à l'import « Citoyen » seul.
 */
enum TypeDemande: string
{
    case Citoyen = 'citoyen';
    case Institution = 'institution';
    case Alerte = 'alerte';

    public function label(): string
    {
        return match ($this) {
            self::Citoyen => 'Citoyen',
            self::Institution => 'Institution',
            self::Alerte => 'Alerte',
        };
    }
}
