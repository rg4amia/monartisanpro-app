<?php

namespace App\Infrastructure\Services\GPS;

use App\Domain\Identity\Models\ValueObjects\OTP;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Services\SMSService;
use App\Domain\Shared\Services\GPSUtilityService as GPSUtilityServiceInterface;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GPS Utility Service providing location-based functionality
 * Implements Requirements 10.2, 10.4, 10.5
 */
class GPSUtilityService implements GPSUtilityServiceInterface
{
    private SMSService $smsService;

    private const OTP_CACHE_PREFIX = 'gps_fallback_otp_';

    private const OTP_EXPIRY_MINUTES = 5;

    public function __construct(SMSService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula
     * Requirement 10.2: Use Haversine formula for distance calculations
     *
     * @param  GPS_Coordinates  $from  Starting coordinates
     * @param  GPS_Coordinates  $to  Destination coordinates
     * @return float Distance in meters
     */
    public function calculateDistance(GPS_Coordinates $from, GPS_Coordinates $to): float
    {
        return $from->distanceTo($to);
    }

    /**
     * Validate GPS coordinates accuracy
     * Requirement 10.4: Verify GPS accuracy within 10m
     *
     * @param  GPS_Coordinates  $coordinates  GPS coordinates to validate
     * @return bool True if accuracy is acceptable (< 10m)
     */
    public function validateGPSAccuracy(GPS_Coordinates $coordinates): bool
    {
        return $coordinates->hasAcceptableAccuracy();
    }

    /**
     * Verify proximity between two locations for validation purposes
     * Used for Jeton_Matériel and Jalon proof validations
     *
     * @param  GPS_Coordinates  $location1  First location
     * @param  GPS_Coordinates  $location2  Second location
     * @param  float  $maxDistanceMeters  Maximum allowed distance in meters
     * @return array Validation result with success status and details
     */
    public function verifyProximity(
        GPS_Coordinates $location1,
        GPS_Coordinates $location2,
        float $maxDistanceMeters
    ): array {
        // First check if both coordinates have acceptable accuracy
        if (! $this->validateGPSAccuracy($location1) || ! $this->validateGPSAccuracy($location2)) {
            return [
                'success' => false,
                'reason' => 'GPS_ACCURACY_INSUFFICIENT',
                'message' => 'GPS accuracy is insufficient for proximity verification',
                'fallback_required' => true,
            ];
        }

        $distance = $this->calculateDistance($location1, $location2);

        if ($distance <= $maxDistanceMeters) {
            return [
                'success' => true,
                'distance' => $distance,
                'message' => 'Proximity verification successful',
            ];
        }

        return [
            'success' => false,
            'reason' => 'DISTANCE_EXCEEDED',
            'distance' => $distance,
            'max_distance' => $maxDistanceMeters,
            'message' => "Distance ({$distance}m) exceeds maximum allowed ({$maxDistanceMeters}m)",
            'fallback_required' => false,
        ];
    }

    /**
     * Generate OTP for GPS fallback verification
     * Requirement 10.5: Fall back to OTP SMS verification when GPS unavailable
     *
     * @param  PhoneNumber  $phoneNumber  Phone number to send OTP to
     * @param  string  $context  Context for the OTP (e.g., 'jeton_validation', 'jalon_proof')
     * @return array Result with success status and OTP details
     */
    public function generateGPSFallbackOTP(PhoneNumber $phoneNumber, string $context = 'gps_fallback'): array
    {
        try {
            // Generate OTP
            $otp = OTP::generate($phoneNumber);

            // Store OTP in cache with context
            $cacheKey = $this->getOTPCacheKey($phoneNumber, $context);
            Cache::put($cacheKey, [
                'code' => $otp->getCode(),
                'expires_at' => $otp->getExpiresAt()->format('Y-m-d H:i:s'),
                'context' => $context,
                'created_at' => $otp->getCreatedAt()->format('Y-m-d H:i:s'),
            ], now()->addMinutes(self::OTP_EXPIRY_MINUTES));

            // Send OTP via SMS
            $message = $this->buildOTPMessage($otp->getCode(), $context);
            $smsSent = $this->smsService->send($phoneNumber, $message);

            if (! $smsSent) {
                // Clean up cache if SMS failed
                Cache::forget($cacheKey);

                return [
                    'success' => false,
                    'reason' => 'SMS_DELIVERY_FAILED',
                    'message' => 'Failed to send OTP via SMS',
                ];
            }

            Log::info('GPS fallback OTP generated', [
                'phone_number' => $phoneNumber->toString(),
                'context' => $context,
                'expires_at' => $otp->getExpiresAt()->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'OTP sent successfully for GPS fallback verification',
                'expires_at' => $otp->getExpiresAt()->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate GPS fallback OTP', [
                'phone_number' => $phoneNumber->toString(),
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'reason' => 'OTP_GENERATION_FAILED',
                'message' => 'Failed to generate OTP for GPS fallback',
            ];
        }
    }

    /**
     * Verify OTP for GPS fallback
     *
     * @param  PhoneNumber  $phoneNumber  Phone number that received the OTP
     * @param  string  $code  OTP code to verify
     * @param  string  $context  Context for the OTP verification
     * @return array Verification result
     */
    public function verifyGPSFallbackOTP(PhoneNumber $phoneNumber, string $code, string $context = 'gps_fallback'): array
    {
        $cacheKey = $this->getOTPCacheKey($phoneNumber, $context);
        $cachedOTP = Cache::get($cacheKey);

        if (! $cachedOTP) {
            return [
                'success' => false,
                'reason' => 'OTP_NOT_FOUND',
                'message' => 'OTP not found or expired',
            ];
        }

        // Check if OTP is expired
        $expiresAt = new DateTime($cachedOTP['expires_at']);
        if (new DateTime >= $expiresAt) {
            Cache::forget($cacheKey);

            return [
                'success' => false,
                'reason' => 'OTP_EXPIRED',
                'message' => 'OTP has expired',
            ];
        }

        // Verify code
        if ($cachedOTP['code'] !== $code) {
            return [
                'success' => false,
                'reason' => 'OTP_INVALID',
                'message' => 'Invalid OTP code',
            ];
        }

        // Clean up cache after successful verification
        Cache::forget($cacheKey);

        Log::info('GPS fallback OTP verified successfully', [
            'phone_number' => $phoneNumber->toString(),
            'context' => $context,
        ]);

        return [
            'success' => true,
            'message' => 'OTP verified successfully',
        ];
    }

    /**
     * Check if GPS coordinates are available and valid for validation
     *
     * @param  GPS_Coordinates|null  $coordinates  GPS coordinates to check
     * @return bool True if GPS is available and valid
     */
    public function isGPSAvailable(?GPS_Coordinates $coordinates): bool
    {
        if ($coordinates === null) {
            return false;
        }

        return $this->validateGPSAccuracy($coordinates);
    }

    /**
     * Perform location-based validation with automatic GPS fallback
     * This is the main method that combines GPS validation with OTP fallback
     *
     * @param  GPS_Coordinates|null  $userLocation  User's GPS coordinates (may be null if unavailable)
     * @param  GPS_Coordinates  $requiredLocation  Required location for validation
     * @param  PhoneNumber  $userPhone  User's phone number for OTP fallback
     * @param  float  $maxDistanceMeters  Maximum allowed distance in meters
     * @param  string  $context  Context for the validation
     * @return array Validation result with next steps
     */
    public function performLocationValidation(
        ?GPS_Coordinates $userLocation,
        GPS_Coordinates $requiredLocation,
        PhoneNumber $userPhone,
        float $maxDistanceMeters,
        string $context = 'location_validation'
    ): array {
        // If GPS is available and accurate, use GPS validation
        if ($this->isGPSAvailable($userLocation)) {
            $proximityResult = $this->verifyProximity($userLocation, $requiredLocation, $maxDistanceMeters);

            if ($proximityResult['success']) {
                return [
                    'validation_method' => 'GPS',
                    'success' => true,
                    'message' => 'Location validated using GPS',
                    'distance' => $proximityResult['distance'],
                ];
            }

            // GPS validation failed due to distance
            if ($proximityResult['reason'] === 'DISTANCE_EXCEEDED') {
                return [
                    'validation_method' => 'GPS',
                    'success' => false,
                    'reason' => 'LOCATION_TOO_FAR',
                    'message' => $proximityResult['message'],
                    'distance' => $proximityResult['distance'],
                    'max_distance' => $proximityResult['max_distance'],
                ];
            }
        }

        // GPS unavailable or insufficient accuracy - fall back to OTP
        Log::info('Falling back to OTP verification due to GPS unavailability', [
            'context' => $context,
            'phone_number' => $userPhone->toString(),
            'gps_available' => $userLocation !== null,
            'gps_accurate' => $userLocation ? $this->validateGPSAccuracy($userLocation) : false,
        ]);

        $otpResult = $this->generateGPSFallbackOTP($userPhone, $context);

        if ($otpResult['success']) {
            return [
                'validation_method' => 'OTP_FALLBACK',
                'success' => false, // Not yet validated, waiting for OTP
                'reason' => 'GPS_UNAVAILABLE',
                'message' => 'GPS unavailable. OTP sent for verification.',
                'next_step' => 'VERIFY_OTP',
                'otp_expires_at' => $otpResult['expires_at'],
            ];
        }

        return [
            'validation_method' => 'FAILED',
            'success' => false,
            'reason' => 'VALIDATION_UNAVAILABLE',
            'message' => 'Both GPS and OTP fallback failed',
        ];
    }

    /**
     * Generate cache key for OTP storage
     */
    private function getOTPCacheKey(PhoneNumber $phoneNumber, string $context): string
    {
        return self::OTP_CACHE_PREFIX.$context.'_'.md5($phoneNumber->toString());
    }

    /**
     * Build OTP message based on context
     */
    private function buildOTPMessage(string $code, string $context): string
    {
        $contextMessages = [
            'jeton_validation' => 'pour la validation de votre jeton matériel',
            'jalon_proof' => 'pour la validation de votre preuve de jalon',
            'gps_fallback' => 'pour la vérification de localisation',
            'location_validation' => 'pour la validation de votre position',
        ];

        $contextText = $contextMessages[$context] ?? 'pour la vérification';

        return "Votre code de vérification ProSartisan {$contextText} est: {$code}. Ce code expire dans 5 minutes.";
    }
}
