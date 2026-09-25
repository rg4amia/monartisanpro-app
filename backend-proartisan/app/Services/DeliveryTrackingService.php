<?php

namespace App\Services;

use App\Models\DeliveryTracking;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;

/**
 * Télémétrie des livraisons de matériaux : positions GPS du livreur (point
 * unique ou lot), diffusion temps réel et paquet de suivi 360° pour le mobile
 * et le backoffice. Le cycle de vie de la commande reste dans OrderService.
 */
class DeliveryTrackingService
{
    public function __construct(
        private OsrmRoutingService $osrmService,
        private RealtimeEventService $realtimeEventService,
    ) {}

    /**
     * Enregistre un lot de points GPS télémétriques (batching économie réseau/batterie)
     * et diffuse la dernière position connue.
     *
     * @param  array  $points  [ ['latitude' => ..., 'longitude' => ..., 'speed_kmh' => ..., 'heading' => ..., 'battery_level' => ..., 'recorded_at' => ...], ... ]
     */
    public function recordDriverBatchLocations(Order $order, User $driver, array $points): DeliveryTracking
    {
        if ($order->driver_id !== $driver->id) {
            throw new \Exception("Ce livreur n'est pas assigné à cette commande.");
        }

        if (empty($points)) {
            throw new \Exception('Aucun point de télémétrie fourni.');
        }

        $records = [];
        $now = now();
        foreach ($points as $pt) {
            $records[] = [
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'latitude' => (float) $pt['latitude'],
                'longitude' => (float) $pt['longitude'],
                'speed_kmh' => isset($pt['speed_kmh']) ? (float) $pt['speed_kmh'] : null,
                'heading' => isset($pt['heading']) ? (float) $pt['heading'] : null,
                'battery_level' => isset($pt['battery_level']) ? (int) $pt['battery_level'] : null,
                'created_at' => ! empty($pt['recorded_at']) ? Carbon::parse($pt['recorded_at']) : $now,
            ];
        }

        DeliveryTracking::insert($records);

        $lastPoint = end($points);
        $lastLat = (float) $lastPoint['latitude'];
        $lastLng = (float) $lastPoint['longitude'];
        $lastSpeed = isset($lastPoint['speed_kmh']) ? (float) $lastPoint['speed_kmh'] : null;
        $lastHeading = isset($lastPoint['heading']) ? (float) $lastPoint['heading'] : null;
        $lastBattery = isset($lastPoint['battery_level']) ? (int) $lastPoint['battery_level'] : null;

        try {
            $driver->setPosition($lastLat, $lastLng);
        } catch (\Throwable $e) {
        }

        $latestTracking = DeliveryTracking::where('order_id', $order->id)
            ->where('driver_id', $driver->id)
            ->latest('id')
            ->first();

        // Diffusion temps réel SSE
        $payload = [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'latitude' => $lastLat,
            'longitude' => $lastLng,
            'speed_kmh' => $lastSpeed,
            'heading' => $lastHeading,
            'battery_level' => $lastBattery,
            'recorded_at' => $now->toIso8601String(),
            'batch_size' => count($points),
        ];

        $this->realtimeEventService->publish(
            'order',
            $order->id,
            'driver_position_updated',
            $payload,
            'Livreur en mouvement (Batch)'
        );

        return $latestTracking ?? new DeliveryTracking($records[0]);
    }

