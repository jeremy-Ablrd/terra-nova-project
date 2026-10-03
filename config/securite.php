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

    // Proxys de confiance (adresses séparées par des virgules, ou « * »). Vide : aucun proxy n'est approuvé.
    'trusted_proxies' => env('TRUSTED_PROXIES'),
];
