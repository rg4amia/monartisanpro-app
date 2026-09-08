<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service de calcul d'itinéraire (distance + durée réelles).
 *
 * Fournisseurs, dans l'ordre de préférence :
 *   1. Yandex Distance Matrix API  (services.yandex.distance_matrix_key)
 *   2. Google Directions API       (services.google.maps_api_key / GOOGLE_MAPS_API_KEY)
 *   3. Repli géodésique Haversine  (toujours disponible, aucune clé)
 *
 * Le nom de classe est conservé pour compatibilité (mocké dans plusieurs tests).
 * Le contrat public reste `getDirections(array $from, array $to): array` avec
 * `['distance' => mètres, 'duration' => secondes, 'source' => string]`.
 */
class GoogleMapsService
{
    private ?string $yandexKey;

    private string $yandexUrl;

    private ?string $googleKey;

    public function __construct()
    {
        $this->yandexKey = config('services.yandex.distance_matrix_key');
        $this->yandexUrl = config(
            'services.yandex.distance_matrix_url',
            'https://api.routing.yandex.net/v2/distancematrix'
        );
        $this->googleKey = config('services.google.maps_api_key') ?? env('GOOGLE_MAPS_API_KEY');
    }

    /**
     * Distance (mètres) et durée (secondes) de trajet entre deux points
     * `['lat' => float, 'lng' => float]`.
     */
    public function getDirections(array $from, array $to): array
    {
        if ($this->yandexKey) {
            $result = $this->tryYandex($from, $to);
            if ($result !== null) {
                return $result;
            }
        }

        if ($this->googleKey) {
            $result = $this->tryGoogle($from, $to);
            if ($result !== null) {
                return $result;
            }
        }

        if (! $this->yandexKey && ! $this->googleKey) {
            Log::warning('Aucune clé Maps (Yandex/Google) configurée. Fallback Haversine.');
        }

        return $this->fallbackHaversine($from, $to);
    }

    private function tryYandex(array $from, array $to): ?array
    {
        try {
            $response = Http::timeout(5)->get($this->yandexUrl, [
                'apikey' => $this->yandexKey,
                'origins' => "{$from['lat']},{$from['lng']}",
                'destinations' => "{$to['lat']},{$to['lng']}",
                'mode' => 'driving',
            ]);

            if ($response->successful()) {
                $element = $response->json('rows.0.elements.0');
                if (is_array($element) && ($element['status'] ?? null) === 'OK') {
                    return [
                        'distance' => (int) ($element['distance']['value'] ?? 0),
                        'duration' => (int) ($element['duration']['value'] ?? 0),
                        'source' => 'yandex_distance_matrix',
                    ];
                }
                Log::warning('Yandex Distance Matrix : aucune route.', ['body' => $response->json()]);
            } else {
                Log::error('Erreur API Yandex Distance Matrix : '.$response->status());
            }
        } catch (\Throwable $e) {
            Log::error('Exception Yandex Distance Matrix : '.$e->getMessage());
        }

        return null;
    }

    private function tryGoogle(array $from, array $to): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => "{$from['lat']},{$from['lng']}",
                'destination' => "{$to['lat']},{$to['lng']}",
                'key' => $this->googleKey,
                'mode' => 'driving',
            ]);

            if ($response->successful()) {
                $leg = $response->json('routes.0.legs.0');
                if (is_array($leg) && isset($leg['distance']['value'], $leg['duration']['value'])) {
                    return [
                        'distance' => (int) $leg['distance']['value'],
                        'duration' => (int) $leg['duration']['value'],
                        'source' => 'google_maps',
                    ];
                }
                Log::warning('Google Maps : aucune route.', ['data' => $response->json()]);
            } else {
                Log::error('Erreur API Google Maps Directions : '.$response->body());
            }
        } catch (\Throwable $e) {
            Log::error('Exception Google Maps Directions : '.$e->getMessage());
        }

        return null;
    }

    /**
     * Distance à vol d'oiseau (Haversine) + estimation de durée à 40 km/h.
     */
    private function fallbackHaversine(array $from, array $to): array
    {
        $earthRadius = 6371000; // mètres

        $latFrom = deg2rad($from['lat']);
        $lonFrom = deg2rad($from['lng']);
        $latTo = deg2rad($to['lat']);
        $lonTo = deg2rad($to['lng']);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        $distance = $angle * $earthRadius;

        // Vitesse moyenne urbaine ~40 km/h (~11.11 m/s)
        $duration = (int) ($distance / 11.11);

        return [
            'distance' => (int) $distance,
            'duration' => $duration,
            'source' => 'haversine_fallback',
        ];
    }
}
