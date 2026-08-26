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

        if (!$user->payment_phone) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'payment_phone' => ['required', 'string', 'max:20'],
                'preferred_payment_provider' => ['required', 'in:wave,orange_money'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez associer votre numéro Mobile Money (Wave ou OM) pour accepter cette course.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user->update([
                'payment_phone' => $request->input('payment_phone'),
                'preferred_payment_provider' => $request->input('preferred_payment_provider'),
            ]);
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
