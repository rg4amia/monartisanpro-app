<?php

namespace App\Domain\Marketplace\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\Mission\Mission;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;

/**
 * Repository interface for Mission aggregate
 */
interface MissionRepository
{
 /**
  * Save a mission (create or update)
  */
 public function save(Mission $mission): void;

 /**
  * Find a mission by its ID
  */
 public function findById(MissionId $id): ?Mission;

 /**
  * Find missions by client ID
  *
  * @return Mission[]
  */
 public function findByClientId(UserId $clientId): array;

 /**
  * Find open missions near a location
  *
  * @param GPS_Coordinates $location Center point for search
  * @param float $radiusKm Search radius in kilometers
  * @return Mission[]
  */
 public function findOpenMissionsNearLocation(GPS_Coordinates $location, float $radiusKm): array;
}
