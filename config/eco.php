<?php

/**
 * Budget de poids par page courante (éco-conception, F57 à F60). Mêmes valeurs que dans CLAUDE.md :
 * la page /eco-conception les lit ici, aucun chiffre n'est écrit à la main dans la vue.
 */
return [
    'budget' => [
        'octets' => 300 * 1024, // transférés, page chargée à froid
        'requetes' => 15,
    ],
];
