<?php

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;

/**
 * Repository interface for User aggregate
 *
 * Provides persistence operations for User entities
 */
interface UserRepository
{
    /**
     * Save a user entity
     *
     * @param User $user User to save
     * @return void
     */
    public function save(User $user): void;

    /**
     * Find a user by ID
     *
     * @param UserId $id User ID
     * @return User|null User entity or null if not found
     */
    public function findById(UserId $id): ?User;

    /**
     * Find a user by email
     *
     * @param Email $email User email
     * @return User|null User entity or null if not found
     */
    public function findByEmail(Email $email): ?User;

    /**
     * Find artisans near a location
     *
     * @param GPS_Coordinates $location Center location
     * @param float $radiusKm Search radius in kilometers
     * @return array Array of Artisan entities
     */
    public function findArtisansNearLocation(GPS_Coordinates $location, float $radiusKm): array;

    /**
     * Find suppliers near a location
     *
     * @param GPS_Coordinates $location Center location
     * @param float $radiusKm Search radius in kilometers
     * @return array Array of Fournisseur entities
     */
    public function findSuppliersNearLocation(GPS_Coordinates $location, float $radiusKm): array;

    /**
     * Find artisans near a location with pagination
     *
     * @param GPS_Coordinates $location Center location
     * @param float $radiusKm Search radius in kilometers
     * @param int $limit Number of items per page
     * @param int $offset Offset for pagination
     * @return array ['artisans' => array, 'total' => int]
     */
    public function findArtisansNearLocationPaginated(GPS_Coordinates $location, float $radiusKm, int $limit, int $offset): array;

    /**
     * Find users by type
     *
     * @param UserType $type User type
     * @return array Array of User entities
     */
    public function findByType(UserType $type): array;

    /**
     * Delete a user
     *
     * @param UserId $id User ID
     * @return void
     */
    public function delete(UserId $id): void;
}
