<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service de calcul d'itinéraire (distance + durée réelles).
 *
 * Fournisseurs, dans l'ordre de préférence :
 *   1. Yandex Distance Matrix API  (services.yandex.distance_matrix_key) — Yandex
 *      est le fournisseur cartographique officiel de la plateforme.
 *   2. Repli géodésique Haversine  (toujours disponible, aucune clé)
 *
 * Le nom de classe est historique et conservé pour compatibilité (mocké dans
 * plusieurs tests) : aucun appel Google n'est effectué.
 * Le contrat public reste `getDirections(array $from, array $to): array` avec
 * `['distance' => mètres, 'duration' => secondes, 'source' => string]`.
 */
class GoogleMapsService
{
    private ?string $yandexKey;

    private string $yandexUrl;

    public function __construct()
    {
        $this->yandexKey = config('services.yandex.distance_matrix_key');
        $this->yandexUrl = config(
            'services.yandex.distance_matrix_url',
            'https://api.routing.yandex.net/v2/distancematrix'
        );
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

        if (! $this->yandexKey) {
            Log::warning('Aucune clé Yandex Distance Matrix configurée. Fallback Haversine.');
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
