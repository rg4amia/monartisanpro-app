<?php

return [
    // Domaine du site vitrine : la racine du backend y redirige (routes/web.php).
    'front_url' => env('FRONT_URL', 'https://www.prosartisan.net'),

    'gps' => [
        'jcode_max_distance' => env('GPS_JCODE_MAX_DISTANCE', 100),   // mètres
        'artisan_blur_radius' => env('GPS_ARTISAN_BLUR', 50),          // mètres
        'nearby_artisan_radius' => env('ARTISAN_NEARBY_RADIUS', 2000),   // mètres
        'matching_tiers' => [
            [
                'id' => 'immediate',
                'radius' => (int) env('ARTISAN_TIER_IMMEDIATE', 2000),
                'label' => 'Proximité immédiate (2 km)',
            ],
            [
                'id' => 'local',
                'radius' => (int) env('ARTISAN_TIER_LOCAL', 5000),
                'label' => 'Zone locale (5 km)',
            ],
            [
                'id' => 'city',
                'radius' => (int) env('ARTISAN_TIER_CITY', 15000),
                'label' => 'Grand Abidjan (15 km)',
            ],
            [
                'id' => 'extended',
                'radius' => (int) env('ARTISAN_TIER_EXTENDED', 50000),
                'label' => 'Zone élargie (50 km)',
            ],
        ],
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
        // Tentatives de vérification autorisées par code. Au-delà, le code est
        // brûlé et il faut en redemander un : c'est ce qui rend la force brute
        // inopérante sur un code court, indépendamment de l'IP de l'attaquant.
        // Porter 'length' à 6 renforce encore la marge, au prix d'un
        // changement d'UX côté mobile (champ de saisie).
        'max_attempts' => 5,
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
        // Taux de prélèvement automatique d'amortissement du micro-crédit sur les jalons libérés (20% par défaut).
        'credit_repayment_rate' => env('SCORE_CREDIT_REPAYMENT_RATE', 0.20),
    ],

    'jcode' => [
        'prefix' => 'PA-',
        'ttl_hours' => 48,
    ],

    // Super administrateurs protégés : accès total permanent au backoffice,
    // non modifiable depuis l'onglet « Rôles & Actions » (garde-fou anti-verrouillage).
    'super_admins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPER_ADMIN_EMAILS', 'admin@prosartisan.ci')),
    ))),

    'delivery' => [
        'in_transit_timeout_minutes' => (int) env('DRIVER_IN_TRANSIT_TIMEOUT_MINUTES', 25),
        'in_transit_alert_cooldown_minutes' => (int) env('DRIVER_IN_TRANSIT_ALERT_COOLDOWN_MINUTES', 30),
    ],

    'jalon' => [
        // Délai (en heures) avant libération automatique si le client ne valide pas
        // Backlog Epic 9 — "Trigger B (Le Force-Pass)"
        'force_release_delay_hours' => env('JALON_FORCE_RELEASE_HOURS', 72),
    ],

    // Vérification KYC par IA (OCR de la pièce + biométrie faciale Gemini).
    // L'auto-approbation n'est qu'un raccourci : tout dossier qui ne franchit
    // pas l'ensemble des contrôles reste en revue humaine au backoffice.
    'kyc' => [
        // Interrupteur général de l'auto-approbation (l'analyse IA reste menée).
        'auto_approval_enabled' => (bool) env('KYC_AUTO_APPROVAL_ENABLED', true),
        // Rôles éligibles. Le fournisseur en est exclu : son agrément suit une
        // revue humaine (CNMCI, quincaillerie) ; référents et admins aussi.
        'auto_approval_roles' => ['client', 'artisan', 'livreur'],
        // Score biométrique global minimum (0 à 100), plafonné par le plus
        // faible des scores de similarité et de vivacité.
        'auto_approval_threshold' => (int) env('KYC_AUTO_APPROVAL_THRESHOLD', 85),
        // Sous ce score (0 à 100), le backoffice signale un dossier à risque élevé ;
        // entre ce seuil et auto_approval_threshold, une revue humaine est conseillée.
        'review_threshold' => (int) env('KYC_REVIEW_THRESHOLD', 50),
        // Score de qualité OCR minimum requis pour la pièce d'identité (0 à 100).
        'ocr_min_quality' => (int) env('KYC_OCR_MIN_QUALITY', 70),
    ],
];
