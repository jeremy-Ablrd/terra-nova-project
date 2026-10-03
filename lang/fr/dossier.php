<?php

// Textes des documents remis à l'habitant (« Mon dossier », « Récapitulatif de mes demandes »). Fichier unique :
// le sens de chaque statut, les phrases de la chronologie et les phrases de synthèse ne sont écrits nulle part ailleurs.
return [
    'roles' => [
        'citoyen' => 'Vous êtes habitant : vous pouvez déposer des demandes auprès de la ville et suivre leur avancement.',
        'agent' => 'Vous êtes agent de la ville : vous traitez les demandes des habitants.',
        'admin' => 'Vous êtes administrateur : vous gérez la plateforme, sans accès aux demandes des habitants.',
    ],

    // « Ce que cela signifie pour vous » : le sens de chaque statut.
    'statuts' => [
        'nouvelle' => 'Votre demande est enregistrée, aucun agent ne l\'a encore prise en charge.',
        'en_cours' => 'Un agent s\'en occupe.',
        'traitee' => 'Le dossier est clos.',
    ],

    // « Étape actuelle » (version tableur).
    'etapes' => [
        'nouvelle' => 'Enregistrée',
        'en_cours' => 'Prise en charge',
        'traitee' => 'Traitée',
    ],

    // Chronologie en phrases : le nom de l'agent n'est jamais communiqué.
    'chronologie' => [
        'nouvelle' => ':date : demande enregistrée',
        'en_cours' => ':date : prise en charge par un agent',
        'traitee' => ':date : demande traitée, le dossier est clos',
    ],

    'synthese' => [
        'aucune' => 'Vous n\'avez encore aucune demande.',
        'total' => '{1} Vous avez déposé :count demande|[2,*] Vous avez déposé :count demandes',
        'nouvelles' => '{0} aucune nouvelle|{1} :count nouvelle|[2,*] :count nouvelles',
        'en_cours' => '{0} aucune en cours|[1,*] :count en cours',
        'traitees' => '{0} aucune traitée|{1} :count traitée|[2,*] :count traitées',
        'attente' => '{0} Votre plus ancienne demande en attente a été déposée aujourd\'hui.|{1} Votre plus ancienne demande en attente a été déposée il y a :count jour.|[2,*] Votre plus ancienne demande en attente a été déposée il y a :count jours.',
        'aucune_attente' => 'Aucune de vos demandes n\'attend d\'être prise en charge.',
        'delai' => 'Les demandes traitées l\'ont été en moyenne en :jours jours.',
        'aucun_delai' => 'Aucune demande n\'est encore traitée : le délai moyen de traitement sera calculé dès la première.',
        'activite' => 'Dernière activité sur vos demandes : :date.',
        'non_lus' => '{0} Vous n\'avez aucun changement d\'état non lu.|{1} Vous avez :count changement d\'état non lu.|[2,*] Vous avez :count changements d\'état non lus.',
    ],
];
