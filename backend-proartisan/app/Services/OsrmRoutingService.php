<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OsrmRoutingService
{
    private const OSRM_BASE_URL = 'https://router.project-osrm.org';
    private const AVERAGE_CITY_SPEED_KMH = 25.0; // Vitesse moyenne en agglomération ivoirienne
    private const URBAN_WINDING_FACTOR = 1.3;   // Facteur de sinuosité urbaine

    /**
     * Calcule l'itinéraire routier précis, la distance, l'ETA et la trace GeoJSON via OSRM.
     * En cas d'indisponibilité, utilise un repli géométrique résilient (Haversine + sinuosité).
     */
    public function calculateRoute(float $fromLat, float $fromLng, float $toLat, float $toLng, string $profile = 'driving'): array
    {
        try {
            $url = sprintf(
                '%s/route/v1/%s/%f,%f;%f,%f?overview=full&geometries=geojson',
                self::OSRM_BASE_URL,
                $profile,
                $fromLng,
                $fromLat,
                $toLng,
                $toLat
            );

            $response = Http::timeout(3)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['routes'][0])) {
                    $route = $data['routes'][0];
                    $distanceMeters = $route['distance'] ?? 0;
                    $durationSeconds = $route['duration'] ?? 0;
                    $geometry = $route['geometry']['coordinates'] ?? [];

                    $distanceKm = round($distanceMeters / 1000, 2);
                    $durationMin = round($durationSeconds / 60, 1);

                    return [
                        'distance_km'      => $distanceKm,
                        'duration_min'     => $durationMin,
                        'duration_minutes' => $durationMin,
                        'eta'              => Carbon::now()->addSeconds((int) $durationSeconds)->toIso8601String(),
                        'geometry'         => $geometry,
                        'coordinates'      => $geometry,
                        'is_fallback'      => false,
                        'provider'         => 'osrm',
                        'source'           => 'osrm',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[OsrmRoutingService] Échec appel OSRM : " . $e->getMessage() . " — Bascule sur le repli résilient.");
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
            'distance_km'      => $roadDistanceKm,
            'duration_min'     => $durationMin,
            'duration_minutes' => $durationMin,
            'eta'              => Carbon::now()->addMinutes((int) round($durationMin))->toIso8601String(),
            'geometry'         => [
                [$fromLng, $fromLat],
                [$toLng, $toLat],
            ],
            'coordinates'      => [
                [$fromLng, $fromLat],
                [$toLng, $toLat],
            ],
            'is_fallback'      => true,
            'provider'         => 'haversine_fallback',
            'source'           => 'haversine_estimate',
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
