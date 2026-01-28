<?php

namespace App\Domain\Worksite\Services;

use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;

/**
 * Domain service for photo verification and EXIF data extraction
 *
 * Handles photo integrity checks and GPS extraction from EXIF data
 * Requirements: 6.2, 6.4
 */
interface PhotoVerificationService
{
    /**
     * Extract GPS coordinates from photo EXIF data
     *
     * Requirement 6.2: Extract GPS from photo metadata
     */
    public function extractGPSFromExif(string $photoUrl): ?GPS_Coordinates;

    /**
     * Verify photo timestamp is reasonable
     *
     * Requirement 6.2: Verify photo timestamp integrity
     */
    public function verifyTimestamp(DateTime $capturedAt): bool;

    /**
     * Verify photo integrity and accessibility
     *
     * Requirement 6.2: Ensure photo is valid and accessible
     */
    public function verifyPhotoIntegrity(string $photoUrl): bool;
}
