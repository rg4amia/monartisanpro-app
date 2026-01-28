<?php

namespace App\Domain\Reputation\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Reputation\Models\ValueObjects\ReputationMetrics;

/**
 * Interface for metrics aggregation service
 */
interface MetricsAggregationService
{
    /**
     * Aggregate all metrics for an artisan
     */
    public function aggregateMetrics(UserId $artisanId): ReputationMetrics;

    /**
     * Get count of completed projects for an artisan
     */
    public function getCompletedProjectsCount(UserId $artisanId): int;

    /**
     * Get count of accepted projects for an artisan
     */
    public function getAcceptedProjectsCount(UserId $artisanId): int;

    /**
     * Get average rating for an artisan
     */
    public function getAverageRating(UserId $artisanId): float;

    /**
     * Get average response time in hours for an artisan
     */
    public function getAverageResponseTime(UserId $artisanId): float;

    /**
     * Get count of fraud attempts for an artisan
     */
    public function getFraudAttemptsCount(UserId $artisanId): int;
}
