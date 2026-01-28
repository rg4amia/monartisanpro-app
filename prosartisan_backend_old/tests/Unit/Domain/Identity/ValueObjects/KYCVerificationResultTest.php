<?php

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\Models\ValueObjects\KYCVerificationResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for KYCVerificationResult value object
 */
class KYCVerificationResultTest extends TestCase
{
    public function test_can_create_success_result(): void
    {
        $result = KYCVerificationResult::success();

        $this->assertTrue($result->isVerified());
        $this->assertFalse($result->hasErrors());
        $this->assertEmpty($result->getValidationErrors());
        $this->assertNotNull($result->getVerifiedAt());
    }

    public function test_can_create_failure_result(): void
    {
        $errors = ['Error 1', 'Error 2'];
        $result = KYCVerificationResult::failure($errors);

        $this->assertFalse($result->isVerified());
        $this->assertTrue($result->hasErrors());
        $this->assertEquals($errors, $result->getValidationErrors());
        $this->assertNull($result->getVerifiedAt());
    }

    public function test_failure_requires_at_least_one_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation errors cannot be empty for a failed verification');

        KYCVerificationResult::failure([]);
    }

    public function test_success_result_to_array(): void
    {
        $result = KYCVerificationResult::success();
        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('is_verified', $array);
        $this->assertArrayHasKey('validation_errors', $array);
        $this->assertArrayHasKey('verified_at', $array);
        $this->assertTrue($array['is_verified']);
        $this->assertEmpty($array['validation_errors']);
        $this->assertNotNull($array['verified_at']);
    }

    public function test_failure_result_to_array(): void
    {
        $errors = ['Error 1', 'Error 2'];
        $result = KYCVerificationResult::failure($errors);
        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('is_verified', $array);
        $this->assertArrayHasKey('validation_errors', $array);
        $this->assertArrayHasKey('verified_at', $array);
        $this->assertFalse($array['is_verified']);
        $this->assertEquals($errors, $array['validation_errors']);
        $this->assertNull($array['verified_at']);
    }

    public function test_verified_at_is_current_timestamp(): void
    {
        $before = new \DateTime;
        $result = KYCVerificationResult::success();
        $after = new \DateTime;

        $verifiedAt = $result->getVerifiedAt();
        $this->assertNotNull($verifiedAt);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $verifiedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $verifiedAt->getTimestamp());
    }
}
