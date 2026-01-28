<?php

namespace App\Domain\Reputation\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Reputation\Models\Rating\Rating;
use App\Domain\Reputation\Models\ValueObjects\RatingId;

/**
 * Repository interface for Rating aggregate
 */
interface RatingRepository
{
    /**
     * Save a rating
     */
    public function save(Rating $rating): void;

    /**
     * Find rating by ID
     */
    public function findById(RatingId $id): ?Rating;

    /**
     * Find rating by mission ID
     */
    public function findByMissionId(MissionId $missionId): ?Rating;

    /**
     * Find all ratings for an artisan
     */
    public function findByArtisanId(UserId $artisanId): array;

    /**
     * Find all ratings by a client
     */
    public function findByClientId(UserId $clientId): array;

    /**
     * Calculate average rating for an artisan
     */
    public function getAverageRatingForArtisan(UserId $artisanId): float;

    /**
     * Count total ratings for an artisan
     */
    public function countRatingsForArtisan(UserId $artisanId): int;
}
