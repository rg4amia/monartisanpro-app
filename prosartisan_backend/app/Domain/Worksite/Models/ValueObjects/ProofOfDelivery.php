<?php

namespace App\Domain\Worksite\Models\ValueObjects;

use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;
use InvalidArgumentException;

/**
 * Value object representing proof of delivery for a milestone
 *
 * Contains photo evidence with GPS coordinates and timestamp
 * Requirements: 6.2
 */
final class ProofOfDelivery
{
    private string $photoUrl;

    private GPS_Coordinates $location;

    private DateTime $capturedAt;

    private array $exifData;

    public function __construct(
        string $photoUrl,
        GPS_Coordinates $location,
        DateTime $capturedAt,
        array $exifData = []
    ) {
        $this->validatePhotoUrl($photoUrl);
        $this->validateCapturedAt($capturedAt);

        $this->photoUrl = $photoUrl;
        $this->location = $location;
        $this->capturedAt = $capturedAt;
        $this->exifData = $exifData;
    }

    public function getPhotoUrl(): string
    {
        return $this->photoUrl;
    }

    public function getLocation(): GPS_Coordinates
    {
        return $this->location;
    }

    public function getCapturedAt(): DateTime
    {
        return $this->capturedAt;
    }

    public function getExifData(): array
    {
        return $this->exifData;
    }

    /**
     * Verify the integrity of the proof
     *
     * Checks if the photo exists and has valid GPS/timestamp data
     */
    public function verifyIntegrity(): bool
    {
        // Check if photo URL is accessible
        if (! $this->isPhotoAccessible()) {
            return false;
        }

        // Check if GPS coordinates are valid
        if (! $this->location->isValid()) {
            return false;
        }

        // Check if timestamp is reasonable (not in future, not too old)
        $now = new DateTime;
        $maxAge = new DateTime('-30 days'); // Photos older than 30 days are suspicious

        if ($this->capturedAt > $now || $this->capturedAt < $maxAge) {
            return false;
        }

        return true;
    }

    /**
     * Check if GPS coordinates match expected location within tolerance
     */
    public function isLocationValid(GPS_Coordinates $expectedLocation, float $toleranceMeters = 100.0): bool
    {
        $distance = $this->location->distanceTo($expectedLocation);

        return $distance <= $toleranceMeters;
    }

    /**
     * Get the age of the proof in hours
     */
    public function getAgeInHours(): float
    {
        $now = new DateTime;
        $diff = $now->getTimestamp() - $this->capturedAt->getTimestamp();

        return $diff / 3600; // Convert seconds to hours
    }

    public function toArray(): array
    {
        return [
            'photo_url' => $this->photoUrl,
            'location' => $this->location->toArray(),
            'captured_at' => $this->capturedAt->format('Y-m-d H:i:s'),
            'exif_data' => $this->exifData,
            'age_hours' => $this->getAgeInHours(),
        ];
    }

    private function validatePhotoUrl(string $photoUrl): void
    {
        if (empty($photoUrl)) {
            throw new InvalidArgumentException('Photo URL cannot be empty');
        }

        // Basic URL validation
        if (! filter_var($photoUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid photo URL format: {$photoUrl}");
        }
    }

    private function validateCapturedAt(DateTime $capturedAt): void
    {
        $now = new DateTime;

        // Photo cannot be from the future
        if ($capturedAt > $now) {
            throw new InvalidArgumentException('Photo capture time cannot be in the future');
        }
    }

    private function isPhotoAccessible(): bool
    {
        // In a real implementation, this would check if the photo URL is accessible
        // For now, we just check if it's a valid URL format
        return filter_var($this->photoUrl, FILTER_VALIDATE_URL) !== false;
    }
}
