<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Infrastructure\Services\Cache\CacheService;

/**
 * Cached decorator for UserRepository
 *
 * Implements caching for artisan profiles with 5-minute TTL
 * Requirements: 17.3
 */
class CachedUserRepository implements UserRepository
{
    public function __construct(
        private UserRepository $repository,
        private CacheService $cacheService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function save(User $user): void
    {
        $this->repository->save($user);

        // Invalidate cache for this user if it's an artisan
        if ($user->getType()->toString() === 'ARTISAN') {
            $this->cacheService->invalidateArtisanProfile($user->getId()->toString());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findById(UserId $id): ?User
    {
        // For artisans, try cache first
        $cachedProfile = $this->cacheService->getCachedArtisanProfile($id->toString());
        if ($cachedProfile !== null) {
            // Reconstruct user from cached data
            return $this->reconstructUserFromCache($cachedProfile);
        }

        $user = $this->repository->findById($id);

        // Cache artisan profiles
        if ($user && $user->getType()->toString() === 'ARTISAN') {
            $this->cacheArtisanProfile($user);
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function findByEmail(Email $email): ?User
    {
        // Email lookups are not cached as they're typically used for authentication
        return $this->repository->findByEmail($email);
    }

    /**
     * {@inheritDoc}
     */
    public function findArtisansNearLocation(GPS_Coordinates $location, float $radiusKm): array
    {
        // Location-based searches are not cached due to dynamic nature
        return $this->repository->findArtisansNearLocation($location, $radiusKm);
    }

    /**
     * {@inheritDoc}
     */
    public function findArtisansNearLocationPaginated(GPS_Coordinates $location, float $radiusKm, int $limit, int $offset): array
    {
        // Location-based searches are not cached due to dynamic nature
        return $this->repository->findArtisansNearLocationPaginated($location, $radiusKm, $limit, $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function findByType(UserType $type): array
    {
        return $this->repository->findByType($type);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(UserId $id): void
    {
        $this->repository->delete($id);

        // Invalidate cache
        $this->cacheService->invalidateArtisanProfile($id->toString());
    }

    /**
     * Cache artisan profile data
     */
    private function cacheArtisanProfile(User $user): void
    {
        if ($user->getType()->toString() !== 'ARTISAN') {
            return;
        }

        $profileData = [
            'id' => $user->getId()->toString(),
            'email' => $user->getEmail()->toString(),
            'user_type' => $user->getType()->toString(),
            'account_status' => $user->getStatus()->toString(),
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            // Add artisan-specific data if available
            'cached_at' => now()->format('Y-m-d H:i:s'),
        ];

        $this->cacheService->cacheArtisanProfile($user->getId()->toString(), $profileData);
    }

    /**
     * Reconstruct user from cached data
     *
     * Note: This is a simplified reconstruction. In a real implementation,
     * you would need to properly reconstruct the full User/Artisan object
     * with all its properties and value objects.
     */
    private function reconstructUserFromCache(array $cachedData): ?User
    {
        // For now, fall back to database lookup
        // In a full implementation, you would reconstruct the User object from cached data
        return null;
    }
}
