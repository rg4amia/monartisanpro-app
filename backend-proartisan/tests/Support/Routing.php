<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

/**
 * Itinéraire OSRM simulé, pour les parcours qui estiment une course ou tracent
 * un suivi sans que le routage soit leur objet.
 *
 * Sans lui, l'appel au serveur public OSRM est bloqué par
 * `Http::preventStrayRequests()` et la course se rabat sur une estimation à
 * vol d'oiseau : le test passe, mais sur un tarif que la production n'applique
 * qu'en dernier recours, et le journal se remplit d'avertissements.
 */
final class Routing
{
    /** Itinéraire de 4,2 km en 10 minutes par défaut. */
    public static function fakeOsrm(float $distanceKm = 4.2, float $durationMin = 10.0): void
    {
        Http::fake([
            '*router.project-osrm.org*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => $distanceKm * 1000,
                    'duration' => $durationMin * 60,
                    'geometry' => ['coordinates' => [[-4.0267, 5.3484], [-4.0083, 5.3599]]],
                    'legs' => [],
                ]],
            ], 200),
        ]);
    }
}
