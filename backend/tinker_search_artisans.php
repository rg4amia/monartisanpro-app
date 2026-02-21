<?php

/**
 * Script Tinker pour tester la recherche d'artisans
 *
 * Usage: php artisan tinker < tinker_search_artisans.php
 * Ou dans tinker: include 'tinker_search_artisans.php';
 */

use App\Models\User;
use App\Models\ArtisanProfile;
use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Support\Facades\Http;

echo "\n🔍 Test de recherche d'artisans\n";
echo "================================\n\n";

// 1. Vérifier les données de base
echo "📊 Données disponibles:\n";
echo "- Artisans: " . User::where('role', 'artisan')->count() . "\n";
echo "- Profils artisans: " . Art
isanProfile::count() . "\n";
echo "- Secteurs: " . Sector::count() . "\n";
echo "- Métiers: " . Trade::count() . "\n\n";

// 2. Lister tous les artisans avec leurs coordonnées
echo "👷 Liste des artisans:\n";
echo str_repeat("-", 80) . "\n";

$artisans = User::where('role', 'artisan')
    ->with(['artisanProfile.trade.sector'])
    ->get();

foreach ($artisans as $artisan) {
    $profile = $artisan->artisanProfile;
    if ($profile) {
        $coords = $profile->location;
        echo sprintf(
            "% "📍 Point de référence: Cocody, Abidjan\n";
echo "   Latitude: {$referencePoint['latitude']}\n";
echo "   Longitude: {$referencePoint['longitude']}\n\n";

// Exemple 1: Recherche tous les artisans dans un rayon de 5km
echo "1️⃣  Recherche: Tous les artisans dans un rayon de 5km\n";
echo "   GET /api/v1/artisans/search\n";
echo "   Params: {\n";
echo "     latitude: {$referencePoint['latitude']},\n";
echo "     longitude: {$referencePoint['longitude']},\n";
echo "     radius: 5000\n";
echo "   }\n\n";

// Simuler la recherche
$results = ArtisanProfile::query()
    ->selectRaw("
        *,
        ST_Distance_Sphere(
            location,
            ST_GeomFromText('POINT({$referencePoint['longitude']} {$referencePoint['latitude']})', 4326)
        ) as distance
    ")
    ->having('distance', '<=', 5000)
    ->orderBy('distance')
    ->with(['user', 'trade'])
    ->get();

echo "   ✅ Résultats: {$results->count()} artisan(s) trouvé(s)\n";
foreach ($results as $result) {
    echo sprintf(
        "      - %s (%s) - %.0fm\n",
        $result->user->name,
        $result->trade->name,
        $result->distance
    );
}
echo "\n";

// Exemple 2: Recherche par métier spécifique
$electricienTrade = Trade::where('name', 'like', '%Électricien%')->first();
if ($electricienTrade) {
    echo "2️⃣  Recherche: Électriciens dans un rayon de 10km\n";
    echo "   GET /api/v1/artisans/search\n";
    echo "   Params: {\n";
    echo "     latitude: {$referencePoint['latitude']},\n";
    echo "     longitude: {$referencePoint['longitude']},\n";
    echo "     radius: 10000,\n";
    echo "     trade_id: {$electricienTrade->id}\n";
    echo "   }\n\n";

    $results = ArtisanProfile::query()
        ->where('trade_id', $electricienTrade->id)
        ->selectRaw("
            *,
            ST_Distance_Sphere(
                location,
                ST_GeomFromText('POINT({$referencePoint['longitude']} {$referencePoint['latitude']})', 4326)
            ) as distance
        ")
        ->having('distance', '<=', 10000)
        ->orderBy('distance')
        ->with(['user', 'trade'])
        ->get();

    echo "   ✅ Résultats: {$results->count()} électricien(s) trouvé(s)\n";
    foreach ($results as $result) {
        echo sprintf(
            "      - %s - %.0fm - %d ans d'exp.\n",
            $result->user->name,
            $result->distance,
            $result->experience_years
        );
    }
    echo "\n";
}

// Exemple 3: Recherche par secteur
$batimentSector = Sector::where('name', 'like', '%Bâtiment%')->first();
if ($batimentSector) {
    echo "3️⃣  Recherche: Artisans du secteur Bâtiment dans un rayon de 10km\n";
    echo "   GET /api/v1/artisans/search\n";
    echo "   Params: {\n";
    echo "     latitude: {$referencePoint['latitude']},\n";
    echo "     longitude: {$referencePoint['longitude']},\n";
    echo "     radius: 10000,\n";
    echo "     sector_id: {$batimentSector->id}\n";
    echo "   }\n\n";

    $results = ArtisanProfile::query()
        ->whereHas('trade', function($q) use ($batimentSector) {
            $q->where('sector_id', $batimentSector->id);
        })
        ->selectRaw("
            *,
            ST_Distance_Sphere(
                location,
                ST_GeomFromText('POINT({$referencePoint['longitude']} {$referencePoint['latitude']})', 4326)
            ) as distance
        ")
        ->having('distance', '<=', 10000)
        ->orderBy('distance')
        ->with(['user', 'trade'])
        ->get();

    echo "   ✅ Résultats: {$results->count()} artisan(s) trouvé(s)\n";
    foreach ($results as $result) {
        echo sprintf(
            "      - %s (%s) - %.0fm\n",
            $result->user->name,
            $result->trade->name,
            $result->distance
        );
    }
    echo "\n";
}

// 4. Commandes cURL pour tester l'API réelle
echo "🔧 Commandes cURL pour tester l'API:\n";
echo str_repeat("-", 80) . "\n\n";

$baseUrl = "https://prosartisan.net/api/v1";
// ou pour local: $baseUrl = "http://localhost:8000/api/v1";

echo "# Recherche tous les artisans (rayon 5km)\n";
echo "curl -X GET '{$baseUrl}/artisans/search?' \\\n";
echo "  -G \\\n";
echo "  --data-urlencode 'latitude={$referencePoint['latitude']}' \\\n";
echo "  --data-urlencode 'longitude={$referencePoint['longitude']}' \\\n";
echo "  --data-urlencode 'radius=5000'\n\n";

if ($electricienTrade) {
    echo "# Recherche électriciens (rayon 10km)\n";
    echo "curl -X GET '{$baseUrl}/artisans/search?' \\\n";
    echo "  -G \\\n";
    echo "  --data-urlencode 'latitude={$referencePoint['latitude']}' \\\n";
    echo "  --data-urlencode 'longitude={$referencePoint['longitude']}' \\\n";
    echo "  --data-urlencode 'radius=10000' \\\n";
    echo "  --data-urlencode 'trade_id={$electricienTrade->id}'\n\n";
}

if ($batimentSector) {
    echo "# Recherche secteur Bâtiment (rayon 10km)\n";
    echo "curl -X GET '{$baseUrl}/artisans/search?' \\\n";
    echo "  -G \\\n";
    echo "  --data-urlencode 'latitude={$referencePoint['latitude']}' \\\n";
    echo "  --data-urlencode 'longitude={$referencePoint['longitude']}' \\\n";
    echo "  --data-urlencode 'radius=10000' \\\n";
    echo "  --data-urlencode 'sector_id={$batimentSector->id}'\n\n";
}

// 5. Données JSON pour Flutter/Postman
echo "📱 Exemple de réponse JSON attendue:\n";
echo str_repeat("-", 80) . "\n";

$sampleArtisan = $artisans->first();
if ($sampleArtisan && $sampleArtisan->artisanProfile) {
    $profile = $sampleArtisan->artisanProfile;
    $sampleResponse = [
        'success' => true,
        'data' => [
            [
                'id' => $sampleArtisan->id,
                'name' => $sampleArtisan->name,
                'phone' => $sampleArtisan->phone,
                'email' => $sampleArtisan->email,
                'avatar' => $sampleArtisan->avatar,
                'profile' => [
                    'trade_id' => $profile->trade_id,
                    'trade_name' => $profile->trade->name,
                    'sector_id' => $profile->trade->sector_id,
                    'sector_name' => $profile->trade->sector->name,
                    'bio' => $profile->bio,
                    'experience_years' => $profile->experience_years,
                    'zone_name' => $profile->zone_name,
                    'available' => $profile->available,
                    'latitude' => $profile->location->latitude,
                    'longitude' => $profile->location->longitude,
                ],
                'distance' => 1234.56,
                'score' => [
                    'total_score' => 85,
                    'reliability_score' => 90,
                    'quality_score' => 88,
                    'communication_score' => 82,
                ],
                'stats' => [
                    'completed_projects' => 5,
                    'average_rating' => 4.5,
                    'total_reviews' => 3,
                ],
            ],
        ],
        'meta' => [
            'total' => 1,
            'search_params' => [
                'latitude' => $referencePoint['latitude'],
                'longitude' => $referencePoint['longitude'],
                'radius' => 5000,
            ],
        ],
    ];

    echo json_encode($sampleResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";
}

// 6. Points de test pour différentes zones d'Abidjan
echo "📍 Points de test pour différentes zones:\n";
echo str_repeat("-", 80) . "\n";

$testPoints = [
    'Cocody Angré' => ['lat' => 5.3364, 'lng' => -4.0267],
    'Plateau Centre' => ['lat' => 5.3333, 'lng' => -4.0333],
    'Marcory Zone 4' => ['lat' => 5.3500, 'lng' => -4.0100],
    'Adjamé Liberté' => ['lat' => 5.3200, 'lng' => -4.0500],
    'Yopougon Niangon' => ['lat' => 5.3450, 'lng' => -4.0350],
    'Treichville' => ['lat' => 5.3400, 'lng' => -4.0200],
];

foreach ($testPoints as $zone => $coords) {
    $count = ArtisanProfile::query()
        ->selectRaw("
            *,
            ST_Distance_Sphere(
                location,
                ST_GeomFromText('POINT({$coords['lng']} {$coords['lat']})', 4326)
            ) as distance
        ")
        ->having('distance', '<=', 5000)
        ->count();

    echo sprintf(
        "%-20s | Lat: %8.4f, Lng: %8.4f | %d artisan(s) dans 5km\n",
        $zone,
        $coords['lat'],
        $coords['lng'],
        $count
    );
}

echo "\n✅ Script terminé!\n\n";
