<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsService
{
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key') ?? env('GOOGLE_MAPS_API_KEY');
    }

    /**
     * Récupère la distance (mètres) et la durée (secondes) de trajet réel via Google Directions API.
     * Si l'API échoue ou n'est pas configurée, bascule vers un calcul de distance géodésique (Haversine).
     */
    public function getDirections(array $from, array $to): array
    {
        if (!$this->apiKey) {
            Log::warning('Google Maps API Key manquante. Utilisation du fallback Haversine.');
            return $this->fallbackHaversine($from, $to);
        }

        try {
            $origin = "{$from['lat']},{$from['lng']}";
            $destination = "{$to['lat']},{$to['lng']}";

            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => $origin,
                'destination' => $destination,
                'key' => $this->apiKey,
                'mode' => 'driving',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['routes'][0]['legs'][0])) {
                    $leg = $data['routes'][0]['legs'][0];
                    $distance = $leg['distance']['value']; // en mètres
                    $duration = $leg['duration']['value']; // en secondes
                    return [
                        'distance' => $distance,
                        'duration' => $duration,
                        'source' => 'google_maps',
                    ];
                }
                Log::warning('Aucune route trouvée par Google Maps. Utilisation du fallback.', ['data' => $data]);
            } else {
                Log::error('Erreur API Google Maps Directions : ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Exception lors de l\'appel Google Maps Directions : ' . $e->getMessage());
        }

        return $this->fallbackHaversine($from, $to);
    }

    /**
     * Calcul de distance à vol d'oiseau (Haversine) avec estimation du temps à 40 km/h de moyenne.
     */
    private function fallbackHaversine(array $from, array $to): array
    {
        $earthRadius = 6371000; // en mètres

        $latFrom = deg2rad($from['lat']);
        $lonFrom = deg2rad($from['lng']);
        $latTo = deg2rad($to['lat']);
        $lonTo = deg2rad($to['lng']);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        $distance = $angle * $earthRadius; // en mètres

        // Estimation de durée : vitesse moyenne de 40 km/h (soit ~11.11 m/s) en ville
        $averageSpeedMs = 11.11;
        $duration = (int)($distance / $averageSpeedMs); // en secondes

        return [
            'distance' => (int)$distance,
            'duration' => $duration,
            'source' => 'haversine_fallback',
        ];
    }
}
