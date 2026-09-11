<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service de Tarification Dynamique Logistique & Surge Pricing (Epic 2, Évolution 12)
 *
 * Formule tarifaire de référence :
 * Coût = max(MINIMUM_FARE, ((DistanceKm * RATE_KM) + (DurationMin * RATE_MIN)) * VehicleMultiplier * SurgeMultiplier)
 */
class DeliveryPricingService
{
    public const RATE_PER_KM = 150;      // 150 FCFA / km
    public const RATE_PER_MIN = 50;      // 50 FCFA / minute
    public const MINIMUM_FARE = 1000;    // Forfait plancher 1000 FCFA
    public const FALLBACK_FARE = 2500;   // Forfait de repli sans coordonnées GPS

    public const VEHICLE_MULTIPLIERS = [
        'moto'    => 1.0,  // Petit colis, outillage léger (< 20 kg)
        'voiture' => 1.5,  // Matériel intermédiaire (20-100 kg, sanitaires, petits rouleaux)
        'cargo'   => 2.5,  // Matériaux lourds (> 100 kg, ciment, fer, sable, gravier)
    ];

    public function __construct(
        private GoogleMapsService $mapsService,
        private ?OsrmRoutingService $osrmService = null,
    ) {
        $this->osrmService = $osrmService ?? app(OsrmRoutingService::class);
    }

    /**
     * Calcule l'estimation détaillée de livraison.
     *
     * @param array $params ['from' => ['lat', 'lng'], 'to' => ['lat', 'lng'], 'vehicle_class' => ?, 'surge_multiplier' => ?, 'items' => ?]
     * @return array
     */
    public function estimateFare(array $params): array
    {
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;
        $items = $params['items'] ?? [];

        // 1. Détection ou validation de la classe de véhicule
        $recommendedVehicle = $this->recommendVehicleClass($items);
        $vehicleClass = $params['vehicle_class'] ?? $recommendedVehicle;
        if (!array_key_exists($vehicleClass, self::VEHICLE_MULTIPLIERS)) {
            $vehicleClass = 'moto';
        }
        $vehicleMultiplier = self::VEHICLE_MULTIPLIERS[$vehicleClass];

        // 2. Calcul du multiplicateur de majoration (Surge Pricing)
        $isPeakHours = $this->isPeakHours();
        if (isset($params['surge_multiplier']) && is_numeric($params['surge_multiplier'])) {
            $surgeMultiplier = max(1.0, min(3.0, (float) $params['surge_multiplier']));
        } else {
            $clientLat = $to['lat'] ?? null;
            $clientLng = $to['lng'] ?? null;
            $surgeMultiplier = $this->calculateSurgeMultiplier(
                $clientLat !== null ? (float) $clientLat : null,
                $clientLng !== null ? (float) $clientLng : null
            );
        }

        // 3. Calcul de la distance et durée de trajet
        $fromLat = $from['lat'] ?? null;
        $fromLng = $from['lng'] ?? null;
        $toLat = $to['lat'] ?? null;
        $toLng = $to['lng'] ?? null;

        if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) {
            $rawCost = self::FALLBACK_FARE * $vehicleMultiplier * $surgeMultiplier;
            $deliveryCost = (int) max(self::MINIMUM_FARE, round($rawCost));

            return [
                'delivery_cost'             => $deliveryCost,
                'distance_km'               => 10.0,
                'duration_min'              => 20.0,
                'vehicle_class'             => $vehicleClass,
                'vehicle_multiplier'        => $vehicleMultiplier,
                'surge_multiplier'          => $surgeMultiplier,
                'is_peak_hours'             => $isPeakHours,
                'recommended_vehicle_class' => $recommendedVehicle,
                'is_fallback'               => true,
                'fare_breakdown'            => [
                    'minimum_fare'  => self::MINIMUM_FARE,
                    'distance_fare' => (int) round(10.0 * self::RATE_PER_KM),
                    'duration_fare' => (int) round(20.0 * self::RATE_PER_MIN),
                    'subtotal'      => self::FALLBACK_FARE,
                    'total'         => $deliveryCost,
                ],
            ];
        }

        $directions = $this->mapsService->getDirections(
            ['lat' => (float) $fromLat, 'lng' => (float) $fromLng],
            ['lat' => (float) $toLat, 'lng' => (float) $toLng]
        );

        $distanceMeters = (float) ($directions['distance'] ?? 0);
        $durationSeconds = (float) ($directions['duration'] ?? 0);

        $distanceKm = round($distanceMeters / 1000, 2);
        $durationMin = round($durationSeconds / 60, 1);

        $distanceFare = $distanceKm * self::RATE_PER_KM;
        $durationFare = $durationMin * self::RATE_PER_MIN;
        $baseSubtotal = $distanceFare + $durationFare;

        $rawCost = $baseSubtotal * $vehicleMultiplier * $surgeMultiplier;
        $deliveryCost = (int) max(self::MINIMUM_FARE, round($rawCost));

