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
                    u.position,
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

            $rows = collect(DB::select($sql, $bindings));

            return $rows->map(function ($row) use ($lat, $lng) {
                if (! empty($row->position) && str_contains((string) $row->position, ',')) {
                    $parts = explode(',', (string) $row->position, 2);
                    $row->lat = (float) trim($parts[0]);
                    $row->lng = (float) trim($parts[1]);
                    $row->distance_metres = (int) round($this->haversineDistanceMeters($lat, $lng, $row->lat, $row->lng));
                }

                return $row;
            })->filter(function ($row) use ($radiusMeters) {
                if (! empty($row->position) && str_contains((string) $row->position, ',')) {
                    return $row->distance_metres <= $radiusMeters;
                }

                return true;
            })->sortBy([
                ['score_prosartisan', 'desc'],
                ['distance_metres', 'asc'],
            ])->values();
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
     * Recherche adaptative d'artisans par paliers concentriques :
     * P1 (2 km) -> P2 (5 km) -> P3 (15 km) -> P4 (50 km).
     *
     * Si un rayon explicite est fourni par le client, on effectue la recherche
     * sur ce rayon uniquement. Sinon, on itère sur les paliers configurés
     * jusqu'à trouver au moins un artisan actif.
     *
     * @return array{
     *     artisans: Collection,
     *     tier_id: string,
     *     tier_label: string,
     *     radius_meters: int,
     *     is_fallback: bool
     * }
     */
    public function adaptiveNearbyArtisans(
        float $lat,
        float $lng,
        ?int $customRadius = null,
        ?string $sectorFilter = null,
        ?string $tradeFilter = null,
        bool $nightOnly = false
    ): array {
        $tiers = config('prosartisan.gps.matching_tiers', [
            ['id' => 'immediate', 'radius' => 2000, 'label' => 'Proximité immédiate (2 km)'],
            ['id' => 'local', 'radius' => 5000, 'label' => 'Zone locale (5 km)'],
            ['id' => 'city', 'radius' => 15000, 'label' => 'Grand Abidjan (15 km)'],
            ['id' => 'extended', 'radius' => 50000, 'label' => 'Zone élargie (50 km)'],
        ]);

        $initialRadius = (int) config('prosartisan.gps.nearby_artisan_radius', 2000);

        if ($customRadius !== null) {
            $artisans = $this->nearbyArtisans($lat, $lng, $customRadius, $sectorFilter, $tradeFilter, $nightOnly);

            return [
                'artisans' => $artisans,
                'tier_id' => 'custom',
                'tier_label' => "Rayon personnalisé ({$customRadius} m)",
                'radius_meters' => $customRadius,
                'is_fallback' => $customRadius > $initialRadius,
            ];
        }

        $activeTier = $tiers[0] ?? ['id' => 'immediate', 'radius' => 2000, 'label' => 'Proximité immédiate (2 km)'];
        $matchedArtisans = collect();

        foreach ($tiers as $tier) {
            $activeTier = $tier;
            $matchedArtisans = $this->nearbyArtisans(
                $lat,
                $lng,
                (int) $tier['radius'],
                $sectorFilter,
                $tradeFilter,
                $nightOnly
            );

            if ($matchedArtisans->isNotEmpty()) {
                break;
            }
        }

        $radiusUsed = (int) $activeTier['radius'];

        return [
            'artisans' => $matchedArtisans,
            'tier_id' => (string) $activeTier['id'],
            'tier_label' => (string) $activeTier['label'],
            'radius_meters' => $radiusUsed,
            'is_fallback' => $radiusUsed > $initialRadius,
        ];
    }

    /**
     * Distance orthodromique en mètres (formule de Haversine).
     */
    public function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // mètres
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Floutage de la position GPS : offset d'environ 50 m.
     * NE JAMAIS retourner la position exacte d'un artisan au client.
     *
     * Lorsqu'un [$seed] est fourni (ex: l'id de l'artisan), l'offset est
     * DÉTERMINISTE et STABLE dans le temps : le marqueur ne « saute » plus à
     * chaque requête et, surtout, un client ne peut plus recouvrer la position
     * réelle en moyennant plusieurs réponses successives. Le seed est mélangé à
     * `APP_KEY` via HMAC : l'offset reste imprévisible sans la clé serveur.
     */
    public function blurPosition(float $lat, float $lng, int $radiusMeters = 50, ?string $seed = null): array
    {
        $delta = $radiusMeters / 111000;

        if ($seed !== null) {
            $hash = hash_hmac('sha256', $seed, (string) config('app.key'));
            $angle = hexdec(substr($hash, 0, 8)) % 360;
            $r = $delta * sqrt((hexdec(substr($hash, 8, 8)) % 1000) / 1000);
        } else {
            $angle = mt_rand(0, 359);
            $r = $delta * sqrt(mt_rand(0, 100) / 100);
        }

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
