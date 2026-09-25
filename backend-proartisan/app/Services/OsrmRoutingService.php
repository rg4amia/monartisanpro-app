<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OsrmRoutingService
{
    private const AVERAGE_CITY_SPEED_KMH = 25.0; // Vitesse moyenne en agglomération ivoirienne

    private const URBAN_WINDING_FACTOR = 1.3;   // Facteur de sinuosité urbaine

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.osrm.base_url', 'https://router.project-osrm.org');
    }

    /**
     * Calcule l'itinéraire routier précis, la distance, l'ETA et la trace GeoJSON via OSRM.
     * En cas d'indisponibilité, utilise un repli géométrique résilient (Haversine + sinuosité).
     */
    public function calculateRoute(float $fromLat, float $fromLng, float $toLat, float $toLng, string $profile = 'driving'): array
    {
        try {
            $url = sprintf(
                '%s/route/v1/%s/%f,%f;%f,%f?overview=full&geometries=geojson',
                $this->baseUrl,
                $profile,
                $fromLng,
                $fromLat,
                $toLng,
                $toLat
            );

            $response = Http::timeout(3)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (! empty($data['routes'][0])) {
                    $route = $data['routes'][0];
                    $distanceMeters = $route['distance'] ?? 0;
                    $durationSeconds = $route['duration'] ?? 0;
                    $geometry = $route['geometry']['coordinates'] ?? [];

                    $distanceKm = round($distanceMeters / 1000, 2);
                    $durationMin = round($durationSeconds / 60, 1);

                    return [
                        'distance_km' => $distanceKm,
                        'duration_min' => $durationMin,
                        'duration_minutes' => $durationMin,
                        'eta' => Carbon::now()->addSeconds((int) $durationSeconds)->toIso8601String(),
                        'geometry' => $geometry,
                        'coordinates' => $geometry,
                        'is_fallback' => false,
                        'provider' => 'osrm',
                        'source' => 'osrm',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[OsrmRoutingService] Échec appel OSRM : '.$e->getMessage().' — Bascule sur le repli résilient.');
        }

        // Repli résilient : Calcul Haversine avec facteur de sinuosité
        return $this->calculateFallbackRoute($fromLat, $fromLng, $toLat, $toLng);
    }

    /**
     * Repli géométrique basé sur la formule de Haversine avec ajustement routier.
     */
    public function calculateFallbackRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $straightDistanceKm = $this->haversineDistanceKm($fromLat, $fromLng, $toLat, $toLng);
        $roadDistanceKm = round($straightDistanceKm * self::URBAN_WINDING_FACTOR, 2);

        // Durée estimée en minutes à 25 km/h moyen en ville
        $durationMin = round(($roadDistanceKm / self::AVERAGE_CITY_SPEED_KMH) * 60, 1);
        $durationMin = max(5.0, $durationMin); // Minimum 5 minutes forfaitaires

        return [
            'distance_km' => $roadDistanceKm,
            'duration_min' => $durationMin,
            'duration_minutes' => $durationMin,
            'eta' => Carbon::now()->addMinutes((int) round($durationMin))->toIso8601String(),
            'geometry' => [
                [$fromLng, $fromLat],
                [$toLng, $toLat],
            ],
            'coordinates' => [
                [$fromLng, $fromLat],
                [$toLng, $toLat],
            ],
            'is_fallback' => true,
            'provider' => 'haversine_fallback',
            'source' => 'haversine_estimate',
        ];
    }

    /**
     * Calcule l'itinéraire multi-arrêts (tournée multi-drop) pour une série de points de passage.
     *
     * @param  array<int, array{lat: float, lng: float, label?: string, type?: string, order_id?: int}>  $waypoints
     * @return array{
     *     distance_km: float,
     *     duration_min: float,
     *     eta: string,
     *     geometry: array,
     *     stops: array<int, array{label: string, lat: float, lng: float, eta: string, leg_distance_km: float, leg_duration_min: float, type: string, order_id: int|null}>,
     *     is_fallback: bool,
     *     provider: string
     * }
     */
    public function calculateMultiDropRoute(array $waypoints, string $profile = 'driving'): array
    {
        if (count($waypoints) < 2) {
            return [
                'distance_km' => 0.0,
                'duration_min' => 0.0,
                'eta' => Carbon::now()->toIso8601String(),
                'geometry' => [],
                'stops' => [],
                'is_fallback' => true,
                'provider' => 'none',
            ];
        }

        try {
            $coordStrings = array_map(function ($wp) {
                return sprintf('%f,%f', (float) $wp['lng'], (float) $wp['lat']);
            }, $waypoints);

            $coordsPath = implode(';', $coordStrings);
            $url = sprintf(
                '%s/route/v1/%s/%s?overview=full&geometries=geojson&steps=true',
                $this->baseUrl,
                $profile,
                $coordsPath
            );

            $response = Http::timeout(4)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (! empty($data['routes'][0])) {
                    $route = $data['routes'][0];
                    $totalDistanceKm = round(($route['distance'] ?? 0) / 1000, 2);
                    $totalDurationMin = round(($route['duration'] ?? 0) / 60, 1);
                    $geometry = $route['geometry']['coordinates'] ?? [];
                    $legs = $route['legs'] ?? [];

                    $currentTime = Carbon::now();
                    $stops = [];

                    foreach ($waypoints as $idx => $wp) {
                        $legDistKm = 0.0;
                        $legDurMin = 0.0;

                        if ($idx > 0 && isset($legs[$idx - 1])) {
                            $legDistKm = round(($legs[$idx - 1]['distance'] ?? 0) / 1000, 2);
                            $legDurMin = round(($legs[$idx - 1]['duration'] ?? 0) / 60, 1);
                            $currentTime = $currentTime->copy()->addMinutes((int) round($legDurMin));
                        }

                        $stops[] = [
                            'index' => $idx,
                            'label' => $wp['label'] ?? ('Arrêt '.($idx + 1)),
                            'type' => $wp['type'] ?? ($idx === 0 ? 'pickup' : 'delivery'),
                            'order_id' => $wp['order_id'] ?? null,
                            'lat' => (float) $wp['lat'],
                            'lng' => (float) $wp['lng'],
                            'eta' => $currentTime->toIso8601String(),
                            'leg_distance_km' => $legDistKm,
                            'leg_duration_min' => $legDurMin,
                        ];
                    }

                    return [
                        'distance_km' => $totalDistanceKm,
                        'duration_min' => $totalDurationMin,
                        'eta' => Carbon::now()->addMinutes((int) round($totalDurationMin))->toIso8601String(),
                        'geometry' => $geometry,
                        'coordinates' => $geometry,
                        'stops' => $stops,
                        'is_fallback' => false,
                        'provider' => 'osrm',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[OsrmRoutingService] Échec calcul tournée multi-drop OSRM : '.$e->getMessage().' — Repli séquentiel.');
        }

        // Repli séquentiel Haversine
        return $this->calculateFallbackMultiDropRoute($waypoints);
    }

    /**
     * Repli géométrique multi-arrêts basé sur Haversine segment par segment.
     */
    public function calculateFallbackMultiDropRoute(array $waypoints): array
    {
        $totalDistanceKm = 0.0;
        $totalDurationMin = 0.0;
        $currentTime = Carbon::now();
        $stops = [];
        $geometry = [];

        foreach ($waypoints as $idx => $wp) {
            $legDistKm = 0.0;
            $legDurMin = 0.0;

            if ($idx > 0) {
                $prev = $waypoints[$idx - 1];
                $straightDist = $this->haversineDistanceKm($prev['lat'], $prev['lng'], $wp['lat'], $wp['lng']);
                $legDistKm = round($straightDist * self::URBAN_WINDING_FACTOR, 2);
                $legDurMin = max(4.0, round(($legDistKm / self::AVERAGE_CITY_SPEED_KMH) * 60, 1));
                $currentTime = $currentTime->copy()->addMinutes((int) round($legDurMin));

                $totalDistanceKm += $legDistKm;
                $totalDurationMin += $legDurMin;
            }

            $geometry[] = [(float) $wp['lng'], (float) $wp['lat']];

            $stops[] = [
                'index' => $idx,
                'label' => $wp['label'] ?? ('Arrêt '.($idx + 1)),
                'type' => $wp['type'] ?? ($idx === 0 ? 'pickup' : 'delivery'),
                'order_id' => $wp['order_id'] ?? null,
                'lat' => (float) $wp['lat'],
                'lng' => (float) $wp['lng'],
                'eta' => $currentTime->toIso8601String(),
                'leg_distance_km' => $legDistKm,
                'leg_duration_min' => $legDurMin,
            ];
        }

        return [
            'distance_km' => round($totalDistanceKm, 2),
            'duration_min' => round($totalDurationMin, 1),
            'eta' => Carbon::now()->addMinutes((int) round($totalDurationMin))->toIso8601String(),
            'geometry' => $geometry,
            'coordinates' => $geometry,
            'stops' => $stops,
            'is_fallback' => true,
            'provider' => 'haversine_fallback',
        ];
    }

    /**
     * Distance à vol d'oiseau (Haversine) en kilomètres.
     */
    public function haversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 3);
    }
}

