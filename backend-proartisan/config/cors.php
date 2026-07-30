<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    // Charger les origines autorisées depuis le fichier .env, avec des fallbacks sécurisés.
    // Pas de wildcard '*' en production si les cookies/tokens d'authentification sont supportés.
    'allowed_origins' => array_filter(explode(',', env('ALLOWED_ORIGINS', 'https://prosartisan.ci,https://admin.prosartisan.ci,https://prosartisan.net,https://admin.prosartisan.net'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
