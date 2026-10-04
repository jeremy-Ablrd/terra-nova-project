<?php

namespace App\Enums;

/**
 * Émetteur officiel d'une alerte (F73) : liste fermée. Chaque clé a sa phrase complète et traduite : jamais une
 * phrase à trous « de {émetteur} », qui ferait des fautes de grammaire (du / de la / de l').
 */
enum Emetteur: string
{
    case HautConseil = 'haut_conseil';
    case Ville = 'ville';
    case ServiceCommunication = 'service_communication';

    /** Nom court, pour la liste déroulante du formulaire. */
    public function label(): string
    {
        return match ($this) {
            self::HautConseil => __('Haut Conseil'),
            self::Ville => __('Ville de Terra Nova'),
            self::ServiceCommunication => __('Service communication'),
        };
    }

    /** Phrase complète affichée au public. */
    public function phrase(): string
    {
        return match ($this) {
            self::HautConseil => __('Message officiel du Haut Conseil'),
            self::Ville => __('Message officiel de la Ville de Terra Nova'),
            self::ServiceCommunication => __('Message officiel du service communication'),
        };
    }
}
