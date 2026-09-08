<?php

/**
 * Tests Pest – FEATURE : géolocalisation d'un artisan et d'un client
 * autour d'une mission validée (devis accepté + séquestre constitué).
 *
 * Vérifie de bout en bout :
 *   – le client peut enregistrer sa position (PUT /users/{id}/location) ;
 *   – l'artisan affecté récupère la position EXACTE du chantier via /site-map
 *     une fois la mission financée ;
 *   – le client ne voit JAMAIS la position exacte de l'artisan : /artisans/{id}
 *     renvoie une position FLOUTÉE (~50 m), stable d'un appel à l'autre
 *     (règle d'or #6 — pas de récupération par moyennage).
 */

use App\Models\Mission;
use App\Models\User;

function haversineMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function geoScenario(): array
{
    $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
    $artisan->artisanProfile()->create(['experience_years' => 4]);

    // Mission "validée" : devis accepté → séquestre → in_progress.
    $mission = Mission::create([
        'client_id' => $client->id,
        'artisan_id' => $artisan->id,
        'description' => 'Pose de carrelage',
        'status' => 'in_progress',
        'montant_total' => 300000,
        'montant_materiaux' => 195000,
        'montant_mo' => 105000,
        'ratio_materiaux' => 0.65,
        'client_latitude' => 5.359500,
        'client_longitude' => -4.008300,
        'client_address' => 'II Plateaux, Cocody',
    ]);

    return [$client, $artisan, $mission];
}

it('le client enregistre sa position GPS', function () {
    [$client] = geoScenario();

    $this->actingAs($client)
        ->putJson("/api/v1/users/{$client->id}/location", [
            'lat' => 5.3599,
            'lng' => -4.0083,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($client->fresh()->getPositionCoords())
        ->toMatchArray(['lat' => 5.3599, 'lng' => -4.0083]);
});

it('l\'artisan affecté obtient la position exacte du chantier (mission financée)', function () {
    [, $artisan, $mission] = geoScenario();

    $this->actingAs($artisan)
        ->getJson("/api/v1/missions/{$mission->id}/site-map")
        ->assertOk()
        ->assertJsonPath('data.client.coordinates.lat', 5.3595)
        ->assertJsonPath('data.client.coordinates.lng', -4.0083)
        ->assertJsonPath('data.client.address', 'II Plateaux, Cocody');
});

it('le client ne voit qu\'une position FLOUTÉE de l\'artisan', function () {
    [$client, $artisan] = geoScenario();

    $realLat = 5.348000;
    $realLng = -4.016900;
    $artisan->setPosition($realLat, $realLng);

    $response = $this->actingAs($client)
        ->getJson("/api/v1/artisans/{$artisan->id}")
        ->assertOk();

    $blurred = $response->json('data.location');
    expect($blurred)->toBeArray()
        ->and($blurred['lat'])->not->toBe($realLat)
        ->and($blurred['lng'])->not->toBe($realLng);

    // L'offset est réel (déplacement effectif) mais reste dans le rayon de
    // floutage (~50 m + marge d'arrondi).
    expect(abs($blurred['lat'] - $realLat))->toBeGreaterThan(0.0);
    $distance = haversineMetres($realLat, $realLng, $blurred['lat'], $blurred['lng']);
    expect($distance)->toBeGreaterThan(0.0)->toBeLessThanOrEqual(60.0);

    // La colonne `position` (coordonnées exactes) n'est jamais sérialisée.
    expect($response->json('data'))->not->toHaveKey('position');
});

it('la position floutée de l\'artisan est stable entre deux appels', function () {
    [$client, $artisan] = geoScenario();
    $artisan->setPosition(5.348000, -4.016900);

    $first = $this->actingAs($client)
        ->getJson("/api/v1/artisans/{$artisan->id}")
        ->json('data.location');

    $second = $this->actingAs($client)
        ->getJson("/api/v1/artisans/{$artisan->id}")
        ->json('data.location');

    expect($first)->toEqual($second);
});

it('un client tiers ne peut pas lire la carte du chantier', function () {
    [, , $mission] = geoScenario();
    $autreClient = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

    $this->actingAs($autreClient)
        ->getJson("/api/v1/missions/{$mission->id}/site-map")
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Recherche géospatiale réelle (ST_Distance_Sphere) — MySQL uniquement
|--------------------------------------------------------------------------
*/

it('recherche les artisans proches et renvoie leur position floutée', function () {
    if (config('database.default') === 'sqlite') {
        $this->markTestSkipped('Requiert MySQL avec support géospatial (ST_Distance_Sphere).');
    }

    $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

    // Artisan à ~300 m du client.
    $proche = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'name' => 'Artisan Proche']);
    $proche->artisanProfile()->create(['experience_years' => 3]);
    $procheLat = 5.3510;
    $procheLng = -4.0169;
    $proche->setPosition($procheLat, $procheLng);

    // Artisan à ~8 km (hors du rayon 2 km initial).
    $loin = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'name' => 'Artisan Loin']);
    $loin->artisanProfile()->create(['experience_years' => 3]);
    $loin->setPosition(5.4200, -4.0169);

    $response = $this->actingAs($client)
        ->getJson('/api/v1/artisans?lat=5.3484&lng=-4.0169&radius=2000')
        ->assertOk()
        ->assertJsonPath('meta.is_fallback', false);

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Artisan Proche')       // ~300 m → inclus
        ->and($names)->not->toContain('Artisan Loin'); // ~8 km → exclu par le rayon

    $procheData = collect($response->json('data'))
        ->firstWhere('name', 'Artisan Proche');
    $location = $procheData['location'];
    expect($location)->toBeArray();

    // Position floutée : proche de la réalité mais jamais exacte.
    $distance = haversineMetres($procheLat, $procheLng, $location['lat'], $location['lng']);
    expect($distance)->toBeGreaterThan(0.0)->toBeLessThanOrEqual(60.0);
    expect($location['lat'])->not->toBe($procheLat);
});
