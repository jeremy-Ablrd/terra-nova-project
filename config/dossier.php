<?php

use App\Console\Commands\PurgerSecurite;

/**
 * « Mon dossier » (F55) : ce que la ville conserve, pourquoi, combien de temps, et où le modifier ou le supprimer.
 * Source unique : la page, le fichier téléchargé et la page « Mes données » lisent ce tableau, jamais une vue.
 * Les textes sont en français (langue de base) et traduits par __() au moment de l'affichage.
 * `action` : route nommée et libellé du lien, ou null quand la donnée n'est pas modifiable par l'habitant.
 */
return [
    'conservation' => [
        [
            'donnee' => 'Votre compte : nom, adresse e-mail et mot de passe (chiffré)',
            'finalite' => 'Vous reconnaître quand vous vous connectez et vous répondre.',
            'duree' => 'Tant que votre compte existe.',
            'action' => ['route' => 'profile.edit', 'libelle' => 'Modifier mon profil'],
        ],
        [
            'donnee' => 'Vos demandes : objet, message, service, statut et dates',
            'finalite' => 'Traiter vos demandes et vous informer de leur avancement.',
            'duree' => 'Tant que votre compte existe. Après sa suppression, elles sont conservées sans votre nom ni votre texte.',
            'action' => ['route' => 'mes-donnees.suppression', 'libelle' => 'Supprimer mon compte'],
        ],
        [
            'donnee' => 'Les étapes de suivi de vos demandes (dates des changements d\'état)',
            'finalite' => 'Vous montrer où en est chaque demande et vous prévenir des changements.',
            'duree' => 'Comme les demandes : conservées sous forme anonyme après la suppression du compte.',
            'action' => null,
        ],
        [
            'donnee' => 'Vos contributions : avis sur un projet, idées et commentaires sur un service, avec leur référence, leur statut et la réponse de la ville',
            'finalite' => 'Les lire, vous répondre et vous montrer ce que la ville en a fait.',
            'duree' => 'Tant que votre compte existe. Après sa suppression, elles sont conservées sans votre nom ni votre texte.',
            'action' => ['route' => 'mes-contributions.index', 'libelle' => 'Voir mes contributions'],
        ],
        [
            'donnee' => 'Vos préférences d\'affichage : taille du texte et thème',
            'finalite' => 'Retrouver votre affichage à chaque visite.',
            'duree' => 'Tant que votre compte existe.',
            'action' => ['route' => 'accessibilite', 'libelle' => 'Régler mon affichage'],
        ],
        [
            'donnee' => 'Les appareils qui ont utilisé votre compte',
            'finalite' => 'Vous prévenir quand quelqu\'un se connecte depuis un nouvel appareil.',
            'duree' => 'Tant que votre compte existe, ou jusqu\'à ce que vous oubliez l\'appareil.',
            'action' => ['route' => 'mes-connexions.index', 'libelle' => 'Gérer mes connexions'],
        ],
        [
            'donnee' => 'Les traces de sécurité : échecs de connexion et alertes (adresse e-mail masquée)',
            'finalite' => 'Protéger les comptes contre les tentatives d\'intrusion.',
            'duree' => PurgerSecurite::JOURS.' jours, puis effacées automatiquement.',
            'action' => null,
        ],
    ],
];
