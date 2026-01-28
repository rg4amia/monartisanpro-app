<?php

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\Models\ValueObjects\OTP;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use DateTime;
use InvalidArgumentException;
use Tests\TestCase;

class OTPTest extends TestCase
{
    /**
     * Test OTP generation creates 6-digit code
     */
    public function test_generate_creates_six_digit_code(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');

        // Act
        $otp = OTP::generate($phone);

        // Assert
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp->getCode());
    }

    /**
     * Test OTP constructor validates code format
     */
    public function test_constructor_validates_code_format(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $expiresAt = new DateTime('+5 minutes');

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OTP code must be exactly 6 digits');

        // Act
        new OTP('12345', $phone, $expiresAt); // Only 5 digits
    }

    /**
     * Test OTP constructor rejects empty code
     */
    public function test_constructor_rejects_empty_code(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $expiresAt = new DateTime('+5 minutes');

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OTP code cannot be empty');

        // Act
        new OTP('', $phone, $expiresAt);
    }

    /**
     * Test OTP constructor rejects non-numeric code
     */
    public function test_constructor_rejects_non_numeric_code(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $expiresAt = new DateTime('+5 minutes');

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OTP code must be exactly 6 digits');

        // Act
        new OTP('ABC123', $phone, $expiresAt);
    }

    /**
     * Test OTP is not expired immediately after generation
     */
    public function test_generated_otp_is_not_expired(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');

        // Act
        $otp = OTP::generate($phone);

        // Assert
        $this->assertFalse($otp->isExpired());
    }

    /**
     * Test OTP expiration detection
     */
    public function test_otp_expiration_detection(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $expiresAt = new DateTime('-1 minute'); // Already expired

        // Act
        $otp = new OTP('123456', $phone, $expiresAt);

        // Assert
        $this->assertTrue($otp->isExpired());
    }

    /**
     * Test OTP verification with correct code
     */
    public function test_verify_with_correct_code_returns_true(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $code = '123456';
        $expiresAt = new DateTime('+5 minutes');
        $otp = new OTP($code, $phone, $expiresAt);

        // Act
        $result = $otp->verify($code);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test OTP verification with wrong code
     */
    public function test_verify_with_wrong_code_returns_false(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $code = '123456';
        $expiresAt = new DateTime('+5 minutes');
        $otp = new OTP($code, $phone, $expiresAt);

        // Act
        $result = $otp->verify('654321');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test OTP verification fails for expired OTP
     */
    public function test_verify_expired_otp_returns_false(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $code = '123456';
        $expiresAt = new DateTime('-1 minute'); // Already expired
        $otp = new OTP($code, $phone, $expiresAt);

        // Act
        $result = $otp->verify($code);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test OTP toString returns code
     */
    public function test_to_string_returns_code(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $code = '123456';
        $expiresAt = new DateTime('+5 minutes');
        $otp = new OTP($code, $phone, $expiresAt);

        // Act & Assert
        $this->assertEquals($code, $otp->toString());
        $this->assertEquals($code, (string) $otp);
    }

    /**
     * Test OTP getters return correct values
     */
    public function test_getters_return_correct_values(): void
    {
        // Arrange
        $phone = new PhoneNumber('+2250123456789');
        $code = '123456';
        $expiresAt = new DateTime('+5 minutes');
        $createdAt = new DateTime;
        $otp = new OTP($code, $phone, $expiresAt, $createdAt);

        // Act & Assert
        $this->assertEquals($code, $otp->getCode());
        $this->assertEquals($phone->getValue(), $otp->getPhoneNumber()->getValue());
        $this->assertEquals($expiresAt, $otp->getExpiresAt());
        $this->assertEquals($createdAt, $otp->getCreatedAt());
    }
}
