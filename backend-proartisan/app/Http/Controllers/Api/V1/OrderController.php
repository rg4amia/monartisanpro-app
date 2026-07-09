<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Passer commande.
     * POST /api/v1/orders
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:users,id',
            'delivery_mode' => 'required|in:pickup,delivery',
            'items' => 'required|array|min:1',
            'items.*.supplier_product_id' => 'required|exists:supplier_products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'vehicle_class' => 'nullable|string|in:moto,voiture,cargo',
            'surge_multiplier' => 'nullable|numeric|min:1.0|max:3.0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation des données échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $client = $request->user();
            $supplier = User::findOrFail($request->supplier_id);

            if ($supplier->role !== 'fournisseur') {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur sélectionné n\'est pas un fournisseur.',
                ], 422);
            }

            $order = $this->orderService->createOrder(
                $client,
                $supplier,
                $request->items,
                $request->delivery_mode,
                $request->input('vehicle_class', 'moto'),
                (float) $request->input('surge_multiplier', 1.0)
            );

            return response()->json([
                'success' => true,
                'message' => 'Commande créée et payée avec succès en compte séquestre.',
                'data' => $order->load('items.product'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Afficher le détail d'une commande.
     * GET /api/v1/orders/{order}
     */
    public function show(Order $order, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($order->client_id !== $user->id && $order->supplier_id !== $user->id && $order->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $order->load('items.product', 'client', 'supplier.fournisseurAgree', 'driver'),
        ]);
    }

    /**
     * Liste des commandes de l'utilisateur connecté selon son rôle.
     * GET /api/v1/orders
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::query()->with('items.product');

        if ($user->role === 'client') {
            $query->where('client_id', $user->id);
        } elseif ($user->role === 'fournisseur') {
            $query->where('supplier_id', $user->id);
        } elseif ($user->role === 'driver') {
            $query->where('driver_id', $user->id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Rôle non autorisé pour l\'historique des commandes.',
            ], 403);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Marquer la commande préparée par le fournisseur.
     * POST /api/v1/orders/{order}/prepared
     */
    public function markPrepared(Order $order, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($order->supplier_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Seul le fournisseur peut préparer cette commande.',
            ], 403);
        }

        try {
            $this->orderService->markAsPrepared($order);

            return response()->json([
                'success' => true,
                'message' => 'Commande marquée comme préparée.',
                'data' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validation du code de retrait (chez le fournisseur).
     * POST /api/v1/orders/{order}/verify-pickup
     */
    public function verifyPickup(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Le code de validation est requis.',
            ], 422);
        }

        // Vérification des droits d'appel (fournisseur ou livreur assigné)
        $user = $request->user();
        if ($order->supplier_id !== $user->id && $order->driver_id !== $user->id && $order->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        try {
            $this->orderService->verifyPickup($order, $request->code);

            return response()->json([
                'success' => true,
                'message' => 'Prise en charge / Retrait validé avec succès. Fonds matériels libérés.',
                'data' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validation du code de réception par le client final.
     * POST /api/v1/orders/{order}/verify-delivery
     */
    public function verifyDelivery(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Le code de réception est requis.',
            ], 422);
        }

        // Seul le livreur (driver) assigné ou le client peut appeler cet endpoint
        $user = $request->user();
        if ($order->driver_id !== $user->id && $order->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        try {
            $this->orderService->verifyDelivery($order, $request->code);

            return response()->json([
                'success' => true,
                'message' => 'Livraison finalisée avec succès. Fonds de livraison libérés au livreur.',
                'data' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
