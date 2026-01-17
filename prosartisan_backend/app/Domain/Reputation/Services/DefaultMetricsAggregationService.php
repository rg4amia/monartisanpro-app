<?php

namespace App\Domain\Reputation\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Reputation\Models\ValueObjects\ReputationMetrics;
use Illuminate\Support\Facades\DB;

/**
 * Default implementation of metrics aggregation service
 * Queries across different bounded contexts to aggregate reputation metrics
 */
class DefaultMetricsAggregationService implements MetricsAggregationService
{
 public function aggregateMetrics(UserId $artisanId): ReputationMetrics
 {
  $completedProjects = $this->getCompletedProjectsCount($artisanId);
  $acceptedProjects = $this->getAcceptedProjectsCount($artisanId);
  $averageRating = $this->getAverageRating($artisanId);
  $averageResponseTime = $this->getAverageResponseTime($artisanId);
  $fraudAttempts = $this->getFraudAttemptsCount($artisanId);

  // Calculate component scores
  $reliabilityScore = $this->calculateReliabilityScore($completedProjects, $acceptedProjects);
  $integrityScore = $this->calculateIntegrityScore($fraudAttempts);
  $qualityScore = $this->calculateQualityScore($averageRating);
  $reactivityScore = $this->calculateReactivityScore($averageResponseTime);

  return new ReputationMetrics(
   $reliabilityScore,
   $integrityScore,
   $qualityScore,
   $reactivityScore,
   $completedProjects,
   $acceptedProjects,
   $averageRating,
   $averageResponseTime,
   $fraudAttempts
  );
 }

 public function getCompletedProjectsCount(UserId $artisanId): int
 {
  // Count chantiers that are completed
  return DB::table('chantiers')
   ->where('artisan_id', $artisanId->getValue())
   ->where('status', 'COMPLETED')
   ->count();
 }

 public function getAcceptedProjectsCount(UserId $artisanId): int
 {
  // Count devis that were accepted by this artisan
  return DB::table('devis')
   ->where('artisan_id', $artisanId->getValue())
   ->where('status', 'ACCEPTED')
   ->count();
 }

 public function getAverageRating(UserId $artisanId): float
 {
  // Get average rating from ratings table (will be created in migrations)
  $result = DB::table('ratings')
   ->where('artisan_id', $artisanId->getValue())
   ->avg('rating');

  return $result ? (float) $result : 0.0;
 }

 public function getAverageResponseTime(UserId $artisanId): float
 {
  // Calculate average response time from mission notifications to devis submission
  // This is a simplified calculation - in practice, you'd track notification timestamps
  $result = DB::table('devis as d')
   ->join('missions as m', 'd.mission_id', '=', 'm.id')
   ->where('d.artisan_id', $artisanId->getValue())
   ->selectRaw('AVG(EXTRACT(EPOCH FROM (d.created_at - m.created_at)) / 3600) as avg_hours')
   ->value('avg_hours');

  return $result ? (float) $result : 0.0;
 }

 public function getFraudAttemptsCount(UserId $artisanId): int
 {
  // Count fraud attempts from various sources
  // This could include failed jeton validations, suspicious activities, etc.
  $jetonFraudAttempts = DB::table('jeton_validations')
   ->where('artisan_id', $artisanId->getValue())
   ->where('validation_result', 'FRAUD_DETECTED')
   ->count();

  // Add other fraud detection sources as needed
  return $jetonFraudAttempts;
 }

 private function calculateReliabilityScore(int $completed, int $accepted): float
 {
  if ($accepted === 0) {
   return 0.0;
  }

  return min(100, ($completed / $accepted) * 100);
 }

 private function calculateIntegrityScore(int $fraudAttempts): float
 {
  $penalty = min($fraudAttempts * 10, 50); // 10 points per fraud, max 50 penalty
  return max(0, 100 - $penalty);
 }

 private function calculateQualityScore(float $averageRating): float
 {
  if ($averageRating <= 0) {
   return 0.0;
  }

  return min(100, ($averageRating / 5.0) * 100);
 }

 private function calculateReactivityScore(float $avgResponseHours): float
 {
  if ($avgResponseHours <= 0) {
   return 100.0;
  }

  if ($avgResponseHours >= 48) {
   return 0.0;
  }

  if ($avgResponseHours <= 2) {
   return 100.0;
  }

  // Linear decay from 2 hours to 48 hours
  return max(0, 100 - (($avgResponseHours - 2) / 46) * 100);
 }
}
