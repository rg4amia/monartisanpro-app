<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeoService
{
    /**
     * Recherche les artisans actifs dans un rayon donné (ST_Distance_Sphere MySQL).
     *
     * @return Collection tableau de stdObject {id, phone, name, score_nzassa, distance_metres, lat, lng}
     */
    public function nearbyArtisans(float $lat, float $lng, int $radiusMeters): Collection
    {
        $rows = DB::select("
            SELECT
                u.id,
                u.phone,
                u.name,
                u.score_nzassa,
                ST_X(u.position) AS lng,
                ST_Y(u.position) AS lat,
                ST_Distance_Sphere(u.position, POINT(?, ?)) AS distance_metres,
                ap.photo_url,
                ap.bio,
                ap.experience_years,
                t.name AS trade_name,
                s.name AS sector_name
            FROM users u
            LEFT JOIN artisan_profiles ap ON ap.user_id = u.id
            LEFT JOIN trades t ON t.id = ap.trade_id
            LEFT JOIN sectors s ON s.id = ap.sector_id
            WHERE u.role = 'artisan'
              AND u.kyc_status = 'actif'
              AND u.position IS NOT NULL
              AND ST_Distance_Sphere(u.position, POINT(?, ?)) <= ?
            ORDER BY u.score_nzassa DESC, distance_metres ASC
        ", [$lng, $lat, $lng, $lat, $radiusMeters]);

        return collect($rows);
    }

    /**
     * Floutage de la position GPS : offset aléatoire d'environ 50 m.
     * NE JAMAIS retourner la position exacte d'un artisan au client.
     */
    public function blurPosition(float $lat, float $lng, int $radiusMeters = 50): array
    {
        // Convertit les mètres en degrés (~111 000 m / degré)
        $delta      = $radiusMeters / 111000;
        $angle      = mt_rand(0, 359);
        $r          = $delta * sqrt(mt_rand(0, 100) / 100);
        $blurredLat = $lat + $r * cos(deg2rad($angle));
        $blurredLng = $lng + $r * sin(deg2rad($angle));

        return ['lat' => round($blurredLat, 6), 'lng' => round($blurredLng, 6)];
    }

    /**
     * Vérifie si un fournisseur est bien à sa boutique lors du scan J-Code.
     * RÈGLE CRITIQUE : distance > 100 m → blocage automatique.
     */
    public function validateJCodeGps(int $fournisseurId, float $scanLat, float $scanLng): array
    {
        $row = DB::selectOne("
            SELECT ST_Distance_Sphere(
                POINT(?, ?),
                fa.position
            ) AS distance_metres
            FROM fournisseurs_agrees fa
            WHERE fa.user_id = ?
        ", [$scanLng, $scanLat, $fournisseurId]);

        if (! $row) {
            return ['valid' => false, 'distance' => null, 'reason' => 'Fournisseur non trouvé.'];
        }

        $distance  = (float) $row->distance_metres;
        $maxDist   = config('prosartisan.gps.jcode_max_distance', 100);
        $isValid   = $distance <= $maxDist;

        return [
            'valid'    => $isValid,
            'distance' => round($distance, 1),
            'max'      => $maxDist,
        ];
    }

    /**
     * Formate une distance en mètres pour l'affichage.
     */
    public function formatDistance(float $metres): string
    {
        if ($metres < 1000) {
            return round($metres) . ' m';
        }

        return round($metres / 1000, 1) . ' km';
    }
}
