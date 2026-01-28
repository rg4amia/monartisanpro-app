<?php

namespace Tests\Unit\Infrastructure\Services\GPS;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Services\SMSService;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Infrastructure\Services\GPS\GPSUtilityService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class GPSUtilityServiceTest extends TestCase
{
    private GPSUtilityService $gpsService;

    private SMSService $mockSmsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSmsService = Mockery::mock(SMSService::class);
        $this->gpsService = new GPSUtilityService($this->mockSmsService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test Haversine distance calculation
     * Requirement 10.2: Use Haversine formula for distance calculations
     */
    public function test_calculates_distance_using_haversine(): void
    {
        // Arrange - Abidjan and Yamoussoukro coordinates
        $abidjan = new GPS_Coordinates(5.3600, -4.0083);
        $yamoussoukro = new GPS_Coordinates(6.8276, -5.2893);

        // Act
        $distance = $this->gpsService->calculateDistance($abidjan, $yamoussoukro);

        // Assert - Distance should be approximately 230km
        $this->assertGreaterThan(200000, $distance);
        $this->assertLessThan(250000, $distance);
    }

    public function test_calculates_short_distance_accurately(): void
    {
        // Arrange - Two points very close to each other
        $point1 = new GPS_Coordinates(5.3600, -4.0083);
        $point2 = new GPS_Coordinates(5.3610, -4.0093); // ~150m away

        // Act
        $distance = $this->gpsService->calculateDistance($point1, $point2);

        // Assert
        $this->assertGreaterThan(100, $distance);
        $this->assertLessThan(300, $distance);
    }

    /**
     * Test GPS accuracy validation
     * Requirement 10.4: Verify GPS accuracy within 10m
     */
    public function test_validates_gps_accuracy_within_10m(): void
    {
        // Arrange
        $accurateCoords = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $inaccurateCoords = new GPS_Coordinates(5.3600, -4.0083, 15.0);

        // Act & Assert
        $this->assertTrue($this->gpsService->validateGPSAccuracy($accurateCoords));
        $this->assertFalse($this->gpsService->validateGPSAccuracy($inaccurateCoords));
    }

    public function test_validates_gps_accuracy_at_boundary(): void
    {
        // Arrange - Exactly 10m accuracy (boundary case)
        $boundaryCoords = new GPS_Coordinates(5.3600, -4.0083, 10.0);

        // Act & Assert
        $this->assertTrue($this->gpsService->validateGPSAccuracy($boundaryCoords));
    }

    /**
     * Test proximity verification for validation purposes
     */
    public function test_verifies_proximity_within_allowed_distance(): void
    {
        // Arrange
        $location1 = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $location2 = new GPS_Coordinates(5.3605, -4.0088, 5.0); // ~70m away
        $maxDistance = 100.0;

        // Act
        $result = $this->gpsService->verifyProximity($location1, $location2, $maxDistance);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('distance', $result);
        $this->assertLessThan($maxDistance, $result['distance']);
    }

    public function test_rejects_proximity_beyond_allowed_distance(): void
    {
        // Arrange
        $location1 = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $location2 = new GPS_Coordinates(5.3700, -4.0183, 5.0); // ~1.5km away
        $maxDistance = 100.0;

        // Act
        $result = $this->gpsService->verifyProximity($location1, $location2, $maxDistance);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('DISTANCE_EXCEEDED', $result['reason']);
        $this->assertArrayHasKey('distance', $result);
        $this->assertGreaterThan($maxDistance, $result['distance']);
    }

    public function test_rejects_proximity_with_insufficient_gps_accuracy(): void
    {
        // Arrange - One location has poor accuracy
        $location1 = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $location2 = new GPS_Coordinates(5.3605, -4.0088, 15.0); // Poor accuracy
        $maxDistance = 100.0;

        // Act
        $result = $this->gpsService->verifyProximity($location1, $location2, $maxDistance);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('GPS_ACCURACY_INSUFFICIENT', $result['reason']);
        $this->assertTrue($result['fallback_required']);
    }

    /**
     * Test GPS fallback OTP generation
     * Requirement 10.5: Fall back to OTP SMS verification when GPS unavailable
     */
    public function test_generates_gps_fallback_otp_successfully(): void
    {
        // Arrange
        $phoneNumber = PhoneNumber::fromString('+22501234567890');
        $context = 'jeton_validation';

        $this->mockSmsService
            ->shouldReceive('send')
            ->once()
            ->with($phoneNumber, Mockery::type('string'))
            ->andReturn(true);

        // Act
        $result = $this->gpsService->generateGPSFallbackOTP($phoneNumber, $context);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertStringContainsString('OTP sent successfully', $result['message']);
    }

    public function test_fails_to_generate_otp_when_sms_fails(): void
    {
        // Arrange
        $phoneNumber = PhoneNumber::fromString('+22501234567890');

        $this->mockSmsService
            ->shouldReceive('send')
            ->once()
            ->andReturn(false);

        // Act
        $result = $this->gpsService->generateGPSFallbackOTP($phoneNumber);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('SMS_DELIVERY_FAILED', $result['reason']);
    }

    /**
     * Test GPS fallback OTP verification
     */
    public function test_verifies_gps_fallback_otp_successfully(): void
    {
        // Arrange
        $phoneNumber = PhoneNumber::fromString('+22501234567890');
        $context = 'jeton_validation';
        $code = '123456';

        // Mock cache to return valid OTP
        Cache::shouldReceive('get')
            ->once()
            ->andReturn([
                'code' => $code,
                'expires_at' => now()->addMinutes(3)->format('Y-m-d H:i:s'),
                'context' => $context,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);

        Cache::shouldReceive('forget')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->gpsService->verifyGPSFallbackOTP($phoneNumber, $code, $context);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('OTP verified successfully', $result['message']);
    }

    public function test_rejects_expired_otp(): void
    {
        // Arrange
        $phoneNumber = PhoneNumber::fromString('+22501234567890');
        $code = '123456';

        // Mock cache to return expired OTP
        Cache::shouldReceive('get')
            ->once()
            ->andReturn([
                'code' => $code,
                'expires_at' => now()->subMinutes(1)->format('Y-m-d H:i:s'), // Expired
                'context' => 'gps_fallback',
                'created_at' => now()->subMinutes(6)->format('Y-m-d H:i:s'),
            ]);

        Cache::shouldReceive('forget')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->gpsService->verifyGPSFallbackOTP($phoneNumber, $code);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('OTP_EXPIRED', $result['reason']);
    }

    public function test_rejects_invalid_otp_code(): void
    {
        // Arrange
        $phoneNumber = PhoneNumber::fromString('+22501234567890');
        $wrongCode = '654321';

        // Mock cache to return different OTP
        Cache::shouldReceive('get')
            ->once()
            ->andReturn([
                'code' => '123456', // Different code
                'expires_at' => now()->addMinutes(3)->format('Y-m-d H:i:s'),
                'context' => 'gps_fallback',
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);

        // Act
        $result = $this->gpsService->verifyGPSFallbackOTP($phoneNumber, $wrongCode);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('OTP_INVALID', $result['reason']);
    }

    public function test_rejects_non_existent_otp(): void
    {
        // Arrange
        $phoneNumber = PhoneNumber::fromString('+22501234567890');
        $code = '123456';

        // Mock cache to return null (no OTP found)
        Cache::shouldReceive('get')
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->gpsService->verifyGPSFallbackOTP($phoneNumber, $code);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('OTP_NOT_FOUND', $result['reason']);
    }

    /**
     * Test GPS availability check
     */
    public function test_checks_gps_availability_with_accurate_coordinates(): void
    {
        // Arrange
        $accurateCoords = new GPS_Coordinates(5.3600, -4.0083, 5.0);

        // Act & Assert
        $this->assertTrue($this->gpsService->isGPSAvailable($accurateCoords));
    }

    public function test_checks_gps_availability_with_inaccurate_coordinates(): void
    {
        // Arrange
        $inaccurateCoords = new GPS_Coordinates(5.3600, -4.0083, 15.0);

        // Act & Assert
        $this->assertFalse($this->gpsService->isGPSAvailable($inaccurateCoords));
    }

    public function test_checks_gps_availability_with_null_coordinates(): void
    {
        // Act & Assert
        $this->assertFalse($this->gpsService->isGPSAvailable(null));
    }

    /**
     * Test complete location validation with GPS fallback
     */
    public function test_performs_location_validation_with_gps_success(): void
    {
        // Arrange
        $userLocation = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $requiredLocation = new GPS_Coordinates(5.3605, -4.0088, 5.0); // ~70m away
        $userPhone = PhoneNumber::fromString('+22501234567890');
        $maxDistance = 100.0;

        // Act
        $result = $this->gpsService->performLocationValidation(
            $userLocation,
            $requiredLocation,
            $userPhone,
            $maxDistance,
            'test_validation'
        );

        // Assert
        $this->assertEquals('GPS', $result['validation_method']);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('distance', $result);
    }

    public function test_performs_location_validation_with_gps_distance_failure(): void
    {
        // Arrange
        $userLocation = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $requiredLocation = new GPS_Coordinates(5.3700, -4.0183, 5.0); // ~1.5km away
        $userPhone = PhoneNumber::fromString('+22501234567890');
        $maxDistance = 100.0;

        // Act
        $result = $this->gpsService->performLocationValidation(
            $userLocation,
            $requiredLocation,
            $userPhone,
            $maxDistance,
            'test_validation'
        );

        // Assert
        $this->assertEquals('GPS', $result['validation_method']);
        $this->assertFalse($result['success']);
        $this->assertEquals('LOCATION_TOO_FAR', $result['reason']);
    }

    public function test_performs_location_validation_with_otp_fallback(): void
    {
        // Arrange - No GPS available
        $userLocation = null;
        $requiredLocation = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $userPhone = PhoneNumber::fromString('+22501234567890');
        $maxDistance = 100.0;

        $this->mockSmsService
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->gpsService->performLocationValidation(
            $userLocation,
            $requiredLocation,
            $userPhone,
            $maxDistance,
            'test_validation'
        );

        // Assert
        $this->assertEquals('OTP_FALLBACK', $result['validation_method']);
        $this->assertFalse($result['success']); // Not yet validated, waiting for OTP
        $this->assertEquals('GPS_UNAVAILABLE', $result['reason']);
        $this->assertEquals('VERIFY_OTP', $result['next_step']);
        $this->assertArrayHasKey('otp_expires_at', $result);
    }

    public function test_performs_location_validation_with_inaccurate_gps_fallback(): void
    {
        // Arrange - Inaccurate GPS
        $userLocation = new GPS_Coordinates(5.3600, -4.0083, 15.0); // Poor accuracy
        $requiredLocation = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $userPhone = PhoneNumber::fromString('+22501234567890');
        $maxDistance = 100.0;

        $this->mockSmsService
            ->shouldReceive('send')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->gpsService->performLocationValidation(
            $userLocation,
            $requiredLocation,
            $userPhone,
            $maxDistance,
            'test_validation'
        );

        // Assert
        $this->assertEquals('OTP_FALLBACK', $result['validation_method']);
        $this->assertFalse($result['success']);
        $this->assertEquals('GPS_UNAVAILABLE', $result['reason']);
    }

    public function test_performs_location_validation_with_complete_failure(): void
    {
        // Arrange - No GPS and OTP fails
        $userLocation = null;
        $requiredLocation = new GPS_Coordinates(5.3600, -4.0083, 5.0);
        $userPhone = PhoneNumber::fromString('+22501234567890');
        $maxDistance = 100.0;

        $this->mockSmsService
            ->shouldReceive('send')
            ->once()
            ->andReturn(false); // SMS fails

        // Act
        $result = $this->gpsService->performLocationValidation(
            $userLocation,
            $requiredLocation,
            $userPhone,
            $maxDistance,
            'test_validation'
        );

        // Assert
        $this->assertEquals('FAILED', $result['validation_method']);
        $this->assertFalse($result['success']);
        $this->assertEquals('VALIDATION_UNAVAILABLE', $result['reason']);
    }
}
