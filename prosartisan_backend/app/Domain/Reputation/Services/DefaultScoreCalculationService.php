<?php

namespace App\Domain\Reputation\Services;

use App\Domain\Reputation\Models\ValueObjects\NZassaScore;
use App\Domain\Reputation\Models\ValueObjects\ReputationMetrics;

/**
 * Default implementation of score calculation service
 */
class DefaultScoreCalculationService implements ScoreCalculationService
{
 // Score component weights
 private const RELIABILITY_WEIGHT = 0.40; // 40%
 private const INTEGRITY_WEIGHT = 0.30;   // 30%
 private const QUALITY_WEIGHT = 0.20;     // 20%
 private const REACTIVITY_WEIGHT = 0.10;  // 10%

 // Fraud penalty constants
 private const FRAUD_PENALTY_PER_ATTEMPT = 10; // 10 points per fraud attempt
 private const MAX_FRAUD_PENALTY = 50; // Maximum 50 points penalty

 // Reactivity constants
 private const OPTIMAL_RESPONSE_TIME_HOURS = 2; // 2 hours is considered optimal
 private const MAX_RESPONSE_TIME_HOURS = 48; // Beyond 48 hours gets 0 points

 public function calculateScore(ReputationMetrics $metrics): NZassaScore
 {
  $reliabilityScore = $this->calculateReliability(
   $metrics->getCompletedProjects(),
   $metrics->getAcceptedProjects()
  );

  $integrityScore = $this->calculateIntegrity($metrics->getFraudAttempts());

  $qualityScore = $this->calculateQuality($metrics->getAverageRating());

  $reactivityScore = $this->calculateReactivity($metrics->getAverageResponseTimeHours());

  // Calculate weighted score
  $totalScore = (
   $reliabilityScore * self::RELIABILITY_WEIGHT +
   $integrityScore * self::INTEGRITY_WEIGHT +
   $qualityScore * self::QUALITY_WEIGHT +
   $reactivityScore * self::REACTIVITY_WEIGHT
  );

  // Ensure score is within bounds
  $finalScore = max(0, min(100, round($totalScore)));

  return NZassaScore::fromInt((int) $finalScore);
 }

 public function calculateReliability(int $completed, int $accepted): float
 {
  if ($accepted === 0) {
   return 0.0; // No accepted projects means 0 reliability
  }

  $reliability = ($completed / $accepted) * 100;
  return max(0, min(100, $reliability));
 }

 public function calculateIntegrity(int $fraudAttempts): float
 {
  $penalty = min($fraudAttempts * self::FRAUD_PENALTY_PER_ATTEMPT, self::MAX_FRAUD_PENALTY);
  $integrityScore = 100 - $penalty;

  return max(0, min(100, $integrityScore));
 }

 public function calculateQuality(float $averageRating): float
 {
  if ($averageRating <= 0) {
   return 0.0; // No ratings means 0 quality score
  }

  $qualityScore = ($averageRating / 5.0) * 100;
  return max(0, min(100, $qualityScore));
 }

 public function calculateReactivity(float $avgResponseHours): float
 {
  if ($avgResponseHours <= 0) {
   return 100.0; // Instant response gets maximum score
  }

  if ($avgResponseHours >= self::MAX_RESPONSE_TIME_HOURS) {
   return 0.0; // Very slow response gets 0 score
  }

  // Linear decay from optimal time to max time
  if ($avgResponseHours <= self::OPTIMAL_RESPONSE_TIME_HOURS) {
   return 100.0; // Optimal or better gets full score
  }

  // Calculate score based on how much slower than optimal
  $excessTime = $avgResponseHours - self::OPTIMAL_RESPONSE_TIME_HOURS;
  $maxExcessTime = self::MAX_RESPONSE_TIME_HOURS - self::OPTIMAL_RESPONSE_TIME_HOURS;

  $reactivityScore = 100 - (($excessTime / $maxExcessTime) * 100);

  return max(0, min(100, $reactivityScore));
 }
}
