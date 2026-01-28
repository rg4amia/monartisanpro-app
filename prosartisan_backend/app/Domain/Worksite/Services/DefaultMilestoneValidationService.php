<?php

namespace App\Domain\Worksite\Services;

use App\Domain\Identity\Models\ValueObjects\OTP;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Services\SMSService;
use App\Domain\Worksite\Models\ValueObjects\ProofOfDelivery;
use App\Domain\Worksite\Services\DTOs\ValidationResult;
use Illuminate\Support\Facades\Cache;

/**
 * Default implementation of milestone validation service
 *
 * Requirements: 6.2, 6.4, 6.5
 */
final class DefaultMilestoneValidationService implements MilestoneValidationService
{
    private PhotoVerificationService $photoVerificationService;

    private SMSService $smsService;

    private const OTP_EXPIRY_MINUTES = 10;

    private const MAX_PHOTO_AGE_HOURS = 24;

    private const MIN_GPS_ACCURACY_METERS = 50.0;

    public function __construct(
        PhotoVerificationService $photoVerificationService,
        SMSService $smsService
    ) {
        $this->photoVerificationService = $photoVerificationService;
        $this->smsService = $smsService;
    }

    /**
     * Validate proof of delivery
     *
     * Requirement 6.2: Validate GPS-tagged photo with timestamp
     */
    public function validateProof(ProofOfDelivery $proof): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Verify basic integrity
        if (! $proof->verifyIntegrity()) {
            $errors[] = 'Proof failed basic integrity verification';

            return ValidationResult::invalid($errors);
        }

        // Verify photo exists and is accessible
        if (! $this->photoVerificationService->verifyPhotoIntegrity($proof->getPhotoUrl())) {
            $errors[] = 'Photo is not accessible or corrupted';
        }

        // Verify timestamp is reasonable
        if (! $this->photoVerificationService->verifyTimestamp($proof->getCapturedAt())) {
            $errors[] = 'Photo timestamp is invalid or suspicious';
        }

        // Check photo age
        $ageHours = $proof->getAgeInHours();
        if ($ageHours > self::MAX_PHOTO_AGE_HOURS) {
            $warnings[] = "Photo is {$ageHours} hours old, which may be outdated";
        }

        // Extract and verify GPS from EXIF
        $exifGPS = $this->photoVerificationService->extractGPSFromExif($proof->getPhotoUrl());
        if ($exifGPS === null) {
            $warnings[] = 'No GPS coordinates found in photo EXIF data';
        } else {
            // Compare EXIF GPS with provided GPS
            $distance = $proof->getLocation()->distanceTo($exifGPS);
            if ($distance > self::MIN_GPS_ACCURACY_METERS) {
                $warnings[] = "GPS coordinates differ by {$distance}m between EXIF and provided data";
            }
        }

        // Verify GPS accuracy
        $accuracy = $proof->getLocation()->getAccuracy();
        if ($accuracy > self::MIN_GPS_ACCURACY_METERS) {
            $warnings[] = "GPS accuracy is {$accuracy}m, which may be imprecise";
        }

        return empty($errors)
         ? ValidationResult::valid($warnings)
         : ValidationResult::invalid($errors, $warnings);
    }

    /**
     * Generate OTP for client validation
     *
     * Requirement 6.4: OTP fallback when GPS is unavailable
     */
    public function generateOTP(PhoneNumber $clientPhone): OTP
    {
        $otp = OTP::generate();

        // Store OTP in cache with expiry
        $cacheKey = $this->getOTPCacheKey($clientPhone);
        Cache::put($cacheKey, $otp->getCode(), now()->addMinutes(self::OTP_EXPIRY_MINUTES));

        // Send OTP via SMS
        $message = "Votre code de validation ProSartisan: {$otp->getCode()}. Valide pendant ".self::OTP_EXPIRY_MINUTES.' minutes.';
        $this->smsService->sendSMS($clientPhone, $message);

        return $otp;
    }

    /**
     * Verify OTP code
     *
     * Requirement 6.4: Verify OTP for milestone validation
     */
    public function verifyOTP(PhoneNumber $phone, string $code): bool
    {
        $cacheKey = $this->getOTPCacheKey($phone);
        $storedCode = Cache::get($cacheKey);

        if ($storedCode === null) {
            return false; // OTP expired or not found
        }

        $isValid = $storedCode === $code;

        if ($isValid) {
            // Remove OTP from cache after successful verification
            Cache::forget($cacheKey);
        }

        return $isValid;
    }

    private function getOTPCacheKey(PhoneNumber $phone): string
    {
        return 'milestone_otp:'.$phone->getValue();
    }
}
