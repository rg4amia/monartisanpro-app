<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Services\Cache\StaticDataCacheService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for serving cached static data
 *
 * Provides endpoints for trade categories and other static data
 * Requirements: 17.3
 */
class StaticDataController extends Controller
{
    public function __construct(
        private StaticDataCacheService $staticDataCacheService
    ) {}

    /**
     * Get trade categories
     *
     * GET /api/v1/static/trade-categories
     */
    public function tradeCategories(): JsonResponse
    {
        $categories = $this->staticDataCacheService->getTradeCategories();

        return response()->json([
            'data' => $categories,
            'meta' => [
                'cached' => true,
                'ttl_seconds' => 3600,
            ],
        ]);
    }

    /**
     * Get mission statuses
     *
     * GET /api/v1/static/mission-statuses
     */
    public function missionStatuses(): JsonResponse
    {
        $statuses = $this->staticDataCacheService->getMissionStatuses();

        return response()->json([
            'data' => $statuses,
            'meta' => [
                'cached' => true,
                'ttl_seconds' => 3600,
            ],
        ]);
    }

    /**
     * Get devis statuses
     *
     * GET /api/v1/static/devis-statuses
     */
    public function devisStatuses(): JsonResponse
    {
        $statuses = $this->staticDataCacheService->getDevisStatuses();

        return response()->json([
            'data' => $statuses,
            'meta' => [
                'cached' => true,
                'ttl_seconds' => 3600,
            ],
        ]);
    }

    /**
     * Get all static data
     *
     * GET /api/v1/static/all
     */
    public function all(): JsonResponse
    {
        return response()->json([
            'data' => [
                'trade_categories' => $this->staticDataCacheService->getTradeCategories(),
                'mission_statuses' => $this->staticDataCacheService->getMissionStatuses(),
                'devis_statuses' => $this->staticDataCacheService->getDevisStatuses(),
            ],
            'meta' => [
                'cached' => true,
                'ttl_seconds' => 3600,
            ],
        ]);
    }
}
