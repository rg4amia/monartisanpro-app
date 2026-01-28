<?php

namespace App\Domain\Worksite\Services;

use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Default implementation of photo verification service
 *
 * Requirements: 6.2, 6.4
 */
final class DefaultPhotoVerificationService implements PhotoVerificationService
{
    private const MAX_PHOTO_AGE_DAYS = 30;

    private const MIN_PHOTO_AGE_MINUTES = -5; // Allow 5 minutes in future for clock skew

    private const SUPPORTED_FORMATS = ['jpg', 'jpeg', 'png'];

    private const MAX_PHOTO_SIZE_MB = 10;

    /**
     * Extract GPS coordinates from photo EXIF data
     *
     * Requirement 6.2: Extract GPS from photo metadata
     */
    public function extractGPSFromExif(string $photoUrl): ?GPS_Coordinates
    {
        try {
            // Download photo temporarily if it's a remote URL
            $localPath = $this->getLocalPhotoPath($photoUrl);
            if ($localPath === null) {
                return null;
            }

            // Read EXIF data
            $exifData = @exif_read_data($localPath);
            if ($exifData === false || ! isset($exifData['GPS'])) {
                $this->cleanupTempFile($localPath, $photoUrl);

                return null;
            }

            $gps = $exifData['GPS'];

            // Extract latitude
            $latitude = $this->convertGPSCoordinate(
                $gps['GPSLatitude'] ?? null,
                $gps['GPSLatitudeRef'] ?? null
            );

            // Extract longitude
            $longitude = $this->convertGPSCoordinate(
                $gps['GPSLongitude'] ?? null,
                $gps['GPSLongitudeRef'] ?? null
            );

            $this->cleanupTempFile($localPath, $photoUrl);

            if ($latitude === null || $longitude === null) {
                return null;
            }

            // Estimate accuracy (EXIF doesn't always provide this)
            $accuracy = 10.0; // Default accuracy in meters
            if (isset($gps['GPSHPositioningError'])) {
                $accuracy = (float) $gps['GPSHPositioningError'];
            }

            return new GPS_Coordinates($latitude, $longitude, $accuracy);
        } catch (Exception $e) {
            Log::warning('Failed to extract GPS from EXIF', [
                'photo_url' => $photoUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify photo timestamp is reasonable
     *
     * Requirement 6.2: Verify photo timestamp integrity
     */
    public function verifyTimestamp(DateTime $capturedAt): bool
    {
        $now = new DateTime;

        // Check if photo is from the future (allowing small clock skew)
        $futureLimit = (clone $now)->modify('+'.abs(self::MIN_PHOTO_AGE_MINUTES).' minutes');
        if ($capturedAt > $futureLimit) {
            return false;
        }

        // Check if photo is too old
        $pastLimit = (clone $now)->modify('-'.self::MAX_PHOTO_AGE_DAYS.' days');
        if ($capturedAt < $pastLimit) {
            return false;
        }

        return true;
    }

    /**
     * Verify photo integrity and accessibility
     *
     * Requirement 6.2: Ensure photo is valid and accessible
     */
    public function verifyPhotoIntegrity(string $photoUrl): bool
    {
        try {
            // Check URL format
            if (! filter_var($photoUrl, FILTER_VALIDATE_URL)) {
                return false;
            }

            // Check file extension
            $extension = strtolower(pathinfo($photoUrl, PATHINFO_EXTENSION));
            if (! in_array($extension, self::SUPPORTED_FORMATS)) {
                return false;
            }

            // Check if photo is accessible
            if ($this->isLocalFile($photoUrl)) {
                return $this->verifyLocalPhoto($photoUrl);
            } else {
                return $this->verifyRemotePhoto($photoUrl);
            }
        } catch (Exception $e) {
            Log::warning('Photo integrity verification failed', [
                'photo_url' => $photoUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function convertGPSCoordinate(?array $coordinate, ?string $hemisphere): ?float
    {
        if ($coordinate === null || $hemisphere === null) {
            return null;
        }

        if (count($coordinate) !== 3) {
            return null;
        }

        // Convert degrees, minutes, seconds to decimal degrees
        $degrees = $this->evaluateFraction($coordinate[0]);
        $minutes = $this->evaluateFraction($coordinate[1]);
        $seconds = $this->evaluateFraction($coordinate[2]);

        if ($degrees === null || $minutes === null || $seconds === null) {
            return null;
        }

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        // Apply hemisphere
        if (in_array($hemisphere, ['S', 'W'])) {
            $decimal = -$decimal;
        }

        return $decimal;
    }

    private function evaluateFraction($fraction): ?float
    {
        if (is_numeric($fraction)) {
            return (float) $fraction;
        }

        if (is_string($fraction) && strpos($fraction, '/') !== false) {
            $parts = explode('/', $fraction);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && $parts[1] != 0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        return null;
    }

    private function getLocalPhotoPath(string $photoUrl): ?string
    {
        if ($this->isLocalFile($photoUrl)) {
            // Convert URL to local path
            $path = str_replace(url('/storage/'), '', $photoUrl);

            return Storage::disk('public')->path($path);
        }

        // Download remote file temporarily
        try {
            $response = Http::timeout(30)->get($photoUrl);
            if (! $response->successful()) {
                return null;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'photo_verification_');
            file_put_contents($tempPath, $response->body());

            return $tempPath;
        } catch (Exception $e) {
            Log::warning('Failed to download photo for verification', [
                'photo_url' => $photoUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function cleanupTempFile(string $localPath, string $originalUrl): void
    {
        if (! $this->isLocalFile($originalUrl) && file_exists($localPath)) {
            @unlink($localPath);
        }
    }

    private function isLocalFile(string $photoUrl): bool
    {
        return strpos($photoUrl, url('/storage/')) === 0;
    }

    private function verifyLocalPhoto(string $photoUrl): bool
    {
        $path = str_replace(url('/storage/'), '', $photoUrl);

        if (! Storage::disk('public')->exists($path)) {
            return false;
        }

        $size = Storage::disk('public')->size($path);
        $maxSizeBytes = self::MAX_PHOTO_SIZE_MB * 1024 * 1024;

        return $size > 0 && $size <= $maxSizeBytes;
    }

    private function verifyRemotePhoto(string $photoUrl): bool
    {
        try {
            $response = Http::head($photoUrl);

            if (! $response->successful()) {
                return false;
            }

            $contentLength = $response->header('Content-Length');
            if ($contentLength !== null) {
                $maxSizeBytes = self::MAX_PHOTO_SIZE_MB * 1024 * 1024;

                return (int) $contentLength <= $maxSizeBytes;
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
