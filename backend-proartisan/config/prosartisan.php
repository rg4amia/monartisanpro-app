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
];
