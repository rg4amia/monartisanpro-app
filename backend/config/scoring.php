<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Score Component Weights
    |--------------------------------------------------------------------------
    |
    | Define the weight of each component in the total score calculation.
    | The sum of all weights must equal 100.
    |
    */

    'weights' => [
        'reliability' => 40,      // Fiabilité (40%)
        'integrity' => 30,        // Intégrité (30%)
        'quality' => 15,          // Qualité (15%)
        'responsiveness' => 10,   // Réactivité (10%)
        'professionalism' => 5,   // Professionnalisme (5%)
    ],

    /*
    |--------------------------------------------------------------------------
    | Badge Thresholds
    |--------------------------------------------------------------------------
    |
    | Define the minimum score and project count required for each badge level.
    |
    */

    'badge_thresholds' => [
        'gold' => [
            'score' => 80,
            'projects' => 20,
        ],
        'silver' => [
            'score' => 65,
            'projects' => 10,
        ],
        'bronze' => [
            'score' => 50,
            'projects' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Score Penalties & Bonuses
    |--------------------------------------------------------------------------
    |
    | Define penalties and bonuses for specific events.
    |
    */

    'penalties' => [
        'project_cancelled_by_artisan' => -5,
        'late_delivery' => -3,
        'dispute_lost' => -10,
        'poor_review' => -2,
    ],

    'bonuses' => [
        'project_completed_on_time' => 2,
        'excellent_review' => 3,
        'dispute_won' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recalculation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for automatic score recalculation.
    |
    */

    'recalculation' => [
        'frequency' => 'daily', // daily, weekly, monthly
        'enabled' => true,
    ],
];
