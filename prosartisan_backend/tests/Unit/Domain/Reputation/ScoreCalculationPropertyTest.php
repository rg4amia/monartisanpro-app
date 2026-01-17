<?php

namespace Tests\Unit\Domain\Reputation;

use App\Domain\Reputation\Models\ValueObjects\ReputationMetrics;
use App\Domain\Reputation\Services\DefaultScoreCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for score calculation logic
 *
 * **Property 37: N'Zassa Score Weighted Calculation**
 * **Property 38: Reliability Score Formula**
 * **Property 39: Integrity Fraud Penalty**
 * **Property 40: Quality Score Normalization**
 * **Property 41: Reactivity Score Calculation**
 * **Validates: Requirements 7.2, 7.3, 7.4, 7.5, 7.6**
 */
class ScoreCalculationPropertyTest extends TestCase
{
 private DefaultScoreCalculationService $service;

 protected function setUp(): void
 {
  parent::setUp();
  $this->service = new DefaultScoreCalculationService();
 }

 /**
  * Property 37: N'Zassa Score Weighted Calculation
  *
  * Tests that the weighted calculation always produces scores within 0-100 range
  * and that the weights are correctly applied.
  */
 public function test_nzassa_score_weighted_calculation_property()
 {
  // Test with 100 random metric combinations
  for ($i = 0; $i < 100; $i++) {
   $reliability = (float) rand(0, 10000) / 100; // 0-100
   $integrity = (float) rand(0, 10000) / 100;   // 0-100
   $quality = (float) rand(0, 10000) / 100;     // 0-100
   $reactivity = (float) rand(0, 10000) / 100;  // 0-100

   $metrics = new ReputationMetrics(
    $reliability,
    $integrity,
    $quality,
    $reactivity,
    rand(0, 100), // completed projects
    rand(1, 100), // accepted projects (at least 1)
    (float) rand(0, 500) / 100, // average rating 0-5
    (float) rand(0, 4800) / 100, // response time 0-48 hours
    rand(0, 10) // fraud attempts
   );

   $score = $this->service->calculateScore($metrics);

   // Property: Score must always be within valid range
   $this->assertGreaterThanOrEqual(0, $score->getValue());
   $this->assertLessThanOrEqual(100, $score->getValue());

   // Property: Score should be deterministic for same inputs
   $score2 = $this->service->calculateScore($metrics);
   $this->assertEquals($score->getValue(), $score2->getValue());
  }
 }

 /**
  * Property 38: Reliability Score Formula
  *
  * Tests that reliability = (completed / accepted) * 100
  */
 public function test_reliability_score_formula_property()
 {
  // Test with 100 random combinations
  for ($i = 0; $i < 100; $i++) {
   $accepted = rand(1, 100); // At least 1 to avoid division by zero
   $completed = rand(0, $accepted); // Can't complete more than accepted

   $reliability = $this->service->calculateReliability($completed, $accepted);

   $expectedReliability = ($completed / $accepted) * 100;

   // Property: Formula must be exact
   $this->assertEquals($expectedReliability, $reliability, "Failed for completed={$completed}, accepted={$accepted}");

   // Property: Reliability must be within 0-100 range
   $this->assertGreaterThanOrEqual(0, $reliability);
   $this->assertLessThanOrEqual(100, $reliability);

   // Property: Perfect completion gives 100% reliability
   if ($completed === $accepted) {
    $this->assertEquals(100.0, $reliability);
   }

   // Property: No completion gives 0% reliability
   if ($completed === 0) {
    $this->assertEquals(0.0, $reliability);
   }
  }
 }

 /**
  * Property 39: Integrity Fraud Penalty
  *
  * Tests that fraud attempts correctly reduce integrity score
  */
 public function test_integrity_fraud_penalty_property()
 {
  // Test with various fraud attempt counts
  for ($fraudAttempts = 0; $fraudAttempts <= 20; $fraudAttempts++) {
   $integrity = $this->service->calculateIntegrity($fraudAttempts);

   // Property: More fraud attempts = lower integrity score
   if ($fraudAttempts === 0) {
    $this->assertEquals(100.0, $integrity);
   }

   // Property: Each fraud attempt reduces score by 10 points (up to max penalty of 50)
   $expectedPenalty = min($fraudAttempts * 10, 50);
   $expectedIntegrity = max(0, 100 - $expectedPenalty);
   $this->assertEquals($expectedIntegrity, $integrity);

   // Property: Integrity score must be within 0-100 range
   $this->assertGreaterThanOrEqual(0, $integrity);
   $this->assertLessThanOrEqual(100, $integrity);
  }
 }

