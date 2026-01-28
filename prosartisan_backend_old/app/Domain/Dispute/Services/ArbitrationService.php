<?php

namespace App\Domain\Dispute\Services;

use App\Domain\Dispute\Models\Litige\Litige;
use App\Domain\Dispute\Models\ValueObjects\ArbitrationDecision;
use App\Domain\Financial\Models\ValueObjects\SequestreId;

/**
 * Service interface for arbitration management
 *
 * Requirements: 9.4, 9.6
 */
interface ArbitrationService
{
    /**
     * Render an arbitration decision for a dispute
     *
     * Requirement 9.6: Execute arbitration decision
     */
    public function renderDecision(Litige $litige): ArbitrationDecision;

    /**
     * Execute an arbitration decision on the sequestre
     *
     * Requirement 9.6: Execute decision (refund, payment, or fund freeze)
     */
    public function executeDecision(ArbitrationDecision $decision, SequestreId $sequestreId): void;

    /**
     * Check if a dispute can be escalated to arbitration
     */
    public function canEscalateToArbitration(Litige $litige): bool;

    /**
     * Get recommended decision based on dispute analysis
     */
    public function getRecommendedDecision(Litige $litige): ArbitrationDecision;
}
