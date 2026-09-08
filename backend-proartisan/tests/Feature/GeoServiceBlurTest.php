<?php

/**
 * Tests Pest – FEATURE : floutage GPS des artisans (règle d'or #6).
 *
 * Vérifie que blurPosition() :
 *   – décale la position d'au plus ~ le rayon demandé ;
 *   – est DÉTERMINISTE quand un seed est fourni (le marqueur ne saute plus et
 *     un client ne peut pas recouvrer la position réelle en moyennant les
 *     réponses successives) ;
 *   – reste aléatoire sans seed (rétro-compatibilité).
 */

use App\Services\GeoService;

beforeEach(function () {
    $this->geo = new GeoService;
});

it('décale la position dans le rayon de floutage', function () {
    $lat = 5.3484;
    $lng = -4.0169;

    $blurred = $this->geo->blurPosition($lat, $lng, 50, '42');

    $metres = sqrt(
        ($blurred['lat'] - $lat) ** 2 + ($blurred['lng'] - $lng) ** 2
    ) * 111000;

    expect($metres)->toBeGreaterThan(0.0)
        ->and($metres)->toBeLessThanOrEqual(55.0); // 50 m + marge d'arrondi
});

it('est déterministe pour un même seed', function () {
    $a = $this->geo->blurPosition(5.3484, -4.0169, 50, '1001');
    $b = $this->geo->blurPosition(5.3484, -4.0169, 50, '1001');

    expect($a)->toBe($b);
});

it('produit des offsets différents pour des seeds différents', function () {
    $a = $this->geo->blurPosition(5.3484, -4.0169, 50, '1001');
    $b = $this->geo->blurPosition(5.3484, -4.0169, 50, '1002');

    expect($a)->not->toBe($b);
});

it('reste aléatoire sans seed', function () {
    $results = collect(range(1, 20))->map(
        fn () => $this->geo->blurPosition(5.3484, -4.0169, 50)
    )->unique();

    expect($results->count())->toBeGreaterThan(1);
});
