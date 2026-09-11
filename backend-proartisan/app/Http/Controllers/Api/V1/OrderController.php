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
    private \App\Services\DeliveryPricingService $pricingService;

    public function __construct(
        private OrderService $orderService,
        ?\App\Services\DeliveryPricingService $pricingService = null
    ) {
        $this->pricingService = $pricingService ?? app(\App\Services\DeliveryPricingService::class);
    }

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
            'promo_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation des données échouée.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = $request->user();
        $lock = null;

        try {
            if (! app()->environment('testing')) {
                $lockKey = 'create_order_lock_' . $client->id;
                $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 5);

                if (! $lock->get()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Une création de commande est déjà en cours. Veuillez patienter un instant.',
                    ], 429);
                }
            }

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
                (float) $request->input('surge_multiplier', 1.0),
                $request->input('promo_code')
            );

            return response()->json([
                'success' => true,
                'message' => 'Commande créée et payée avec succès en compte séquestre.',
                'data' => $order->load('items.product'),
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Order creation error: ' . $e->getMessage(), [
                'client_id' => $client?->id,
                'supplier_id' => $request->supplier_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } finally {
            if ($lock) {
                try {
                    $lock->release();
                } catch (\Exception $e) {
                    // Ignore lock release exception if already expired
                }
            }
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

        $query = Order::query()->with(['items.product', 'client', 'supplier.fournisseurAgree']);

        if ($user->role === 'client') {
            $query->where('client_id', $user->id);
        } elseif ($user->role === 'fournisseur') {
            $query->where('supplier_id', $user->id);
        } elseif (in_array($user->role, ['driver', 'livreur'])) {
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
    public function markPrepared(Order $order, Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if ($order->supplier_id !== $user->id) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['message' => 'Seul le fournisseur peut préparer cette commande.']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Seul le fournisseur peut préparer cette commande.',
            ], 403);
        }

        try {
            $this->orderService->markAsPrepared($order);

            if ($request->header('X-Inertia')) {
                return back();
            }

            return response()->json([
                'success' => true,
                'message' => 'Commande marquée comme préparée.',
                'data' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['message' => $e->getMessage()]);
            }
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
    public function verifyPickup(Request $request, Order $order): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['code' => 'Le code de validation est requis.']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Le code de validation est requis.',
            ], 422);
        }

        // Vérification des droits d'appel (fournisseur ou livreur assigné)
        $user = $request->user();
        if ($order->supplier_id !== $user->id && $order->driver_id !== $user->id && $order->client_id !== $user->id) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['message' => 'Non autorisé.']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        try {
            $this->orderService->verifyPickup($order, $request->code, $request->input('photo_url'));

            if ($request->header('X-Inertia')) {
                return back();
            }

            return response()->json([
                'success' => true,
                'message' => 'Prise en charge / Retrait validé avec succès. Fonds matériels libérés.',
                'data' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['message' => $e->getMessage()]);
            }
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
            'photo_url' => 'nullable|string',
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
            $this->orderService->verifyDelivery($order, $request->code, $request->input('photo_url'));

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

    /**
     * Déclaration d'un litige sur la commande par le client (dans le délai paramétré).
     * POST /api/v1/orders/{order}/dispute
     */
    public function dispute(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Une raison valide est requise pour ouvrir un litige.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $disputedOrder = $this->orderService->openOrderDispute($order, $request->user(), $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Litige déclaré avec succès sur la commande.',
                'data' => $disputedOrder->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Majoration des frais d'attente livreur.
     * POST /api/v1/orders/{order}/waiting-surge
     */
    public function applyWaitingSurge(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'waiting_minutes' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Le nombre de minutes d\'attente est requis.',
            ], 422);
        }

        try {
            $updatedOrder = $this->orderService->applyWaitingSurgeFee($order, (int) $request->waiting_minutes);

            return response()->json([
                'success' => true,
                'message' => 'Frais d\'attente majores appliqués.',
                'data' => $updatedOrder->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Estimer le coût de livraison dynamique et le surge pricing.
     * POST /api/v1/deliveries/estimate ou POST /api/v1/orders/estimate-delivery
     */
    public function estimateDelivery(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'nullable|exists:users,id',
            'client_latitude' => 'nullable|numeric|between:-90,90',
            'client_longitude' => 'nullable|numeric|between:-180,180',
            'from_latitude' => 'nullable|numeric|between:-90,90',
            'from_longitude' => 'nullable|numeric|between:-180,180',
            'to_latitude' => 'nullable|numeric|between:-90,90',
            'to_longitude' => 'nullable|numeric|between:-180,180',
            'vehicle_class' => 'nullable|string|in:moto,voiture,cargo',
            'surge_multiplier' => 'nullable|numeric|min:1.0|max:3.0',
            'items' => 'nullable|array',
            'items.*.supplier_product_id' => 'nullable|exists:supplier_products,id',
            'items.*.name' => 'nullable|string',
            'items.*.quantity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données d\'estimation invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Déterminer le point d'origine (from)
        $from = null;
        if ($request->filled('from_latitude') && $request->filled('from_longitude')) {
            $from = [
                'lat' => (float) $request->from_latitude,
                'lng' => (float) $request->from_longitude,
            ];
        } elseif ($request->filled('supplier_id')) {
            $supplier = User::find($request->supplier_id);
            $from = $supplier?->fournisseurAgree?->getPositionCoords() ?? $supplier?->getPositionCoords();
        }

        // Déterminer le point de destination (to)
        $to = null;
        if ($request->filled('to_latitude') && $request->filled('to_longitude')) {
            $to = [
                'lat' => (float) $request->to_latitude,
                'lng' => (float) $request->to_longitude,
            ];
        } elseif ($request->filled('client_latitude') && $request->filled('client_longitude')) {
            $to = [
                'lat' => (float) $request->client_latitude,
                'lng' => (float) $request->client_longitude,
            ];
        } else {
            $client = $request->user();
            $to = $client?->getPositionCoords();
        }

        // Normaliser les articles pour la recommandation intelligente de véhicule
        $rawItems = $request->input('items', []);
        $formattedItems = [];
        foreach ($rawItems as $item) {
            $name = $item['name'] ?? null;
            if (!$name && !empty($item['supplier_product_id'])) {
                $prod = \App\Models\SupplierProduct::find($item['supplier_product_id']);
                $name = $prod?->name ?? '';
            }
            $formattedItems[] = [
                'name' => $name ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
            ];
        }

        $estimate = $this->pricingService->estimateFare([
            'from' => $from,
            'to' => $to,
            'vehicle_class' => $request->input('vehicle_class'),
            'surge_multiplier' => $request->filled('surge_multiplier') ? (float) $request->surge_multiplier : null,
            'items' => $formattedItems,
        ]);

        return response()->json([
            'success' => true,
            'data' => $estimate,
        ]);
    }
}