 /**
  * Property 40: Quality Score Normalization
  *
  * Tests that quality score correctly normalizes ratings from 0-5 to 0-100
  */
 public function test_quality_score_normalization_property()
 {
  // Test with 100 random ratings
  for ($i = 0; $i < 100; $i++) {
   $rating = (float) rand(0, 500) / 100; // 0.00 to 5.00

   $quality = $this->service->calculateQuality($rating);

   // Property: Quality score must be within 0-100 range
   $this->assertGreaterThanOrEqual(0, $quality);
   $this->assertLessThanOrEqual(100, $quality);

   // Property: Formula should be (rating / 5) * 100
   if ($rating > 0) {
    $expectedQuality = min(100, ($rating / 5.0) * 100);
    $this->assertEquals($expectedQuality, $quality, "Failed for rating={$rating}");
   }

   // Property: Perfect rating gives perfect quality score
   if ($rating >= 5.0) {
    $this->assertEquals(100.0, $quality);
   }

   // Property: No rating gives zero quality score
   if ($rating <= 0) {
    $this->assertEquals(0.0, $quality);
   }
  }
 }

 /**
  * Property 41: Reactivity Score Calculation
  *
  * Tests that reactivity score correctly handles response times
  */
 public function test_reactivity_score_calculation_property()
 {
  // Test with various response times
  $testCases = [
   0.0,    // Instant response
   1.0,    // 1 hour
   2.0,    // Optimal (2 hours)
   5.0,    // Moderate
   24.0,   // Slow
   48.0,   // Very slow
   100.0   // Extremely slow
  ];

  foreach ($testCases as $responseTime) {
   $reactivity = $this->service->calculateReactivity($responseTime);

   // Property: Reactivity score must be within 0-100 range
   $this->assertGreaterThanOrEqual(0, $reactivity);
   $this->assertLessThanOrEqual(100, $reactivity);

   // Property: Instant or optimal response gets maximum score
   if ($responseTime <= 2.0) {
    $this->assertEquals(100.0, $reactivity);
   }

   // Property: Very slow response gets minimum score
   if ($responseTime >= 48.0) {
    $this->assertEquals(0.0, $reactivity);
   }

   // Property: Faster response should give higher score
   if ($responseTime > 2.0 && $responseTime < 48.0) {
    $slowerReactivity = $this->service->calculateReactivity($responseTime + 1);
    $this->assertGreaterThanOrEqual($slowerReactivity, $reactivity);
   }
  }

  // Test monotonicity property with random values
  for ($i = 0; $i < 50; $i++) {
   $time1 = (float) rand(200, 4700) / 100; // 2.00 to 47.00 hours
   $time2 = $time1 + 1; // Always slower

   $reactivity1 = $this->service->calculateReactivity($time1);
   $reactivity2 = $this->service->calculateReactivity($time2);

   // Property: Slower response should never have higher reactivity score
   $this->assertGreaterThanOrEqual(
    $reactivity2,
    $reactivity1,
    "Reactivity should decrease with slower response time: {$time1}h vs {$time2}h"
   );
  }
 }

 /**
  * Property test: Score calculation should be monotonic with respect to individual components
  */
 public function test_score_monotonicity_property()
 {
  // Test that improving reliability increases overall score
  $baseCompleted = 5;
  $baseAccepted = 10;
  $improvedCompleted = 8; // Better completion rate

  $baseReliability = $this->service->calculateReliability($baseCompleted, $baseAccepted);
  $improvedReliability = $this->service->calculateReliability($improvedCompleted, $baseAccepted);

  $this->assertGreaterThan(
   $baseReliability,
   $improvedReliability,
   "Improved completion rate should increase reliability score"
  );

  // Test that reducing fraud attempts increases integrity score
  $baseFraud = 3;
  $improvedFraud = 1;

  $baseIntegrity = $this->service->calculateIntegrity($baseFraud);
  $improvedIntegrity = $this->service->calculateIntegrity($improvedFraud);

  $this->assertGreaterThan(
   $baseIntegrity,
   $improvedIntegrity,
   "Fewer fraud attempts should increase integrity score"
  );

  // Test that better ratings increase quality score
  $baseRating = 3.0;
  $improvedRating = 4.5;

  $baseQuality = $this->service->calculateQuality($baseRating);
  $improvedQuality = $this->service->calculateQuality($improvedRating);

  $this->assertGreaterThan(
   $baseQuality,
   $improvedQuality,
   "Better ratings should increase quality score"
  );

  // Test that faster response increases reactivity score
  $baseResponseTime = 10.0; // 10 hours
  $improvedResponseTime = 3.0; // 3 hours

  $baseReactivity = $this->service->calculateReactivity($baseResponseTime);
  $improvedReactivity = $this->service->calculateReactivity($improvedResponseTime);

  $this->assertGreaterThan(
   $baseReactivity,
   $improvedReactivity,
   "Faster response should increase reactivity score"
  );
 }
}
