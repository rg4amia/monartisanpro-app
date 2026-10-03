<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminExportService;
use App\Services\Admin\AdminTerritoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminTerritoryController extends Controller
{
    public function __construct(
        private AdminTerritoryService $territoryService
    ) {}

    /**
     * Retourne les indicateurs, la matrice par zone et la liste dynamique en JSON.
     */
    public function stats(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return response()->json([
            'summary' => $this->territoryService->getTerritorySummary($filters['district'], $filters['commune'], $filters),
            'entities' => $this->territoryService->getTerritoryEntities($filters, (int) $request->query('per_page', 15)),
            'breakdowns' => $this->territoryService->getTerritoryBreakdowns($filters['district'], $filters['commune']),
            'matrix' => $this->territoryService->getZoneMatrix($filters),
            'filters' => $filters,
        ]);
    }

    /**
     * Export CSV de la synthèse par zone ou du tableau détaillé, avec les
     * filtres de l'écran (Règle d'or 20).
     */
    public function export(Request $request, AdminExportService $exports): StreamedResponse
    {
        $filters = $this->filters($request);
        $options = $request->validate([
            'vue' => ['nullable', Rule::in(['synthese', 'detail'])],
            'niveau' => ['nullable', Rule::in(['districts', 'communes'])],
        ]);
        $view = $options['vue'] ?? 'synthese';

        if ($view === 'detail') {
            [$headers, $rows, $count] = $this->territoryService->detailExport($filters);

            return $exports->streamTable('territoires_detail', $headers, $rows, $request->query(), $count);
        }

        // Niveau affiché à l'écran : les communes dans la vue Grand Abidjan, sinon les districts.
        $level = $options['niveau']
            ?? ($filters['commune'] || $filters['district'] === 'abidjan' ? 'communes' : 'districts');
        [$headers, $rows] = $this->territoryService->synthesisExport($filters, $level);

        return $exports->streamTable('territoires_synthese', $headers, $rows, $request->query(), count($rows));
    }

    /**
     * Filtres validés : une valeur inconnue est refusée (422) plutôt que de
     * retomber en silence sur « tout », ce qui affichait une liste sans rapport.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'district' => ['nullable', 'string', Rule::in([...array_keys(AdminTerritoryService::DISTRICTS), AdminTerritoryService::UNLOCATED])],
            'commune' => ['nullable', 'string', Rule::in(array_keys(AdminTerritoryService::ABIDJAN_COMMUNES))],
            'entity_type' => ['nullable', 'string', Rule::in(AdminTerritoryService::acceptedEntityTypes())],
            'types' => ['nullable', 'string', 'max:100'],
            'mission_status' => ['nullable', 'string', Rule::in(AdminTerritoryService::MISSION_STATUS_FILTERS)],
            'kyc' => ['nullable', 'string', Rule::in(AdminTerritoryService::KYC_FILTERS)],
            'period' => ['nullable', 'string', Rule::in(AdminTerritoryService::PERIOD_FILTERS)],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(AdminTerritoryService::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->territoryService->normalizeFilters($validated);
    }
}
