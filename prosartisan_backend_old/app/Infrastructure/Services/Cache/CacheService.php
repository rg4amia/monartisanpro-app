<?php

namespace App\Infrastructure\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized caching service for the ProSartisan platform
 *
 * Implements caching strategy with TTL for frequently accessed data
 * Requirements: 17.3
 */
class CacheService
{
    // Cache TTL constants (in seconds)
    public const ARTISAN_PROFILE_TTL = 300; // 5 minutes

    public const TRADE_CATEGORIES_TTL = 3600; // 1 hour

    public const STATIC_DATA_TTL = 3600; // 1 hour

    // Cache key prefixes
    public const ARTISAN_PROFILE_PREFIX = 'artisan_profile:';

    public const TRADE_CATEGORIES_KEY = 'trade_categories';

    public const MISSION_STATUSES_KEY = 'mission_statuses';

    public const DEVIS_STATUSES_KEY = 'devis_statuses';

    /**
     * Cache artisan profile data
     */
    public function cacheArtisanProfile(string $artisanId, array $profileData): void
    {
        $key = self::ARTISAN_PROFILE_PREFIX.$artisanId;
        Cache::put($key, $profileData, self::ARTISAN_PROFILE_TTL);
    }

    /**
     * Get cached artisan profile
     */
    public function getCachedArtisanProfile(string $artisanId): ?array
    {
        $key = self::ARTISAN_PROFILE_PREFIX.$artisanId;

        return Cache::get($key);
    }

    /**
     * Invalidate artisan profile cache
     */
    public function invalidateArtisanProfile(string $artisanId): void
    {
        $key = self::ARTISAN_PROFILE_PREFIX.$artisanId;
        Cache::forget($key);
    }

    /**
     * Cache trade categories
     */
    public function cacheTradeCategories(array $categories): void
    {
        Cache::put(self::TRADE_CATEGORIES_KEY, $categories, self::TRADE_CATEGORIES_TTL);
    }

    /**
     * Get cached trade categories
     */
    public function getCachedTradeCategories(): ?array
    {
        return Cache::get(self::TRADE_CATEGORIES_KEY);
    }

    /**
     * Cache mission statuses
     */
    public function cacheMissionStatuses(array $statuses): void
    {
        Cache::put(self::MISSION_STATUSES_KEY, $statuses, self::STATIC_DATA_TTL);
    }

    /**
     * Get cached mission statuses
     */
    public function getCachedMissionStatuses(): ?array
    {
        return Cache::get(self::MISSION_STATUSES_KEY);
    }

    /**
     * Cache devis statuses
     */
    public function cacheDevisStatuses(array $statuses): void
    {
        Cache::put(self::DEVIS_STATUSES_KEY, $statuses, self::STATIC_DATA_TTL);
    }

    /**
     * Get cached devis statuses
     */
    public function getCachedDevisStatuses(): ?array
    {
        return Cache::get(self::DEVIS_STATUSES_KEY);
    }

    /**
     * Remember a value in cache with callback
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Clear all cached data
     */
    public function clearAll(): void
    {
        Cache::flush();
    }

    /**
     * Clear artisan-related cache
     */
    public function clearArtisanCache(): void
    {
        // Get all keys with artisan profile prefix and delete them
        $keys = Cache::getRedis()->keys(self::ARTISAN_PROFILE_PREFIX.'*');
        if (! empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }

    /**
     * Clear static data cache
     */
    public function clearStaticDataCache(): void
    {
        Cache::forget(self::TRADE_CATEGORIES_KEY);
        Cache::forget(self::MISSION_STATUSES_KEY);
        Cache::forget(self::DEVIS_STATUSES_KEY);
    }
}
