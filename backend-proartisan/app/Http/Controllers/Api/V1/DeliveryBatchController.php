<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DeliveryBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeliveryBatchController extends Controller
{
    public function __construct(private DeliveryBatchService $batchService) {}

    /**
     * Liste des tournées groupées (lots multi-drop) disponibles.
     * GET /api/v1/deliveries/batches
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['driver', 'livreur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les livreurs peuvent consulter les tournées groupées.',
            ], 403);
        }

        $batches = $this->batchService->findAvailableBatches($user);

        return response()->json([
            'success' => true,
            'data' => $batches,
            'meta' => [
                'count' => count($batches),
            ],
        ]);
    }

    /**
     * Accepter une tournée groupée (lot multi-drop).
     * POST /api/v1/deliveries/batch-accept
     */
    public function accept(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['driver', 'livreur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les livreurs peuvent accepter une tournée.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'order_ids' => ['required', 'array', 'min:2', 'max:5'],
            'order_ids.*' => ['required', 'integer', 'exists:orders,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de tournée invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->batchService->acceptBatch($user, $request->input('order_ids'));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Récupère la feuille de route active de la tournée multi-drop du livreur connecté.
     * GET /api/v1/deliveries/active-tour
     */
    public function activeTour(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['driver', 'livreur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les livreurs peuvent consulter leur tournée active.',
            ], 403);
        }

        $tour = $this->batchService->getActiveTour($user);

        return response()->json([
            'success' => true,
            'data' => $tour,
        ]);
    }
}
