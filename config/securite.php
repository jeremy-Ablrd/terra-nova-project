<?php

/**
 * Réglages de sécurité modifiables dans le .env, sans redéployer le code (F37, F69).
 * Si la configuration est en cache sur le serveur, lancer `php artisan config:clear` après une modification.
 */
return [
    // Content-Security-Policy : « enforce » (par défaut), « report-only » (le navigateur signale sans bloquer) ou « off ».
    'csp_mode' => env('NOVATERRA_CSP_MODE', 'enforce'),

    // Limite de tentatives de connexion par adresse IP seule. À désactiver si l'IP détectée est celle d'un proxy.
    'limite_ip' => filter_var(env('NOVATERRA_LIMITE_IP', true), FILTER_VALIDATE_BOOLEAN),

    // Protection des formulaires contre les envois automatiques et les doublons (F81, F82) : champ leurre invisible, jeton
    // signé avec délai minimal, jeton à usage unique, limite de débit. Pas de CAPTCHA. Désactivée dans phpunit.xml ; à n'éteindre
    // en production qu'en cas de problème (puis `php artisan config:clear` si la configuration est en cache).
    'protection_formulaires' => filter_var(env('NOVATERRA_PROTECTION_FORMULAIRES', true), FILTER_VALIDATE_BOOLEAN),
    'formulaires' => [
        'delai_minimal' => 2,            // secondes entre l'affichage et l'envoi
        'validite_minutes' => 180,       // au-delà, le formulaire est à recharger
        'limite' => 30,                  // envois par adresse IP et par fenêtre, tous formulaires protégés confondus
        'fenetre_minutes' => 10,
        'champ_leurre' => 'note_interne_zq', // ni adresse, ni e-mail, ni téléphone, ni nom : rien que les navigateurs remplissent seuls
    ],

    // Proxys de confiance (adresses séparées par des virgules, ou « * »). Vide : aucun proxy n'est approuvé.
    'trusted_proxies' => env('TRUSTED_PROXIES'),
];
