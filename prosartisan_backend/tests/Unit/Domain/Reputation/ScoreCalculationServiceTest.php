<?php

namespace Tests\Unit\Domain\Reputation;

use App\Domain\Reputation\Models\ValueObjects\ReputationMetrics;
use App\Domain\Reputation\Services\DefaultScoreCalculationService;
use PHPUnit\Framework\TestCase;

class ScoreCalculationServiceTest extends TestCase
{
 private DefaultScoreCalculationService $service;

 protected function setUp(): void
 {
  parent::setUp();
  $this->service = new DefaultScoreCalculationService();
 }

 public function test_calculate_reliability_score()
 {
  // Perfect reliability: 10 completed out of 10 accepted
  $reliability = $this->service->calculateReliability(10, 10);
  $this->assertEquals(100.0, $reliability);

  // 50% reliability: 5 completed out of 10 accepted
  $reliability = $this->service->calculateReliability(5, 10);
  $this->assertEquals(50.0, $reliability);

  // No accepted projects
  $reliability = $this->service->calculateReliability(0, 0);
  $this->assertEquals(0.0, $reliability);
 }

 public function test_calculate_integrity_score()
 {
  // No fraud attempts
  $integrity = $this->service->calculateIntegrity(0);
  $this->assertEquals(100.0, $integrity);

  // 2 fraud attempts = 20 points penalty
  $integrity = $this->service->calculateIntegrity(2);
  $this->assertEquals(80.0, $integrity);

  // 10 fraud attempts = 50 points penalty (max)
  $integrity = $this->service->calculateIntegrity(10);
  $this->assertEquals(50.0, $integrity);
 }

 public function test_calculate_quality_score()
 {
  // Perfect 5-star rating
  $quality = $this->service->calculateQuality(5.0);
  $this->assertEquals(100.0, $quality);

  // 3.5-star rating
  $quality = $this->service->calculateQuality(3.5);
  $this->assertEquals(70.0, $quality);

  // No ratings
  $quality = $this->service->calculateQuality(0.0);
  $this->assertEquals(0.0, $quality);
 }

 public function test_calculate_reactivity_score()
 {
  // Instant response
  $reactivity = $this->service->calculateReactivity(0.0);
  $this->assertEquals(100.0, $reactivity);

  // Optimal response time (2 hours)
  $reactivity = $this->service->calculateReactivity(2.0);
  $this->assertEquals(100.0, $reactivity);

  // Very slow response (48+ hours)
  $reactivity = $this->service->calculateReactivity(48.0);
  $this->assertEquals(0.0, $reactivity);
 }

 public function test_calculate_overall_score()
 {
  $metrics = new ReputationMetrics(
   100.0, // reliability
   100.0, // integrity
   100.0, // quality
   100.0, // reactivity
   10,    // completed projects
   10,    // accepted projects
   5.0,   // average rating
   2.0,   // average response time
   0      // fraud attempts
  );

  $score = $this->service->calculateScore($metrics);
  $this->assertEquals(100, $score->getValue());
 }
}
