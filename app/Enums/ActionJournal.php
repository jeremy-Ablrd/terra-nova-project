<?php

namespace App\Enums;

/**
 * Actions tracées dans le journal d'activité. StatutDemandeModifie et CompteSupprime sont prévues mais pas encore
 * branchées : les fonctionnalités correspondantes (changement de statut côté agent, suppression de compte par l'admin)
 * n'existent pas encore.
 */
enum ActionJournal: string
{
    case RoleModifie = 'role_modifie';
    case AlertePubliee = 'alerte_publiee';
    case AlerteTerminee = 'alerte_terminee';
    case AlerteAnnulee = 'alerte_annulee';
    case ServiceModifie = 'service_modifie';
    case SynchronisationLancee = 'synchronisation_lancee';
    case StatutDemandeModifie = 'statut_demande_modifie';
    case CompteSupprime = 'compte_supprime';
    case ProjetCree = 'projet_cree';
    case ProjetModifie = 'projet_modifie';
    case ContributionTraitee = 'contribution_traitee';

    public function label(): string
    {
        return match ($this) {
            self::RoleModifie => __('Rôle modifié'),
            self::AlertePubliee => __('Alerte publiée'),
            self::AlerteTerminee => __('Alerte terminée'),
            self::AlerteAnnulee => __('Alerte annulée'),
            self::ServiceModifie => __('Service modifié'),
            self::SynchronisationLancee => __('Synchronisation lancée'),
            self::StatutDemandeModifie => __('Statut de demande modifié'),
            self::CompteSupprime => __('Compte supprimé'),
            self::ProjetCree => __('Projet créé'),
            self::ProjetModifie => __('Projet modifié'),
            self::ContributionTraitee => __('Contribution traitée'),
        };
    }

    /** Type et libellé de l'objet quand l'action n'a pas d'objet précis (ex. une synchronisation). */
    public function objetParDefaut(): array
    {
        return match ($this) {
            self::SynchronisationLancee => ['synchronisation', 'Synchronisation des demandes'],
            default => ['plateforme', 'Plateforme'],
        };
    }
}
