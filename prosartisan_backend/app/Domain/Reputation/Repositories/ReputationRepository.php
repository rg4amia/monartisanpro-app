<?php

namespace App\Domain\Reputation\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Reputation\Models\ReputationProfile\ReputationProfile;
use App\Domain\Reputation\Models\ValueObjects\ProfileId;

/**
 * Repository interface for ReputationProfile aggregate
 */
interface ReputationRepository
{
    /**
     * Save a reputation profile
     */
    public function save(ReputationProfile $profile): void;

    /**
     * Find reputation profile by ID
     */
    public function findById(ProfileId $id): ?ReputationProfile;

    /**
     * Find reputation profile by artisan ID
     */
    public function findByArtisanId(UserId $artisanId): ?ReputationProfile;

    /**
     * Find top artisans by score
     */
    public function findTopArtisans(int $limit): array;

    /**
     * Find artisans eligible for micro-credit (score > 70)
     */
    public function findEligibleForMicroCredit(): array;
}
