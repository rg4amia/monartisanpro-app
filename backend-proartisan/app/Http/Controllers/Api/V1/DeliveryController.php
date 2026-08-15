<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Liste des courses disponibles pour les livreurs.
     * GET /api/v1/deliveries/available
     */
    public function available(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['driver', 'livreur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les livreurs peuvent consulter les courses disponibles.',
            ], 403);
        }

        $orders = Order::where('status', 'searching_driver')
            ->where('delivery_mode', 'delivery')
            ->with(['items.product', 'supplier.fournisseurAgree', 'client'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
            'meta' => [
                'count' => $orders->count(),
            ],
        ]);
    }

    /**
     * Accepter une course de livraison.
     * POST /api/v1/deliveries/{order}/accept
     */
    public function accept(Order $order, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['driver', 'livreur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les livreurs peuvent accepter une course.',
            ], 403);
        }

        try {
            $this->orderService->assignDriver($order, $user);

            return response()->json([
                'success' => true,
                'message' => 'Course acceptée avec succès. Veuillez vous rendre chez le fournisseur.',
                'data' => $order->fresh()->load('items.product', 'supplier.fournisseurAgree', 'client'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
