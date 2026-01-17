<?php

namespace Tests\Unit\Domain\Reputation;

use App\Domain\Reputation\Models\ValueObjects\NZassaScore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NZassaScoreTest extends TestCase
{
 public function test_can_create_valid_score()
 {
  $score = NZassaScore::fromInt(75);

  $this->assertEquals(75, $score->getValue());
 }

 public function test_score_below_zero_throws_exception()
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage("N'Zassa score must be between 0 and 100, got: -1");

  NZassaScore::fromInt(-1);
 }

 public function test_score_above_100_throws_exception()
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage("N'Zassa score must be between 0 and 100, got: 101");

  NZassaScore::fromInt(101);
 }

 public function test_credit_eligibility()
 {
  $lowScore = NZassaScore::fromInt(50);
  $highScore = NZassaScore::fromInt(80);

  $this->assertFalse($lowScore->isEligibleForCredit());
  $this->assertTrue($highScore->isEligibleForCredit());
 }

 public function test_score_comparison()
 {
  $score1 = NZassaScore::fromInt(60);
  $score2 = NZassaScore::fromInt(80);
  $score3 = NZassaScore::fromInt(60);

  $this->assertTrue($score2->isGreaterThan($score1));
  $this->assertTrue($score1->isLessThan($score2));
  $this->assertTrue($score1->equals($score3));
 }
}
