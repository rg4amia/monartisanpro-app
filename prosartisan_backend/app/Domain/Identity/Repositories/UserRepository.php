<?php

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\UserId;
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
  * Delete a user
  *
  * @param UserId $id User ID
  * @return void
  */
 public function delete(UserId $id): void;
}
