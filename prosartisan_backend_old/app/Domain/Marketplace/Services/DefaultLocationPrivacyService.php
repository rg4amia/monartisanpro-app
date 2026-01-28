<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Marketplace\Repositories\MissionRepository;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use InvalidArgumentException;

/**
 * Default implementation of LocationPrivacyService
 *
 * Implements GPS coordinate blurring for privacy protection
 * and controlled revelation after quote acceptance
 */
final class DefaultLocationPrivacyService implements LocationPrivacyService
{
    private MissionRepository $missionRepository;

    public function __construct(MissionRepository $missionRepository)
    {
        $this->missionRepository = $missionRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function blurCoordinates(GPS_Coordinates $coords, int $radiusMeters = 50): GPS_Coordinates
    {
        if ($radiusMeters <= 0) {
            throw new InvalidArgumentException('Blur radius must be positive');
        }

        return $coords->blur($radiusMeters);
    }

    /**
     * {@inheritDoc}
     */
    public function revealExactLocation(MissionId $missionId, UserId $artisanId): GPS_Coordinates
    {
        // Retrieve the mission
        $mission = $this->missionRepository->findById($missionId);

        if ($mission === null) {
            throw new InvalidArgumentException(
                "Mission {$missionId->getValue()} not found"
            );
        }

        // Check if the artisan has an accepted quote for this mission
        $acceptedQuote = $mission->getAcceptedQuote();

        if ($acceptedQuote === null) {
            throw new InvalidArgumentException(
                "No accepted quote found for mission {$missionId->getValue()}"
            );
        }

        // Verify the artisan is the one who submitted the accepted quote
        if (! $acceptedQuote->getArtisanId()->equals($artisanId)) {
            throw new InvalidArgumentException(
                "Artisan {$artisanId->getValue()} is not authorized to view exact location for mission {$missionId->getValue()}"
            );
        }

        // Return the exact mission location
        return $mission->getLocation();
    }
}
