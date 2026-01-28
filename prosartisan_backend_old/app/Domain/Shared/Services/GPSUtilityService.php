<?php

namespace App\Domain\Shared\Services;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;

/**
 * Interface for GPS utility services
 * Defines contracts for GPS-based operations and fallback mechanisms
 */
interface GPSUtilityService
{
    /**
     * Calculate distance between two GPS coordinates using Haversine formula
     * Requirement 10.2: Use Haversine formula for distance calculations
     *
     * @param  GPS_Coordinates  $from  Starting coordinates
     * @param  GPS_Coordinates  $to  Destination coordinates
     * @return float Distance in meters
     */
    public function calculateDistance(GPS_Coordinates $from, GPS_Coordinates $to): float;

    /**
     * Validate GPS coordinates accuracy
     * Requirement 10.4: Verify GPS accuracy within 10m
     *
     * @param  GPS_Coordinates  $coordinates  GPS coordinates to validate
     * @return bool True if accuracy is acceptable (< 10m)
     */
    public function validateGPSAccuracy(GPS_Coordinates $coordinates): bool;

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
    ): array;

    /**
     * Generate OTP for GPS fallback verification
     * Requirement 10.5: Fall back to OTP SMS verification when GPS unavailable
     *
     * @param  PhoneNumber  $phoneNumber  Phone number to send OTP to
     * @param  string  $context  Context for the OTP (e.g., 'jeton_validation', 'jalon_proof')
     * @return array Result with success status and OTP details
     */
    public function generateGPSFallbackOTP(PhoneNumber $phoneNumber, string $context = 'gps_fallback'): array;

    /**
     * Verify OTP for GPS fallback
     *
     * @param  PhoneNumber  $phoneNumber  Phone number that received the OTP
     * @param  string  $code  OTP code to verify
     * @param  string  $context  Context for the OTP verification
     * @return array Verification result
     */
    public function verifyGPSFallbackOTP(PhoneNumber $phoneNumber, string $code, string $context = 'gps_fallback'): array;

    /**
     * Check if GPS coordinates are available and valid for validation
     *
     * @param  GPS_Coordinates|null  $coordinates  GPS coordinates to check
     * @return bool True if GPS is available and valid
     */
    public function isGPSAvailable(?GPS_Coordinates $coordinates): bool;

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
    ): array;
}