        return [
            'delivery_cost'             => $deliveryCost,
            'distance_km'               => $distanceKm,
            'duration_min'              => $durationMin,
            'vehicle_class'             => $vehicleClass,
            'vehicle_multiplier'        => $vehicleMultiplier,
            'surge_multiplier'          => $surgeMultiplier,
            'is_peak_hours'             => $isPeakHours,
            'recommended_vehicle_class' => $recommendedVehicle,
            'is_fallback'               => false,
            'fare_breakdown'            => [
                'minimum_fare'   => self::MINIMUM_FARE,
                'distance_fare'  => (int) round($distanceFare),
                'duration_fare'  => (int) round($durationFare),
                'base_subtotal'  => (int) round($baseSubtotal),
                'vehicle_surcharge' => (int) round($baseSubtotal * ($vehicleMultiplier - 1.0)),
                'surge_applied'  => (int) round($rawCost - ($baseSubtotal * $vehicleMultiplier)),
                'total'          => $deliveryCost,
            ],
        ];
    }

    /**
     * Calcule le Surge Multiplier en fonction de l'heure locale et des paramètres plateforme.
     */
    public function calculateSurgeMultiplier(?float $lat = null, ?float $lng = null): float
    {
        // 1. Vérifier si une surcharge manuelle globale est configurée dans settings
        $globalSurge = Setting::getValueByKey('delivery_surge_multiplier', null);
        if ($globalSurge !== null && is_numeric($globalSurge) && (float) $globalSurge > 1.0) {
            return (float) $globalSurge;
        }

        // 2. Calcul dynamique basé sur l'heure locale d'Abidjan (GMT/UTC)
        $now = Carbon::now('Africa/Abidjan');
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $timeDecimal = $hour + ($minute / 60.0);

        $surge = 1.0;

        // Heure de pointe du matin (07h30 à 09h30)
        if ($timeDecimal >= 7.5 && $timeDecimal <= 9.5) {
            $surge = 1.30;
        }
        // Heure de pointe du soir (17h00 à 20h00)
        elseif ($timeDecimal >= 17.0 && $timeDecimal <= 20.0) {
            $surge = 1.40;
        }
        // Nuit profonde (22h00 à 05h00)
        elseif ($timeDecimal >= 22.0 || $timeDecimal <= 5.0) {
            $surge = 1.20;
        }

        return round($surge, 2);
    }

    /**
     * Indique si l'instant présent correspond à une heure de pointe à Abidjan.
     */
    public function isPeakHours(): bool
    {
        $now = Carbon::now('Africa/Abidjan');
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $timeDecimal = $hour + ($minute / 60.0);

        return ($timeDecimal >= 7.5 && $timeDecimal <= 9.5) ||
               ($timeDecimal >= 17.0 && $timeDecimal <= 20.0);
    }

    /**
     * Recommande intelligemment le gabarit de véhicule en fonction des articles du panier.
     */
    public function recommendVehicleClass(array $items = []): string
    {
        if (empty($items)) {
            return 'moto';
        }

        $heavyKeywords = [
            'ciment', 'sable', 'gravier', 'brique', 'parpaing', 'fer', 'tôle',
            'carreau', 'carrelage', 'plaque', 'tuyau', 'cuvette', 'chauffe-eau',
            'mortier', 'béton', 'agglo', 'poutrelle'
        ];

        $mediumKeywords = [
            'peinture', 'rouleau', 'lavabo', 'évier', 'mitigeur', 'robinet',
            'câble', 'gaine', 'disjoncteur', 'serrure', 'porte', 'fenêtre',
            'enduit', 'vernis'
        ];

        $totalQuantity = 0;
        $hasHeavy = false;
        $hasMedium = false;

        foreach ($items as $item) {
            $name = strtolower((string) ($item['name'] ?? $item['product_name'] ?? ''));
            $quantity = (int) ($item['quantity'] ?? 1);
            $totalQuantity += $quantity;

            foreach ($heavyKeywords as $kw) {
                if (str_contains($name, $kw)) {
                    $hasHeavy = true;
                    break;
                }
            }

            foreach ($mediumKeywords as $kw) {
                if (str_contains($name, $kw)) {
                    $hasMedium = true;
                    break;
                }
            }
        }

        // Si présence de matériaux lourds ou plus de 8 articles au total -> Cargo
        if ($hasHeavy || $totalQuantity >= 8) {
            return 'cargo';
        }

        // Si matériel intermédiaire ou plus de 3 articles -> Voiture
        if ($hasMedium || $totalQuantity >= 3) {
            return 'voiture';
        }

        return 'moto';
    }

    /**
     * Calcule le coût de livraison d'une commande existante.
     */
    public function calculateOrderDeliveryCost(Order $order): int
    {
        $supplierProfile = $order->supplier?->fournisseurAgree;
        $from = $supplierProfile?->getPositionCoords();
        $to = $order->client?->getPositionCoords();

        $items = [];
        foreach ($order->items as $orderItem) {
            $items[] = [
                'name'     => $orderItem->supplierProduct?->name ?? '',
                'quantity' => $orderItem->quantity,
            ];
        }

        $estimate = $this->estimateFare([
            'from'             => $from,
            'to'               => $to,
            'vehicle_class'    => $order->vehicle_class ?? 'moto',
            'surge_multiplier' => $order->surge_multiplier ?? 1.0,
            'items'            => $items,
        ]);

        return $estimate['delivery_cost'];
    }
}
