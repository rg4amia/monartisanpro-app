<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Disputes Language Lines
    |--------------------------------------------------------------------------
    */

    'dispute_reported' => 'Litige signalé avec succès.',
    'dispute_resolved' => 'Litige résolu avec succès.',
    'mediation_started' => 'Médiation démarrée.',
    'arbitration_rendered' => 'Décision d\'arbitrage rendue.',
    'funds_frozen' => 'Fonds gelés en raison du litige.',
    'funds_released' => 'Fonds libérés suite à la résolution.',
    'mediator_assigned' => 'Médiateur assigné au litige.',
    'evidence_uploaded' => 'Preuve téléchargée avec succès.',
    'communication_sent' => 'Message de médiation envoyé.',
    'dispute_not_found' => 'Litige non trouvé.',
    'dispute_already_resolved' => 'Ce litige a déjà été résolu.',
    'reporting_deadline_passed' => 'Délai de signalement dépassé (7 jours après validation finale).',
    'high_value_dispute' => 'Litige de haute valeur (> 2 000 000 FCFA) - Référent de zone assigné.',

    'types' => [
        'quality' => 'Problème de qualité',
        'payment' => 'Problème de paiement',
        'delay' => 'Retard de livraison',
        'other' => 'Autre',
    ],

    'status' => [
        'open' => 'Ouvert',
        'in_mediation' => 'En médiation',
        'in_arbitration' => 'En arbitrage',
        'resolved' => 'Résolu',
        'closed' => 'Fermé',
    ],

    'decisions' => [
        'refund_client' => 'Remboursement client',
        'pay_artisan' => 'Paiement artisan',
        'partial_refund' => 'Remboursement partiel',
        'freeze_funds' => 'Gel des fonds',
    ],
];
