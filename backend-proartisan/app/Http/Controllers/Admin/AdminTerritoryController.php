<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminTerritoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTerritoryController extends Controller
{
    public function __construct(
        private AdminTerritoryService $territoryService
    ) {}

    /**
     * Retourne les indicateurs et la liste dynamique pour une zone en JSON.
     */
    public function stats(Request $request): JsonResponse
    {
        $district = $request->query('district');
        $commune = $request->query('commune');
        $filters = [
            'entity_type' => $request->query('entity_type', 'all'),
            'district' => $district,
            'commune' => $commune,
            'search' => $request->query('search', ''),
        ];

        $summary = $this->territoryService->getTerritorySummary($district, $commune);
        $entities = $this->territoryService->getTerritoryEntities($filters, (int) $request->query('per_page', 15));

        return response()->json([
            'summary' => $summary,
            'entities' => $entities,
        ]);
    }
}