    /**
     * Enregistre un point GPS télémétrique du livreur et diffuse l'événement SSE en temps réel.
     */
    public function recordDriverLocation(
        Order $order,
        User $driver,
        float $lat,
        float $lng,
        ?float $speed = null,
        ?float $heading = null,
        ?int $battery = null,
    ): DeliveryTracking {
        if ($order->driver_id !== $driver->id) {
            throw new \Exception("Ce livreur n'est pas assigné à cette commande.");
        }

        $tracking = DeliveryTracking::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed_kmh' => $speed,
            'heading' => $heading,
            'battery_level' => $battery,
            'created_at' => now(),
        ]);

        // Mettre à jour la position courante du profil livreur.
        // On passe par User::setPosition, qui utilise des requêtes préparées et
        // gère le cas SQLite : l'ancienne interpolation directe des coordonnées
        // dans du SQL brut n'était pas injectable (les paramètres sont typés
        // `float`), mais elle restait fragile et contournait la convention du
        // projet — les colonnes POINT s'écrivent avec des bindings.
        try {
            $driver->setPosition($lat, $lng);
        } catch (\Throwable $e) {
            // Ignorer si la colonne spatiale a des spécificités en environnement de test
        }

        // Diffusion temps réel SSE via RealtimeEventService (Lot 2)
        $payload = [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed_kmh' => $speed,
            'heading' => $heading,
            'battery_level' => $battery,
            'recorded_at' => now()->toIso8601String(),
        ];

        // 1. Diffusion au canal de la commande
        $this->realtimeEventService->publish(
            'order',
            $order->id,
            'driver_position_updated',
            $payload,
            'Livreur en mouvement'
        );

        // 2. Si rattaché à une mission chantier, diffusion au canal de la mission
        // Le lien mission est porté par la commande (`orders.mission_id`) ;
        // `order_items` n'a pas de colonne mission_id. SQLite tolérait la
        // requête (identifiant inconnu lu comme chaîne littérale), MariaDB la
        // rejette : toute télémétrie livreur échouait en production.
        $missionId = $order->mission_id;
        if ($missionId) {
            $this->realtimeEventService->publish(
                'mission',
                $missionId,
                'driver_position_updated',
                $payload,
                'Livreur de matériaux en approche'
            );
        }

        return $tracking;
    }

    /**
     * Récupère le package complet de suivi 360° de la livraison pour mobile et backoffice.
     */
    public function getDeliveryTrackingData(Order $order, ?User $viewer = null): array
    {
        $order->loadMissing(['supplier.fournisseurAgree', 'client', 'driver', 'latestTracking']);

        $supplierPos = $order->supplier?->fournisseurAgree?->getPositionCoords();
        $clientPos = $order->client?->getPositionCoords();
        if (! $clientPos && $order->delivery_latitude && $order->delivery_longitude) {
            $clientPos = [
                'lat' => (float) $order->delivery_latitude,
                'lng' => (float) $order->delivery_longitude,
            ];
        }
        $latest = $order->latestTracking;

        $route = null;
        if ($supplierPos && $clientPos) {
            $route = $this->osrmService->calculateRoute(
                (float) $supplierPos['lat'],
                (float) $supplierPos['lng'],
                (float) $clientPos['lat'],
                (float) $clientPos['lng']
            );
        }

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'delivery_mode' => $order->delivery_mode,
            'supplier' => [
                'id' => $order->supplier_id,
                'name' => $order->supplier?->fournisseurAgree?->nom_boutique ?? $order->supplier?->name,
                'phone' => $order->supplier?->phone,
                'position' => $supplierPos,
            ],
            'client' => [
                'id' => $order->client_id,
                'name' => $order->client?->name,
                'phone' => $order->client?->phone,
                'position' => $clientPos,
            ],
            'driver' => $order->driver ? [
                'id' => $order->driver->id,
                'name' => $order->driver->name,
                'phone' => $order->driver->phone,
                'position' => $latest ? [
                    'lat' => $latest->latitude,
                    'lng' => $latest->longitude,
                    'speed' => $latest->speed_kmh,
                    'heading' => $latest->heading,
                    'updated_at' => $latest->created_at?->toIso8601String(),
                ] : null,
            ] : null,
            'route' => $route,
            'routing' => $route,
            'driver_latest_position' => $latest ? [
                'latitude' => $latest->latitude,
                'longitude' => $latest->longitude,
                'speed_kmh' => $latest->speed_kmh,
                'heading' => $latest->heading,
                'battery_level' => $latest->battery_level,
                'recorded_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            // Uniquement les codes que cet acteur doit connaître : le livreur
            // n'en reçoit aucun, il doit les demander au fournisseur puis au
            // client. Auparavant les deux étaient renvoyés à tout le monde.
            'codes' => $order->codesVisibleTo($viewer),
            'pickup_photo_url' => $order->pickup_photo_url,
            'delivery_photo_url' => $order->delivery_photo_url,
            'delivery_cost' => $order->delivery_cost,
        ];
    }

    /**
     * Récupère la cartographie en direct et le radar de supervision de toute la flotte livreur.
     *
     * @return array{
     *     summary: array{
     *         total_drivers: int,
     *         online_drivers: int,
     *         in_transit: int,
     *         available: int,
     *         stalled_alerts: int,
     *         active_orders_count: int,
     *         updated_at: string
     *     },
     *     drivers: array<int, array>
     * }
     */
    public function getFleetOverview(?string $commune = null): array
    {
        $drivers = User::query()
            ->whereIn('role', ['driver', 'livreur'])
            ->where('kyc_status', 'actif')
            ->get();

        $driverIds = $drivers->pluck('id')->all();

        // Récupérer les commandes actives pour ces chauffeurs
        $activeOrders = Order::query()
            ->whereIn('driver_id', $driverIds)
            ->whereIn('status', ['driver_assigned', 'shipping', 'driver_picked_up'])
            ->with([
                'client',
                'supplier.fournisseurAgree',
            ])
            ->get()
            ->groupBy('driver_id');

        // Récupérer le dernier point télémétrique pour chaque chauffeur
        $latestTrackings = DeliveryTracking::query()
            ->whereIn('driver_id', $driverIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('driver_id')
            ->map(fn ($list) => $list->first());

        $now = now();
        $fleetList = [];

        $totalDrivers = $drivers->count();
        $onlineDriversCount = 0;
        $inTransitCount = 0;
        $availableCount = 0;
        $stalledAlertsCount = 0;
        $activeOrdersCount = 0;

        foreach ($drivers as $driver) {
            $latest = $latestTrackings->get($driver->id);
            $activeOrder = $activeOrders->get($driver->id)?->first();

            $coords = null;
            if ($latest && $latest->latitude && $latest->longitude) {
                $coords = [
                    'lat' => (float) $latest->latitude,
                    'lng' => (float) $latest->longitude,
                ];
            } else {
                $coords = $driver->getPositionCoords();
            }

            $lastPingAt = $latest?->created_at ?? $driver->updated_at;
            $minutesAgo = $lastPingAt ? (int) $lastPingAt->diffInMinutes($now) : null;
            $isOnline = $minutesAgo !== null && $minutesAgo <= 120;

            if ($isOnline) {
                $onlineDriversCount++;
            }

            // Statut opérationnel
            $status = 'available';
            $isStalled = false;
            $stalledReason = null;

            if ($activeOrder) {
                $activeOrdersCount++;
                if (in_array($activeOrder->status, ['driver_picked_up', 'shipping'])) {
                    $status = 'delivering';
                    $inTransitCount++;
                    // En transit avec marchandise : alerte si pas de ping depuis > 25 min (Règle 89)
                    if ($minutesAgo !== null && $minutesAgo > 25) {
                        $isStalled = true;
                        $stalledReason = 'Livraison en transit bloquée (> 25 min sans signal GPS)';
                    }
                } else { // driver_assigned
                    $status = 'en_route_pickup';
                    $inTransitCount++;
                    // Assigné : alerte si pas de ping depuis > 15 min (Règle 74 / Watchdog)
                    if ($minutesAgo !== null && $minutesAgo > 15) {
                        $isStalled = true;
                        $stalledReason = 'Retard d\'enlèvement boutique (> 15 min sans mouvement)';
                    }
                }

                if ($activeOrder->driver_stalled_alert_at) {
                    $isStalled = true;
                    $stalledReason = $stalledReason ?? 'Alerte inactivité déclenchée par le watchdog';
                }
            } else {
                if ($isOnline) {
                    $status = 'available';
                    $availableCount++;
                } else {
                    $status = 'offline';
                }
            }

            if ($isStalled) {
                $stalledAlertsCount++;
            }

            // Commune estimée à Abidjan
            $estimatedCommune = null;
            if ($coords) {
                $estimatedCommune = $this->resolveClosestCommune($coords['lat'], $coords['lng']);
            }

            // Si filtre par commune demandé
            if ($commune && $commune !== 'all' && $estimatedCommune) {
                if (strtolower($estimatedCommune) !== strtolower($commune)) {
                    continue;
                }
            }

            $activeOrderData = null;
            if ($activeOrder) {
                $supplierPos = $activeOrder->supplier?->fournisseurAgree?->getPositionCoords();
                $clientPos = $activeOrder->client?->getPositionCoords();
                if (! $clientPos && $activeOrder->delivery_latitude && $activeOrder->delivery_longitude) {
                    $clientPos = [
                        'lat' => (float) $activeOrder->delivery_latitude,
                        'lng' => (float) $activeOrder->delivery_longitude,
                    ];
                }

                $activeOrderData = [
                    'id' => $activeOrder->id,
                    'status' => $activeOrder->status,
                    'order_group_id' => $activeOrder->order_group_id,
                    'delivery_cost' => (int) ($activeOrder->delivery_cost ?? 1500),
                    'total_amount' => (int) ($activeOrder->total_amount ?? 0),
                    'supplier_name' => $activeOrder->supplier?->fournisseurAgree?->nom_boutique ?? $activeOrder->supplier?->name ?? 'Quincaillerie',
                    'supplier_position' => $supplierPos,
                    'client_name' => $activeOrder->client?->name ?? 'Client',
                    'client_position' => $clientPos,
                    'pickup_code' => $activeOrder->pickup_code,
                    'reception_code' => $activeOrder->reception_code,
                ];
            }

            $fleetList[] = [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'avatar_url' => $driver->photo_url,
                'status' => $status,
                'is_online' => $isOnline,
                'is_stalled' => $isStalled,
                'stalled_reason' => $stalledReason,
                'position' => $coords,
                'speed_kmh' => $latest?->speed_kmh !== null ? (float) $latest->speed_kmh : null,
                'heading' => $latest?->heading !== null ? (float) $latest->heading : null,
                'battery_level' => $latest?->battery_level !== null ? (int) $latest->battery_level : null,
                'last_ping_at' => $lastPingAt?->toIso8601String(),
                'minutes_since_ping' => $minutesAgo,
                'estimated_commune' => $estimatedCommune,
                'active_order' => $activeOrderData,
            ];
        }

        return [
            'summary' => [
                'total_drivers' => $totalDrivers,
                'online_drivers' => $onlineDriversCount,
                'in_transit' => $inTransitCount,
                'available' => $availableCount,
                'stalled_alerts' => $stalledAlertsCount,
                'active_orders_count' => $activeOrdersCount,
                'updated_at' => $now->toIso8601String(),
            ],
            'drivers' => $fleetList,
        ];
    }

    /**
     * Identifie la commune du Grand Abidjan la plus proche des coordonnées GPS données.
     */
    private function resolveClosestCommune(float $lat, float $lng): string
    {
        $communes = [
            'Cocody' => [5.3544, -3.9856],
            'Yopougon' => [5.3400, -4.0800],
            'Plateau' => [5.3261, -4.0197],
            'Abobo' => [5.4167, -4.0167],
            'Marcory' => [5.3000, -3.9833],
            'Koumassi' => [5.3000, -3.9500],
            'Treichville' => [5.3000, -4.0000],
            'Port-Bouët' => [5.2500, -3.9333],
            'Attécoubé' => [5.3333, -4.0333],
            'Adjamé' => [5.3500, -4.0333],
            'Bingerville' => [5.3556, -3.8861],
            'Anyama' => [5.4944, -4.0519],
            'Songon' => [5.3167, -4.2500],
        ];

        $closestName = 'Abidjan';
        $minDist = PHP_FLOAT_MAX;

        foreach ($communes as $name => [$cLat, $cLng]) {
            $dist = (($lat - $cLat) ** 2) + (($lng - $cLng) ** 2);
            if ($dist < $minDist) {
                $minDist = $dist;
                $closestName = $name;
            }
        }

        return $closestName;
    }
}

