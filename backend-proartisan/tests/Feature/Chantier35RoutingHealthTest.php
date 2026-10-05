<?php

use App\Services\Admin\AdminObservabilityService;
use App\Services\DeliveryPricingService;
use App\Services\GoogleMapsService;
use App\Services\RoutingHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\Routing;

/*
 * Chantier 35 — la clé Yandex de production a expiré sans que personne ne
 * soit prévenu : chaque course était tarifée par le serveur public d'OSRM.
 * L'état des services d'itinéraire est désormais suivi et signalé.
 */

uses(RefreshDatabase::class);

const C35_FROM = ['lat' => 5.3484, 'lng' => -4.0267];
const C35_TO = ['lat' => 5.3550, 'lng' => -3.8850];

function c35Yandex(int $status, array $body = []): void
{
    config(['services.yandex.distance_matrix_key' => 'cle-secrete-de-test']);
    Http::fake(['*api.routing.yandex.net*' => Http::response($body, $status)]);
}

function c35Ask(int $times): void
{
    for ($i = 0; $i < $times; $i++) {
        app(GoogleMapsService::class)->getDirections(C35_FROM, C35_TO);
    }
}

test('une clé Yandex refusée trois fois de suite signale un calcul des courses dégradé', function () {
    c35Yandex(403);

    c35Ask(2);
    expect(app(RoutingHealthService::class)->isDegraded())->toBeFalse();

    c35Ask(1);
    $health = app(RoutingHealthService::class);
    $yandex = collect($health->snapshot()['providers'])->firstWhere('key', 'yandex');

    expect($health->isDegraded())->toBeTrue()
        ->and($yandex['failing'])->toBeTrue()
        ->and($yandex['consecutive_failures'])->toBe(3)
        ->and($yandex['last_failure_reason'])->toBe('Clé refusée (HTTP 403) : expirée ou invalide')
        ->and(app(AdminObservabilityService::class)->criticalCounts()['routing_degraded'])->toBe(1);
});

test('le motif retenu ne contient jamais la clé', function () {
    c35Yandex(403, ['error' => 'Invalid key cle-secrete-de-test']);

    c35Ask(3);

    expect(json_encode(app(RoutingHealthService::class)->snapshot()))->not->toContain('cle-secrete-de-test');
});

test('une réponse correcte de Yandex efface le signalement', function () {
    c35Yandex(403);
    c35Ask(3);

    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*api.routing.yandex.net*' => Http::response(['rows' => [['elements' => [['status' => 'OK', 'distance' => ['value' => 9000], 'duration' => ['value' => 1200]]]]]])]);
    c35Ask(1);

    $health = app(RoutingHealthService::class);
    expect($health->isDegraded())->toBeFalse()
        ->and($health->snapshot()['mode'])->toBe('Les courses sont tarifées par Yandex.')
        ->and(app(AdminObservabilityService::class)->criticalCounts()['routing_degraded'])->toBe(0);
});

test('une clé absente est signalée comme une clé refusée', function () {
    config(['services.yandex.distance_matrix_key' => null]);

    c35Ask(3);

    $yandex = collect(app(RoutingHealthService::class)->snapshot()['providers'])->firstWhere('key', 'yandex');
    expect($yandex['failing'])->toBeTrue()
        ->and($yandex['last_failure_reason'])->toBe('Clé absente');
});

test('l\'écran annonce qui tarife les courses, et si le serveur OSRM est celui de démonstration', function () {
    c35Yandex(403);
    Routing::fakeOsrm();

    for ($i = 0; $i < 3; $i++) {
        app(DeliveryPricingService::class)->estimateFare(['from' => C35_FROM, 'to' => C35_TO]);
    }

    $routing = app(AdminObservabilityService::class)->snapshot()['routing'];
    expect($routing['degraded'])->toBeTrue()
        ->and($routing['mode'])->toBe('Yandex ne répond plus : les courses sont tarifées par le serveur OSRM.')
        ->and($routing['osrm_public'])->toBeTrue();
});

test('quand OSRM ne répond pas non plus, l\'écran dit que les courses sont estimées', function () {
    c35Yandex(403);
    Http::fake(['*router.project-osrm.org*' => Http::response(null, 503)]);

    for ($i = 0; $i < 3; $i++) {
        app(DeliveryPricingService::class)->estimateFare(['from' => C35_FROM, 'to' => C35_TO]);
    }

    expect(app(AdminObservabilityService::class)->snapshot()['routing']['mode'])
        ->toBe('Ni Yandex ni OSRM ne répondent : les courses sont tarifées sur une distance estimée.');
});

test('le contrôle de santé alerte sur Telegram quand Yandex ne répond plus', function () {
    c35Yandex(403);
    c35Ask(3);
    config(['services.telegram.bot_token' => 'test-token', 'services.telegram.chat_id' => '123']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->artisan('admin:health-check')->assertExitCode(0);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
        && str_contains((string) $request['text'], 'Yandex ne répond plus'));
});
