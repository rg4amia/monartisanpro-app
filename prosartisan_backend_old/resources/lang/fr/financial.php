<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Financial Language Lines
    |--------------------------------------------------------------------------
    */

    'escrow_blocked' => 'Fonds bloqués en séquestre avec succès.',
    'escrow_fragmented' => 'Séquestre fragmenté : :materials_percent% matériaux, :labor_percent% main-d\'œuvre.',
    'jeton_generated' => 'Jeton matériel généré : :code',
    'jeton_validated' => 'Jeton matériel validé avec succès.',
    'jeton_expired' => 'Ce jeton matériel a expiré.',
    'jeton_not_found' => 'Jeton matériel non trouvé.',
    'jeton_insufficient_funds' => 'Fonds insuffisants sur le jeton.',
    'jeton_proximity_failed' => 'Validation échouée : artisan et fournisseur doivent être à moins de 100m.',
    'labor_payment_released' => 'Paiement main-d\'œuvre libéré : :amount FCFA',
    'material_payment_released' => 'Paiement matériaux libéré : :amount FCFA',
    'payment_failed' => 'Échec du paiement. Veuillez réessayer.',
    'payment_processing' => 'Paiement en cours de traitement...',
    'payment_confirmed' => 'Paiement confirmé avec succès.',
    'refund_processed' => 'Remboursement traité avec succès.',
    'transaction_recorded' => 'Transaction enregistrée dans l\'historique.',

    'mobile_money' => [
        'wave' => 'Wave',
        'orange' => 'Orange Money',
        'mtn' => 'MTN Mobile Money',
        'provider_unavailable' => 'Fournisseur de paiement mobile indisponible.',
        'webhook_received' => 'Confirmation de paiement reçue.',
        'webhook_timeout' => 'Délai d\'attente de confirmation dépassé.',
    ],

    'transaction_types' => [
        'escrow_block' => 'Blocage séquestre',
        'material_release' => 'Libération matériaux',
        'labor_release' => 'Libération main-d\'œuvre',
        'refund' => 'Remboursement',
        'service_fee' => 'Frais de service',
    ],
];
