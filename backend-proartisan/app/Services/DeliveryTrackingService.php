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
}
