<?php

namespace App\Services;

use App\Models\Mission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeoService
{
    /**
     * Recherche les artisans actifs dans un rayon donné (ST_Distance_Sphere MySQL).
     *
     * @return Collection tableau de stdObject {id, phone, name, score_prosartisan, distance_metres, lat, lng}
     */
    public function nearbyArtisans(
        float $lat,
        float $lng,
        int $radiusMeters,
        ?string $sectorFilter = null,
        ?string $tradeFilter = null,
        bool $nightOnly = false
    ): Collection {
        $sectorFilter = $this->sanitizeFilter($sectorFilter);
        $tradeFilter = $this->sanitizeFilter($tradeFilter);

        if (config('database.default') === 'sqlite') {
            $sql = "
                SELECT
                    u.id,
                    u.phone,
                    u.name,
                    u.score_prosartisan,
                    0.0 AS lng,
                    0.0 AS lat,
                    0 AS distance_metres,
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
            ";

            $bindings = [];
            [$sql, $bindings] = $this->appendFilters($sql, $bindings, $sectorFilter, $tradeFilter, $nightOnly);

            return collect(DB::select($sql, $bindings));
        }

        $sql = "
            SELECT
                u.id,
                u.phone,
                u.name,
                u.score_prosartisan,
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
        ";

        $bindings = [$lng, $lat, $lng, $lat, $radiusMeters];
        [$sql, $bindings] = $this->appendFilters($sql, $bindings, $sectorFilter, $tradeFilter, $nightOnly);
        $sql .= ' ORDER BY u.score_prosartisan DESC, distance_metres ASC';

        return collect(DB::select($sql, $bindings));
    }

    /**
     * Floutage de la position GPS : offset aléatoire d'environ 50 m.
     * NE JAMAIS retourner la position exacte d'un artisan au client.
     */
    public function blurPosition(float $lat, float $lng, int $radiusMeters = 50): array
    {
        $delta = $radiusMeters / 111000;
        $angle = mt_rand(0, 359);
        $r = $delta * sqrt(mt_rand(0, 100) / 100);
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
        if (config('database.default') === 'sqlite') {
            $row = DB::table('fournisseurs_agrees')->where('user_id', $fournisseurId)->first();
            if (! $row) {
                return ['valid' => false, 'distance' => null, 'reason' => 'Fournisseur non trouvé.'];
            }

            $pos = explode(',', $row->position);
            if (count($pos) !== 2) {
                return ['valid' => true, 'distance' => 0, 'max' => 100];
            }

            $dist = sqrt(pow($scanLat - (float) $pos[0], 2) + pow($scanLng - (float) $pos[1], 2)) * 111000;

            return [
                'valid' => $dist <= 100,
                'distance' => round($dist, 1),
                'max' => 100,
            ];
        }

        $row = DB::selectOne('
            SELECT ST_Distance_Sphere(
                POINT(?, ?),
                fa.position
            ) AS distance_metres
            FROM fournisseurs_agrees fa
            WHERE fa.user_id = ?
        ', [$scanLng, $scanLat, $fournisseurId]);

        if (! $row) {
            return ['valid' => false, 'distance' => null, 'reason' => 'Fournisseur non trouvé.'];
        }

        $distance = (float) $row->distance_metres;
        $maxDist = config('prosartisan.gps.jcode_max_distance', 100);

        return [
            'valid' => $distance <= $maxDist,
            'distance' => round($distance, 1),
            'max' => $maxDist,
        ];
    }

    /**
     * Vérifie qu'un référent est physiquement proche de la mission.
     * RÈGLE : distance > 500 m → validation refusée.
     */
    public function validateReferentMissionGps(
        Mission $mission,
        float $referentLat,
        float $referentLng
    ): array {
        if ($mission->client_latitude === null || $mission->client_longitude === null) {
            return [
                'valid' => false,
                'distance' => null,
                'max' => 500,
                'reason' => 'La position de la mission est introuvable.',
            ];
        }

        $maxDist = 500;

        if (config('database.default') === 'sqlite') {
            $dist = sqrt(
                pow($referentLat - (float) $mission->client_latitude, 2) +
                pow($referentLng - (float) $mission->client_longitude, 2)
            ) * 111000;

            return [
                'valid' => $dist <= $maxDist,
                'distance' => round($dist, 1),
                'max' => $maxDist,
            ];
        }

        $row = DB::selectOne('
            SELECT ST_Distance_Sphere(
                POINT(?, ?),
                POINT(?, ?)
            ) AS distance_metres
        ', [
            $referentLng,
            $referentLat,
            (float) $mission->client_longitude,
            (float) $mission->client_latitude,
        ]);

        $distance = (float) ($row->distance_metres ?? 0);

        return [
            'valid' => $distance <= $maxDist,
            'distance' => round($distance, 1),
            'max' => $maxDist,
        ];
    }

    /**
     * Formate une distance en mètres pour l'affichage.
     */
    public function formatDistance(float $metres): string
    {
        if ($metres < 1000) {
            return round($metres).' m';
        }

        return round($metres / 1000, 1).' km';
    }

    private function appendFilters(
        string $sql,
        array $bindings,
        ?string $sectorFilter,
        ?string $tradeFilter,
        bool $nightOnly
    ): array {
        if ($sectorFilter !== null) {
            if (is_numeric($sectorFilter)) {
                $sql .= ' AND ap.sector_id = ?';
                $bindings[] = (int) $sectorFilter;
            } else {
                $sql .= ' AND LOWER(s.name) LIKE ?';
                $bindings[] = '%'.$sectorFilter.'%';
            }
        }

        if ($tradeFilter !== null) {
            if (is_numeric($tradeFilter)) {
                $sql .= ' AND ap.trade_id = ?';
                $bindings[] = (int) $tradeFilter;
            } else {
                $sql .= ' AND LOWER(t.name) LIKE ?';
                $bindings[] = '%'.$tradeFilter.'%';
            }
        }

        if ($nightOnly) {
            $sql .= ' AND ap.intervient_la_nuit = ?';
            $bindings[] = 1;
        }

        return [$sql, $bindings];
    }

    private function sanitizeFilter(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(mb_strtolower($value));

        return $normalized === '' ? null : $normalized;
    }
}
