<?php

namespace Tests\Unit\Domain\Reputation;

use App\Domain\Reputation\Models\ValueObjects\NZassaScore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for NZassaScore value object
 *
 * **Validates: Requirements 7.2**
 */
class NZassaScorePropertyTest extends TestCase
{
    /**
     * Property test: NZassaScore constructor should reject values outside 0-100 range
     *
     * This property tests that the NZassaScore value object correctly validates
     * its input range and throws appropriate exceptions for invalid values.
     */
    public function test_score_range_validation_property()
    {
        // Test with 100 random invalid values below 0
        for ($i = 0; $i < 50; $i++) {
            $invalidLowValue = rand(-1000, -1);

            try {
                NZassaScore::fromInt($invalidLowValue);
                $this->fail("Expected InvalidArgumentException for value: {$invalidLowValue}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("N'Zassa score must be between 0 and 100", $e->getMessage());
                $this->assertStringContainsString((string) $invalidLowValue, $e->getMessage());
            }
        }

        // Test with 100 random invalid values above 100
        for ($i = 0; $i < 50; $i++) {
            $invalidHighValue = rand(101, 1000);

            try {
                NZassaScore::fromInt($invalidHighValue);
                $this->fail("Expected InvalidArgumentException for value: {$invalidHighValue}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString("N'Zassa score must be between 0 and 100", $e->getMessage());
                $this->assertStringContainsString((string) $invalidHighValue, $e->getMessage());
            }
        }

        // Test with 100 random valid values between 0-100
        for ($i = 0; $i < 100; $i++) {
            $validValue = rand(0, 100);

            $score = NZassaScore::fromInt($validValue);
            $this->assertEquals($validValue, $score->getValue());
            $this->assertGreaterThanOrEqual(0, $score->getValue());
            $this->assertLessThanOrEqual(100, $score->getValue());
        }
    }

    /**
     * Property test: Credit eligibility threshold should be consistent
     *
     * Tests that the credit eligibility logic is consistent across all score values.
     */
    public function test_credit_eligibility_threshold_property()
    {
        // Test all possible score values
        for ($score = 0; $score <= 100; $score++) {
            $nzassaScore = NZassaScore::fromInt($score);

            if ($score > 70) {
                $this->assertTrue(
                    $nzassaScore->isEligibleForCredit(),
                    "Score {$score} should be eligible for credit"
                );
            } else {
                $this->assertFalse(
                    $nzassaScore->isEligibleForCredit(),
                    "Score {$score} should NOT be eligible for credit"
                );
            }
        }
    }

    /**
     * Property test: Score comparison operations should be transitive and consistent
     */
    public function test_score_comparison_properties()
    {
        // Generate 50 random score pairs and test comparison properties
        for ($i = 0; $i < 50; $i++) {
            $value1 = rand(0, 100);
            $value2 = rand(0, 100);
            $value3 = rand(0, 100);

            $score1 = NZassaScore::fromInt($value1);
            $score2 = NZassaScore::fromInt($value2);
            $score3 = NZassaScore::fromInt($value3);

            // Test reflexivity: a equals a
            $this->assertTrue($score1->equals($score1));

            // Test symmetry: if a equals b, then b equals a
            if ($value1 === $value2) {
                $this->assertTrue($score1->equals($score2));
                $this->assertTrue($score2->equals($score1));
            }

            // Test consistency with comparison operations
            if ($value1 > $value2) {
                $this->assertTrue($score1->isGreaterThan($score2));
                $this->assertFalse($score1->isLessThan($score2));
                $this->assertFalse($score1->equals($score2));
            } elseif ($value1 < $value2) {
                $this->assertTrue($score1->isLessThan($score2));
                $this->assertFalse($score1->isGreaterThan($score2));
                $this->assertFalse($score1->equals($score2));
            } else {
                $this->assertTrue($score1->equals($score2));
                $this->assertFalse($score1->isGreaterThan($score2));
                $this->assertFalse($score1->isLessThan($score2));
            }
        }
    }
}
