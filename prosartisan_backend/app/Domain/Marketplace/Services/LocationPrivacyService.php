<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;

/**
 * Domain service for managing location privacy
 *
 * Protects client location by blurring GPS coordinates to a 50m radius
 * Reveals exact coordinates only after quote acceptance
 */
interface LocationPrivacyService
{
 /**
  * Blur GPS coordinates for privacy protection
  *
  * @param GPS_Coordinates $coords Original coordinates to blur
  * @param int $radiusMeters Blur radius in meters (default 50m)
  * @return GPS_Coordinates Blurred coordinates within specified radius
  */
 public function blurCoordinates(GPS_Coordinates $coords, int $radiusMeters = 50): GPS_Coordinates;

 /**
  * Reveal exact mission location to artisan after quote acceptance
  *
  * This method should only return exact coordinates if:
  * - The mission exists
  * - The artisan has an accepted quote for this mission
  *
  * @param MissionId $missionId The mission ID
  * @param UserId $artisanId The artisan requesting location
  * @return GPS_Coordinates Exact mission coordinates
  * @throws \InvalidArgumentException if artisan is not authorized to view exact location
  */
 public function revealExactLocation(MissionId $missionId, UserId $artisanId): GPS_Coordinates;
}
