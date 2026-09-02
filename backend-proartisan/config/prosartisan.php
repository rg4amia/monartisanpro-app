<?php

return [
    'gps' => [
        'jcode_max_distance' => env('GPS_JCODE_MAX_DISTANCE', 100),   // mètres
        'artisan_blur_radius' => env('GPS_ARTISAN_BLUR', 50),          // mètres
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
        'ttl' => 5, // minutes
    ],

    'score_prosartisan' => [
        // Points maximum de chaque pilier sur l'échelle 0–1000 du Score ProsArtisan
        // (cf. ScoreService::recalculateFromLedger).
        'weights' => [
            'fiabilite' => 400,
            'integrite' => 300,
            'qualite' => 200,
            'reactivite' => 100,
        ],
        // Score minimum requis pour l'accès au micro-crédit d'urgence (échelle 0–1000).
        'credit_threshold' => env('SCORE_CREDIT_THRESHOLD', 700),
        // Seuil des scores d'excellence (> 800) exigeant maturité + 5 étoiles sur ≥ 3 critères.
        'excellence_threshold' => env('SCORE_EXCELLENCE_THRESHOLD', 800),
        // Score à partir duquel l'artisan est affiché avec le « marqueur doré » (artisan prioritaire).
        'golden_marker_threshold' => env('SCORE_GOLDEN_MARKER_THRESHOLD', 700),
    ],

    'jcode' => [
        'prefix' => 'PA-',
        'ttl_hours' => 48,
    ],

    'jalon' => [
        // Délai (en heures) avant libération automatique si le client ne valide pas
        // Backlog Epic 9 — "Trigger B (Le Force-Pass)"
        'force_release_delay_hours' => env('JALON_FORCE_RELEASE_HOURS', 72),
    ],
];
