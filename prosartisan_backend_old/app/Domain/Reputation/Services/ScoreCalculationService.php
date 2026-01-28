<?php

namespace App\Domain\Reputation\Services;

use App\Domain\Reputation\Models\ValueObjects\NZassaScore;
use App\Domain\Reputation\Models\ValueObjects\ReputationMetrics;

/**
 * Interface for score calculation service
 */
interface ScoreCalculationService
{
    /**
     * Calculate the N'Zassa score based on reputation metrics
     */
    public function calculateScore(ReputationMetrics $metrics): NZassaScore;

    /**
     * Calculate reliability score: (completed / accepted) * 100
     */
    public function calculateReliability(int $completed, int $accepted): float;

    /**
     * Calculate integrity score with fraud penalties
     */
    public function calculateIntegrity(int $fraudAttempts): float;

    /**
     * Calculate quality score: (average rating / 5) * 100
     */
    public function calculateQuality(float $averageRating): float;

    /**
     * Calculate reactivity score: inverse of response time
     */
    public function calculateReactivity(float $avgResponseHours): float;
}
