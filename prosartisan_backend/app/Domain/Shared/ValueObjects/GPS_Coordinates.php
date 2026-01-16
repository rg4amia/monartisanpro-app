<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing GPS coordinates (latitude, longitude)
 * Includes accuracy tracking and distance calculation using Haversine formula
 */
final class GPS_Coordinates
{
    private float $latitude;
    private float $longitude;
    private float $accuracy; // in meters

    private const EARTH_RADIUS_KM = 6371;
    private const MIN_LATITUDE = -90.0;
    private const MAX_LATITUDE = 90.0;
    private const MIN_LONGITUDE = -180.0;
    private const MAX_LONGITUDE = 180.0;

    public function __construct(float $latitude, float $longitude, float $accuracy = 10.0)
    {
        $this->validateLatitude($latitude);
        $this->validateLongitude($longitude);
        $this->validateAccuracy($accuracy);

        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->accuracy = $accuracy;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['latitude'] ?? throw new InvalidArgumentException('Latitude is required'),
            $data['longitude'] ?? throw new InvalidArgumentException('Longitude is required'),
            $data['accuracy'] ?? 10.0
        );
    }

    /**
     * Calculate distance to another coordinate using Haversine formula
     * Returns distance in meters
     */
    public function distanceTo(GPS_Coordinates $other): float
    {
        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($other->latitude);
        $lonTo = deg2rad($other->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distanceKm = self::EARTH_RADIUS_KM * $c;

        return $distanceKm * 1000; // Convert to meters
    }

    /**
     * Blur coordinates by adding random offset within specified radius
     * Used for privacy protection (e.g., 50m blur for artisan locations)
     */
    public function blur(int $radiusMeters): self
    {
        // Convert radius from meters to degrees (approximate)
        $radiusDegrees = $radiusMeters / 111000; // 1 degree ≈ 111km

        // Generate random angle and distance
        $angle = mt_rand(0, 360) * (M_PI / 180);
        $distance = sqrt(mt_rand(0, 1000) / 1000) * $radiusDegrees;

        // Calculate new coordinates
        $newLat = $this->latitude + ($distance * cos($angle));
        $newLon = $this->longitude + ($distance * sin($angle) / cos($this->latitude * M_PI / 180));

        // Ensure coordinates stay within valid bounds
        $newLat = max(self::MIN_LATITUDE, min(self::MAX_LATITUDE, $newLat));
        $newLon = max(self::MIN_LONGITUDE, min(self::MAX_LONGITUDE, $newLon));

        return new self($newLat, $newLon, $this->accuracy);
    }

    /**
     * Check if coordinates are within specified distance of another point
     */
    public function isWithinRadius(GPS_Coordinates $center, float $radiusMeters): bool
    {
        return $this->distanceTo($center) <= $radiusMeters;
    }

    /**
     * Check if GPS accuracy is acceptable (< 10m)
     */
    public function hasAcceptableAccuracy(): bool
    {
        return $this->accuracy <= 10.0;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getAccuracy(): float
    {
        return $this->accuracy;
    }

    public function equals(GPS_Coordinates $other): bool
    {
        return abs($this->latitude - $other->latitude) < 0.000001
            && abs($this->longitude - $other->longitude) < 0.000001;
    }

    /**
     * Convert to PostGIS POINT format for database storage
     */
    public function toPostGISPoint(): string
    {
        return "POINT({$this->longitude} {$this->latitude})";
    }

    /**
     * Convert to WKT (Well-Known Text) format
     */
    public function toWKT(): string
    {
        return $this->toPostGISPoint();
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
        ];
    }

    public function __toString(): string
    {
        return "{$this->latitude},{$this->longitude}";
    }

    private function validateLatitude(float $latitude): void
    {
        if ($latitude < self::MIN_LATITUDE || $latitude > self::MAX_LATITUDE) {
            throw new InvalidArgumentException(
                "Latitude must be between {self::MIN_LATITUDE} and {self::MAX_LATITUDE}, got {$latitude}"
            );
        }
    }

    private function validateLongitude(float $longitude): void
    {
        if ($longitude < self::MIN_LONGITUDE || $longitude > self::MAX_LONGITUDE) {
            throw new InvalidArgumentException(
                "Longitude must be between {self::MIN_LONGITUDE} and {self::MAX_LONGITUDE}, got {$longitude}"
            );
        }
    }

    private function validateAccuracy(float $accuracy): void
    {
        if ($accuracy < 0) {
            throw new InvalidArgumentException('GPS accuracy cannot be negative');
        }
    }
}
