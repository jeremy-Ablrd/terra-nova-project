<?php

namespace App\Enums;

/** Types d'événements du journal de sécurité (F37, F54, F70). */
enum TypeEvenementSecurite: string
{
    case EchecConnexion = 'echec_connexion';
    case Blocage = 'blocage';
    case NouvelAppareil = 'nouvel_appareil';
    case AccesRefuse = 'acces_refuse';
    case FormulaireSuspect = 'formulaire_suspect';

    public function label(): string
    {
        return match ($this) {
            self::EchecConnexion => __('Échec de connexion'),
            self::Blocage => __('Blocage temporaire'),
            self::NouvelAppareil => __('Nouvel appareil'),
            self::AccesRefuse => __('Accès refusé'),
            self::FormulaireSuspect => __('Formulaire suspect'),
        };
    }
}
