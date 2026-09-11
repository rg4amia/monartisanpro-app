<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeliveryTrackingController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Envoi de la position GPS périodique par le livreur en mission.
     */
    public function updateLocation(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['livreur', 'driver', 'admin'])) {
            return response()->json(['error' => 'Accès réservé aux livreurs enregistrés.'], 403);
        }

        if ($order->driver_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['error' => "Vous n'êtes pas assigné à cette livraison."], 403);
        }

        $validated = $request->validate([
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'speed_kmh'     => 'nullable|numeric|min:0',
            'heading'       => 'nullable|numeric|between:0,360',
            'battery_level' => 'nullable|integer|between:0,100',
        ]);

        try {
            $tracking = $this->orderService->recordDriverLocation(
                $order,
                $user,
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                isset($validated['speed_kmh']) ? (float) $validated['speed_kmh'] : null,
                isset($validated['heading']) ? (float) $validated['heading'] : null,
                isset($validated['battery_level']) ? (int) $validated['battery_level'] : null
            );

            return response()->json([
                'success'  => true,
                'status'   => 'success',
                'order_id' => $order->id,
                'tracking' => $tracking,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Récupère les données de suivi temps réel d'une livraison (route, ETA, positions).
     */
    public function getTracking(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        // Contrôle d'accès : Client, Livreur, Fournisseur de la commande ou Admin
        $isAuthorized = $user->role === 'admin'
            || $order->client_id === $user->id
            || $order->driver_id === $user->id
            || $order->supplier_id === $user->id;

        if (! $isAuthorized) {
            return response()->json(['error' => 'Non autorisé à consulter ce suivi.'], 403);
        }

        $trackingData = $this->orderService->getDeliveryTrackingData($order);

        return response()->json([
            'success'  => true,
            'status'   => 'success',
            'order_id' => $order->id,
            'tracking' => $trackingData,
            'data'     => $trackingData,
        ]);
    }

    /**
     * Validation du code d'enlèvement (Pickup) chez le commerçant avec photo du chargement.
     */
    public function verifyPickup(Request $request, Order $order): JsonResponse
    {
        $code = $request->input('pickup_code') ?? $request->input('code');
        if (! $code) {
            return response()->json([
                'success' => false,
                'error'   => 'Le code de retrait (pickup_code ou code) est obligatoire.',
            ], 422);
        }

        $request->validate([
            'photo' => 'nullable|image|max:10240', // Max 10MB
        ]);

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('orders/pickup', 'public');
            $photoUrl = Storage::url($path);
        }

        try {
            $updatedOrder = $this->orderService->verifyPickup($order, (string) $code, $photoUrl);

            return response()->json([
                'success' => true,
                'status'  => $updatedOrder->status,
                'message' => 'Enlèvement validé avec succès.',
                'order'   => $updatedOrder,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validation du code de réception (Delivery) sur le chantier avec photo du déchargement.
     */
    public function verifyDelivery(Request $request, Order $order): JsonResponse
    {
        $code = $request->input('reception_code') ?? $request->input('code');
        if (! $code) {
            return response()->json([
                'success' => false,
                'error'   => 'Le code de réception (reception_code ou code) est obligatoire.',
            ], 422);
        }

        $request->validate([
            'photo' => 'nullable|image|max:10240',
        ]);

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('orders/delivery', 'public');
            $photoUrl = Storage::url($path);
        }

        try {
            $updatedOrder = $this->orderService->verifyDelivery($order, (string) $code, $photoUrl);

            return response()->json([
                'success' => true,
                'status'  => $updatedOrder->status,
                'message' => 'Livraison confirmée et fonds débloqués.',
                'order'   => $updatedOrder,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Réassignation manuelle ou d'urgence d'une commande logistique.
     */
    public function reassign(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Action réservée aux administrateurs.'], 403);
        }

        $reason = $request->input('reason', 'Réassignation administrative');

        try {
            $updatedOrder = $this->orderService->reassignDriver($order, $reason);

            return response()->json([
                'success' => true,
                'status'  => $updatedOrder->status,
                'message' => 'Livreur retiré et recherche relancée.',
                'order'   => $updatedOrder,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 400);
        }
    }
}
