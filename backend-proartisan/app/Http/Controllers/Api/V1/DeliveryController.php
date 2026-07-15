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
        if ($user->role !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les livreurs peuvent consulter les courses disponibles.',
            ], 403);
        }

        $driverCoords = $user->getPositionCoords();
        $isExtended = false;

        if (config('database.default') === 'sqlite' || !$driverCoords) {
            // SQLite ou livreur sans position -> pas de filtrage spatial
            $orders = Order::where('status', 'searching_driver')
                ->where('delivery_mode', 'delivery')
                ->with('items.product', 'supplier.fournisseurAgree', 'client')
                ->orderBy('created_at', 'asc')
                ->get();
        } else {
            $lng = $driverCoords['lng'];
            $lat = $driverCoords['lat'];

            // 1. Recherche dans la zone locale (10 km du fournisseur ET du client)
            $orders = Order::where('status', 'searching_driver')
                ->where('delivery_mode', 'delivery')
                ->whereHas('client', function ($q) use ($lng, $lat) {
                    $q->whereRaw("ST_Distance_Sphere(position, ST_SRID(POINT(?, ?), 4326)) <= 10000", [$lng, $lat]);
                })
                ->whereHas('supplier.fournisseurAgree', function ($q) use ($lng, $lat) {
                    $q->whereRaw("ST_Distance_Sphere(position, ST_SRID(POINT(?, ?), 4326)) <= 10000", [$lng, $lat]);
                })
                ->with('items.product', 'supplier.fournisseurAgree', 'client')
                ->orderBy('created_at', 'asc')
                ->get();

            // 2. Si aucun résultat local, on étend la recherche à toutes les zones
            if ($orders->isEmpty()) {
                $isExtended = true;
                $orders = Order::where('status', 'searching_driver')
                    ->where('delivery_mode', 'delivery')
                    ->with('items.product', 'supplier.fournisseurAgree', 'client')
                    ->orderBy('created_at', 'asc')
                    ->get();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $orders,
            'meta' => [
                'is_extended' => $isExtended,
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
        if ($user->role !== 'driver') {
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
