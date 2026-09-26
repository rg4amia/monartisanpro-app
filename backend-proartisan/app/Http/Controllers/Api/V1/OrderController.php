<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\DeliveryPricingService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    private DeliveryPricingService $pricingService;

    public function __construct(
        private OrderService $orderService,
        ?DeliveryPricingService $pricingService = null
    ) {
        $this->pricingService = $pricingService ?? app(DeliveryPricingService::class);
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
            'address_id' => 'required_if:delivery_mode,delivery|nullable|exists:addresses,id',
            'items' => 'required|array|min:1',
            'items.*.supplier_product_id' => 'required|exists:supplier_products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'vehicle_class' => 'nullable|string|in:moto,voiture,cargo',
            'surge_multiplier' => 'nullable|numeric|min:1.0|max:3.0',
            'promo_code' => 'nullable|string|max:50',
            'mission_id' => 'nullable|exists:missions,id',
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

        $address = null;
        if ($request->filled('address_id')) {
            $address = Address::find($request->address_id);
            if (! $address || $address->user_id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Adresse de livraison invalide.',
                ], 422);
            }
        }

        try {
            if (! app()->environment('testing')) {
                $lockKey = 'create_order_lock_'.$client->id;
                $lock = Cache::lock($lockKey, 5);

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
                $request->input('promo_code'),
                $address,
                $request->filled('mission_id') ? (int) $request->input('mission_id') : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Commande créée et payée avec succès en compte séquestre.',
                'data' => $order->load('items.product'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Order creation error: '.$e->getMessage(), [
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

        $order->load('items.product', 'client', 'supplier.fournisseurAgree', 'driver');

        // Les codes sont masqués par défaut sur le modèle ; on ne réexpose que
        // ceux dont cet acteur a besoin pour contrôler la remise.
        return response()->json([
            'success' => true,
            'data' => array_merge(
                $order->toArray(),
                $order->codesVisibleTo($user),
            ),
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
    public function markPrepared(Order $order, Request $request): JsonResponse|RedirectResponse
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
    public function verifyPickup(Request $request, Order $order): JsonResponse|RedirectResponse
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

        // Une seule définition des acteurs habilités, portée par le modèle :
        // le fournisseur qui remet, et selon le mode le livreur ou le client
        // qui récupère. Le client n'a pas à valider un enlèvement livreur.
        if (! $order->canValidatePickup($request->user())) {
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

        // Le livreur assigné qui saisit le code du client, ou le client qui
        // confirme lui-même la réception depuis son appareil.
        if (! $order->canValidateDelivery($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        try {
            $this->orderService->verifyDelivery($order, $request->code, $request->input('photo_url'));
            $order = $order->fresh();

            return response()->json([
                'success' => true,
                'message' => $this->orderService->deliveryFareMessage($order),
                'delivery_fare' => $order->delivery_fare,
                'data' => $order,
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
        $user = $request->user();
        if ($user->role !== 'admin' && $order->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Seul le livreur assigné à cette commande peut déclarer un temps d\'attente.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            // Plafond de 2h par déclaration : au-delà, un livreur assigné
            // pouvait s'auto-attribuer un gain arbitrairement élevé (aucune
            // borne supérieure ne bridait auparavant ce champ), crédité tel
            // quel sur son wallet_mo à la livraison.
            'waiting_minutes' => 'required|integer|min:1|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Le nombre de minutes d\'attente est requis et limité à 120 minutes par déclaration.',
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
            'address_id' => 'nullable|exists:addresses,id',
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
        } elseif ($request->filled('address_id')) {
            $address = Address::find($request->address_id);
            $to = ($address && $address->user_id === $request->user()->id)
                ? ($address->getPositionCoords() ?? $request->user()?->getPositionCoords())
                : $request->user()?->getPositionCoords();
        } else {
            $client = $request->user();
            $to = $client?->getPositionCoords();
        }

        // Normaliser les articles pour la recommandation intelligente de véhicule
        $rawItems = $request->input('items', []);
        $formattedItems = [];
        foreach ($rawItems as $item) {
            $name = $item['name'] ?? null;
            if (! $name && ! empty($item['supplier_product_id'])) {
                $prod = SupplierProduct::find($item['supplier_product_id']);
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

    /**
     * Estimer le coût de livraison groupé multi-fournisseurs.
     * POST /api/v1/orders/multi-estimate
     */
    public function estimateMultiDelivery(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_latitude' => 'nullable|numeric|between:-90,90',
            'client_longitude' => 'nullable|numeric|between:-180,180',
            'packages' => 'required|array|min:1',
            'packages.*.supplier_id' => 'required|exists:users,id',
            'packages.*.vehicle_class' => 'nullable|string|in:moto,voiture,cargo',
            'packages.*.delivery_mode' => 'nullable|string|in:delivery,pickup',
            'packages.*.items' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données d\'estimation multi-fournisseurs invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = $request->user();
        $to = null;
        if ($request->filled('client_latitude') && $request->filled('client_longitude')) {
            $to = [
                'lat' => (float) $request->client_latitude,
                'lng' => (float) $request->client_longitude,
            ];
        } else {
            $to = $client?->getPositionCoords();
        }

        $results = [];
        $totalDeliveryCost = 0;

        foreach ($request->packages as $pkg) {
            $supplier = User::find($pkg['supplier_id']);
            $deliveryMode = $pkg['delivery_mode'] ?? 'delivery';
            if ($deliveryMode === 'pickup') {
                $results[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->fournisseurAgree?->nom_boutique ?? $supplier->name,
                    'delivery_mode' => 'pickup',
                    'delivery_cost' => 0,
                    'distance_km' => 0,
                    'duration_minutes' => 0,
                ];

                continue;
            }

            $from = $supplier?->fournisseurAgree?->getPositionCoords() ?? $supplier?->getPositionCoords();
            $estimate = $this->pricingService->estimateFare([
                'from' => $from,
                'to' => $to,
                'vehicle_class' => $pkg['vehicle_class'] ?? 'moto',
                'surge_multiplier' => 1.0,
            ]);

            $cost = (int) ($estimate['delivery_cost'] ?? 0);
            $totalDeliveryCost += $cost;

            $results[] = [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->fournisseurAgree?->nom_boutique ?? $supplier->name,
                'delivery_mode' => 'delivery',
                'delivery_cost' => $cost,
                'distance_km' => $estimate['distance_km'] ?? 0,
                'duration_minutes' => $estimate['duration_minutes'] ?? 0,
                'vehicle_class' => $estimate['vehicle_class'] ?? 'moto',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'packages' => $results,
                'total_delivery_cost' => $totalDeliveryCost,
            ],
        ]);
    }

    /**
     * Création et paiement d'un panier multi-fournisseurs.
     * POST /api/v1/orders/multi-store
     */
    public function multiStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address_id' => 'nullable|exists:addresses,id',
            'packages' => 'required|array|min:1',
            'packages.*.supplier_id' => 'required|exists:users,id',
            'packages.*.delivery_mode' => 'required|in:delivery,pickup',
            'packages.*.vehicle_class' => 'nullable|in:moto,voiture,cargo',
            'packages.*.surge_multiplier' => 'nullable|numeric|min:1.0|max:3.0',
            'packages.*.items' => 'required|array|min:1',
            'packages.*.items.*.supplier_product_id' => 'required|exists:supplier_products,id',
            'packages.*.items.*.quantity' => 'required|integer|min:1',
            'promo_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données du panier multi-fournisseurs invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $client = $request->user();
        $lock = null;

        $address = null;
        if ($request->filled('address_id')) {
            $address = Address::find($request->address_id);
            if (! $address || $address->user_id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Adresse de livraison invalide.',
                ], 422);
            }
        } elseif (collect($request->packages)->contains(fn ($pkg) => ($pkg['delivery_mode'] ?? 'delivery') === 'delivery')) {
            return response()->json([
                'success' => false,
                'message' => 'Une adresse de livraison est requise.',
            ], 422);
        }

        try {
            if (! app()->environment('testing')) {
                $lockKey = 'create_order_lock_'.$client->id;
                $lock = Cache::lock($lockKey, 10);

                if (! $lock->get()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Une commande est déjà en cours de création. Veuillez patienter.',
                    ], 429);
                }
            }

            $result = $this->orderService->createMultiSupplierOrders(
                $client,
                $request->packages,
                $request->input('promo_code'),
                $address
            );

            return response()->json([
                'success' => true,
                'message' => 'Commandes multi-fournisseurs créées et payées avec succès.',
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Multi-order creation error: '.$e->getMessage(), [
                'client_id' => $client?->id,
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
                }
            }
        }
    }
}
