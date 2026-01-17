<?php

namespace App\Domain\Reputation\Models\ValueObjects;

/**
 * Value object representing reputation metrics used for score calculation
 */
final class ReputationMetrics
{
    private float $reliabilityScore; // 40% weight
    private float $integrityScore; // 30% weight
    private float $qualityScore; // 20% weight
    private float $reactivityScore; // 10% weight
    private int $completedProjects;
    private int $acceptedProjects;
    private float $averageRating;
    private float $averageResponseTimeHours;
    private int $fraudAttempts;

    public function __construct(
        float $reliabilityScore,
        float $integrityScore,
        float $qualityScore,
        float $reactivityScore,
        int $completedProjects,
        int $acceptedProjects,
        float $averageRating,
        float $averageResponseTimeHours,
        int $fraudAttempts
    ) {
        $this->reliabilityScore = max(0, min(100, $reliabilityScore));
        $this->integrityScore = max(0, min(100, $integrityScore));
        $this->qualityScore = max(0, min(100, $qualityScore));
        $this->reactivityScore = max(0, min(100, $reactivityScore));
        $this->completedProjects = max(0, $completedProjects);
        $this->acceptedProjects = max(0, $acceptedProjects);
        $this->averageRating = max(0, min(5, $averageRating));
        $this->averageResponseTimeHours = max(0, $averageResponseTimeHours);
        $this->fraudAttempts = max(0, $fraudAttempts);
    }

    public static function empty(): self
    {
        return new self(0, 100, 0, 0, 0, 0, 0, 0, 0);
    }

    public function getReliabilityScore(): float
    {
        return $this->reliabilityScore;
    }

    public function getIntegrityScore(): float
    {
        return $this->integrityScore;
    }

    public function getQualityScore(): float
    {
        return $this->qualityScore;
    }

    public function getReactivityScore(): float
    {
        return $this->reactivityScore;
    }

    public function getCompletedProjects(): int
    {
        return $this->completedProjects;
    }

    public function getAcceptedProjects(): int
    {
        return $this->acceptedProjects;
    }

    public function getAverageRating(): float
    {
        return $this->averageRating;
    }

    public function getAverageResponseTimeHours(): float
    {
        return $this->averageResponseTimeHours;
    }

    public function getFraudAttempts(): int
    {
        return $this->fraudAttempts;
    }
}
