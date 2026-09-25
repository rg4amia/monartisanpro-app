<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryBatchService
{
    public function __construct(
        private OsrmRoutingService $osrmService,
        private OrderService $orderService,
    ) {}

    /**
     * Recherche les tournées groupées (lots multi-drop) disponibles pour les livreurs.
     * Regroupe les commandes en attente de livreur par quincaillerie.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAvailableBatches(?User $driver = null): array
    {
        $orders = Order::where('status', 'searching_driver')
            ->where('delivery_mode', 'delivery')
            ->with(['supplier.fournisseurAgree', 'client', 'address', 'items.product'])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($orders->count() < 2) {
            return [];
        }

        $groupedBySupplier = $orders->groupBy('supplier_id');
        $batches = [];

        foreach ($groupedBySupplier as $supplierId => $supplierOrders) {
            if ($supplierOrders->count() < 2) {
                continue;
            }

            $chunks = $supplierOrders->chunk(3);

            foreach ($chunks as $chunkOrders) {
                if ($chunkOrders->count() < 2) {
                    continue;
                }

                $firstOrder = $chunkOrders->first();
                $supplier = $firstOrder->supplier;
                $supplierPos = $supplier?->fournisseurAgree?->getPositionCoords();

                if (! $supplierPos) {
                    continue;
                }

                $waypoints = [];
                $waypoints[] = [
                    'lat' => (float) $supplierPos['lat'],
                    'lng' => (float) $supplierPos['lng'],
                    'label' => $supplier?->fournisseurAgree?->nom_boutique ?? 'Quincaillerie',
                    'type' => 'pickup',
                    'order_id' => null,
                ];

                $validOrders = [];
                $totalEarnings = 0;
                $maxVehicle = 'moto';
                $vehicleRanks = ['moto' => 1, 'voiture' => 2, 'cargo' => 3];

                foreach ($chunkOrders as $order) {
                    $destPos = $order->address?->getPositionCoords()
                        ?? $order->client?->getPositionCoords();

                    if (! $destPos && $order->delivery_latitude && $order->delivery_longitude) {
                        $destPos = [
                            'lat' => (float) $order->delivery_latitude,
                            'lng' => (float) $order->delivery_longitude,
                        ];
                    }

                    if (! $destPos) {
                        continue;
                    }

                    $waypoints[] = [
                        'lat' => (float) $destPos['lat'],
                        'lng' => (float) $destPos['lng'],
                        'label' => 'Chantier : '.($order->delivery_address_line ?? $order->client?->name ?? 'Client'),
                        'type' => 'delivery',
                        'order_id' => $order->id,
                    ];

                    $validOrders[] = $order;
                    $totalEarnings += (int) $order->delivery_cost;

                    if ($order->vehicle_class && ($vehicleRanks[$order->vehicle_class] ?? 1) > ($vehicleRanks[$maxVehicle] ?? 1)) {
                        $maxVehicle = $order->vehicle_class;
                    }
                }

                if (count($validOrders) < 2) {
                    continue;
                }

                $route = $this->osrmService->calculateMultiDropRoute($waypoints);
                $batchId = sprintf('BATCH-%d-%d', $supplierId, $firstOrder->id);

                $batches[] = [
                    'batch_id' => $batchId,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplier?->fournisseurAgree?->nom_boutique ?? $supplier?->name,
                    'supplier_phone' => $supplier?->phone,
                    'supplier_position' => $supplierPos,
                    'order_ids' => array_map(fn ($o) => $o->id, $validOrders),
                    'orders_count' => count($validOrders),
                    'orders' => $validOrders,
                    'total_delivery_cost' => $totalEarnings,
                    'driver_earnings' => $totalEarnings,
                    'vehicle_class_required' => $maxVehicle,
                    'route' => $route,
                    'stops' => $route['stops'] ?? [],
                    'total_distance_km' => $route['distance_km'] ?? 0.0,
                    'total_duration_min' => $route['duration_min'] ?? 0.0,
                ];
            }
        }

        return $batches;
    }

    /**
     * Acceptation atomique d'un lot de commandes pour une tournée multi-drop.
     *
     * @param  array<int>  $orderIds
     * @return array<string, mixed>
     */
    public function acceptBatch(User $driver, array $orderIds): array
    {
        if (! in_array($driver->role, ['driver', 'livreur'])) {
            throw new \Exception('Seuls les livreurs enregistrés peuvent accepter une tournée.');
        }

        if ($driver->kyc_status !== 'actif') {
            throw new \Exception('Votre KYC doit être validé pour accepter une tournée logistique.');
        }

        if (count($orderIds) < 2) {
            throw new \Exception('Une tournée groupée nécessite au moins deux commandes.');
        }

        return DB::transaction(function () use ($driver, $orderIds) {
            $orders = Order::whereIn('id', $orderIds)
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== count($orderIds)) {
                throw new \Exception('Certaines commandes demandées sont introuvables.');
            }

            foreach ($orders as $order) {
                if ($order->status !== 'searching_driver') {
                    throw new \Exception("La commande #{$order->id} n'est plus disponible.");
                }
            }

            $tourId = 'TOUR-'.Str::upper(Str::random(8));
            $assignedOrders = [];

            foreach ($orders as $order) {
                $order->order_group_id = $tourId;
                $order->save();

                $assigned = $this->orderService->assignDriver($order, $driver);
                $assignedOrders[] = $assigned;
            }

            return [
                'tour_id' => $tourId,
                'driver_id' => $driver->id,
                'orders_count' => count($assignedOrders),
                'orders' => $assignedOrders,
                'message' => 'Tournée groupée acceptée avec succès.',
            ];
        });
    }

    /**
     * Récupère la feuille de route active de la tournée multi-drop du livreur.
     *
     * @return array<string, mixed>|null
     */
    public function getActiveTour(User $driver): ?array
    {
        $activeOrders = Order::where('driver_id', $driver->id)
            ->whereIn('status', ['driver_assigned', 'driver_picked_up', 'shipping'])
            ->whereNotNull('order_group_id')
            ->with(['supplier.fournisseurAgree', 'client', 'address'])
            ->orderBy('id', 'asc')
            ->get();

        if ($activeOrders->isEmpty()) {
            return null;
        }

        $tourId = $activeOrders->first()->order_group_id;
        $firstOrder = $activeOrders->first();
        $supplier = $firstOrder->supplier;
        $supplierPos = $supplier?->fournisseurAgree?->getPositionCoords();

        $waypoints = [];
        if ($supplierPos) {
            $allPickedUp = $activeOrders->every(fn ($o) => in_array($o->status, ['driver_picked_up', 'shipping', 'delivered']));
            $waypoints[] = [
                'lat' => (float) $supplierPos['lat'],
                'lng' => (float) $supplierPos['lng'],
                'label' => $supplier?->fournisseurAgree?->nom_boutique ?? 'Quincaillerie',
                'type' => 'pickup',
                'completed' => $allPickedUp,
                'order_id' => null,
            ];
        }

        foreach ($activeOrders as $order) {
            $destPos = $order->address?->getPositionCoords()
                ?? $order->client?->getPositionCoords();

            if (! $destPos && $order->delivery_latitude && $order->delivery_longitude) {
                $destPos = [
                    'lat' => (float) $order->delivery_latitude,
                    'lng' => (float) $order->delivery_longitude,
                ];
            }

            if ($destPos) {
                $waypoints[] = [
                    'lat' => (float) $destPos['lat'],
                    'lng' => (float) $destPos['lng'],
                    'label' => 'Chantier : '.($order->delivery_address_line ?? $order->client?->name ?? 'Client'),
                    'type' => 'delivery',
                    'completed' => $order->status === 'delivered',
                    'order_id' => $order->id,
                ];
            }
        }

        $route = count($waypoints) >= 2
            ? $this->osrmService->calculateMultiDropRoute($waypoints)
            : null;

        return [
            'tour_id' => $tourId,
            'driver_id' => $driver->id,
            'total_orders' => $activeOrders->count(),
            'pending_pickups' => $activeOrders->filter(fn ($o) => $o->status === 'driver_assigned')->count(),
            'pending_deliveries' => $activeOrders->filter(fn ($o) => in_array($o->status, ['driver_picked_up', 'shipping']))->count(),
            'orders' => $activeOrders,
            'route' => $route,
            'waypoints' => $waypoints,
        ];
    }
}
