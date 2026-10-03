<?php

// Horaires d'ouverture des services (F74). Nom volontairement différent de « horaires » ou « services » : sous Windows,
// __('Horaires') chargerait un fichier horaires.php (système de fichiers insensible à la casse).
return [
    'jours' => [
        'lundi' => 'lundi', 'mardi' => 'mardi', 'mercredi' => 'mercredi', 'jeudi' => 'jeudi',
        'vendredi' => 'vendredi', 'samedi' => 'samedi', 'dimanche' => 'dimanche',
    ],
    'jours_titre' => [
        'lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi', 'jeudi' => 'Jeudi',
        'vendredi' => 'Vendredi', 'samedi' => 'Samedi', 'dimanche' => 'Dimanche',
    ],
    'etat' => [
        'ouvert' => 'Ouvert maintenant, jusqu\'à :heure',
        'ouvre_aujourdhui' => 'Fermé pour le moment, ouvre aujourd\'hui à :heure',
        'pause' => 'Fermé pour le moment (pause), rouvre aujourd\'hui à :heure',
        'rouvre_demain' => 'Fermé pour le moment, rouvre demain à :heure',
        'ferme_aujourdhui_demain' => 'Fermé aujourd\'hui, rouvre demain à :heure',
        'rouvre_jour' => 'Fermé pour le moment, rouvre :jour à :heure',
        'ferme_aujourdhui_jour' => 'Fermé aujourd\'hui, rouvre :jour à :heure',
        'jamais' => 'Fermé : aucun horaire d\'ouverture renseigné',
    ],
    'ferme' => 'Fermé',
    'aujourdhui' => 'aujourd\'hui',
];
