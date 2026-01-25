<?php

namespace App\Infrastructure\Services\Cache;

/**
 * Service for caching static data like trade categories and statuses
 *
 * Requirements: 17.3
 */
class StaticDataCacheService
{
    public function __construct(
        private CacheService $cacheService
    ) {}

    /**
     * Get trade categories with caching
     */
    public function getTradeCategories(): array
    {
        return $this->cacheService->remember(
            CacheService::TRADE_CATEGORIES_KEY,
            CacheService::TRADE_CATEGORIES_TTL,
            function () {
                return [
                    [
                        'value' => 'PLUMBER',
                        'label' => 'Plombier',
                        'description' => 'Installation et réparation de plomberie',
                    ],
                    [
                        'value' => 'ELECTRICIAN',
                        'label' => 'Électricien',
                        'description' => 'Installation et réparation électrique',
                    ],
                    [
                        'value' => 'MASON',
                        'label' => 'Maçon',
                        'description' => 'Travaux de maçonnerie et construction',
                    ],
                ];
            }
        );
    }

    /**
     * Get mission statuses with caching
     */
    public function getMissionStatuses(): array
    {
        return $this->cacheService->remember(
            CacheService::MISSION_STATUSES_KEY,
            CacheService::STATIC_DATA_TTL,
            function () {
                return [
                    [
                        'value' => 'OPEN',
                        'label' => 'Ouverte',
                        'description' => 'Mission ouverte aux devis',
                    ],
                    [
                        'value' => 'QUOTED',
                        'label' => 'Devis reçus',
                        'description' => 'Mission avec des devis en attente',
                    ],
                    [
                        'value' => 'ACCEPTED',
                        'label' => 'Acceptée',
                        'description' => 'Mission avec devis accepté',
                    ],
                    [
                        'value' => 'CANCELLED',
                        'label' => 'Annulée',
                        'description' => 'Mission annulée',
                    ],
                ];
            }
        );
    }

    /**
     * Get devis statuses with caching
     */
    public function getDevisStatuses(): array
    {
        return $this->cacheService->remember(
            CacheService::DEVIS_STATUSES_KEY,
            CacheService::STATIC_DATA_TTL,
            function () {
                return [
                    [
                        'value' => 'PENDING',
                        'label' => 'En attente',
                        'description' => 'Devis en attente de réponse',
                    ],
                    [
                        'value' => 'ACCEPTED',
                        'label' => 'Accepté',
                        'description' => 'Devis accepté par le client',
                    ],
                    [
                        'value' => 'REJECTED',
                        'label' => 'Rejeté',
                        'description' => 'Devis rejeté par le client',
                    ],
                ];
            }
        );
    }

    /**
     * Warm up all static data caches
     */
    public function warmUpCaches(): void
    {
        $this->getTradeCategories();
        $this->getMissionStatuses();
        $this->getDevisStatuses();
    }

    /**
     * Clear all static data caches
     */
    public function clearCaches(): void
    {
        $this->cacheService->clearStaticDataCache();
    }
}
