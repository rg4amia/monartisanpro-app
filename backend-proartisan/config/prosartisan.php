<?php

return [
    'gps' => [
        'jcode_max_distance'    => env('GPS_JCODE_MAX_DISTANCE', 100),   // mètres
        'artisan_blur_radius'   => env('GPS_ARTISAN_BLUR', 50),          // mètres
        'nearby_artisan_radius' => env('ARTISAN_NEARBY_RADIUS', 2000),   // mètres
    ],

    'mission' => [
        'referent_threshold' => env('REFERENT_THRESHOLD', 2000000), // FCFA
    ],

    'night_mode' => [
        'surge_multiplier' => env('NIGHT_SURGE_MULTIPLIER', 1.5),
    ],

    'otp' => [
        'length' => 4,
        'ttl'    => 5, // minutes
    ],

    'score_nzassa' => [
        'weights' => [
            'fiabilite'  => 40,
            'integrite'  => 30,
            'qualite'    => 20,
            'reactivite' => 10,
        ],
        'credit_threshold' => 70,
    ],

    'jcode' => [
        'prefix'    => 'PA-',
        'ttl_hours' => 48,
    ],

    'jalon' => [
        // Délai (en heures) avant libération automatique si le client ne valide pas
        // Backlog Epic 9 — "Trigger B (Le Force-Pass)"
        'force_release_delay_hours' => env('JALON_FORCE_RELEASE_HOURS', 72),
    ],
];
