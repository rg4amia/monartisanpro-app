<?php

use App\Models\Address;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\DeliveryBatchService;
use App\Services\DeliveryPricingService;
use App\Services\DeliveryTrackingService;
use App\Services\GoogleMapsService;
use App\Services\OrderService;
use App\Services\OsrmRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo;

/*
 * Chantier 31 — la course d'une commande se calcule vers l'adresse de
 * livraison choisie, figée sur la commande, jamais vers la position du compte
 * du client : un client qui se fait livrer ailleurs payait une course calculée
 * vers le mauvais point, et le livreur suivait un itinéraire vers ce même point.
 */

uses(RefreshDatabase::class);

// Trois points distincts d'Abidjan : la boutique, le compte du client, le chantier livré.
const C31_BOUTIQUE = ['lat' => 5.3484, 'lng' => -4.0267];
const C31_COMPTE = ['lat' => 5.3200, 'lng' => -4.0200];
const C31_CHANTIER = ['lat' => 5.3550, 'lng' => -3.8850];
const C31_AUTRE = ['lat' => 5.2900, 'lng' => -3.9900];

beforeEach(function () {
    $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $this->client->setPosition(C31_COMPTE['lat'], C31_COMPTE['lng']);

    $this->address = Address::create([
        'user_id' => $this->client->id,
        'recipient_name' => 'Chef de chantier',
        'recipient_phone' => '+2250101010101',
        'address_line' => 'Chantier Bingerville',
        'city' => 'Bingerville',
        'is_default' => true,
    ]);
    $this->address->setPosition(C31_CHANTIER['lat'], C31_CHANTIER['lng']);

    $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    FournisseurAgree::create([
        'user_id' => $this->supplier->id,
        'nom_boutique' => 'Quincaillerie Centrale',
        'position' => Geo::point(C31_BOUTIQUE['lat'], C31_BOUTIQUE['lng']),
        'statut' => 'agree',
        'approuve_at' => now(),
    ]);
    $this->product = SupplierProduct::create(['supplier_id' => $this->supplier->id, 'sku' => 'CIM-1', 'name' => 'Vis', 'unit_price' => 5000, 'stock_quantity' => 50, 'is_active' => true]);
});

function c31Order($test, string $status = 'searching_driver'): Order
{
    $order = app(OrderService::class)->createOrder(
        $test->client,
        $test->supplier,
        [['supplier_product_id' => $test->product->id, 'quantity' => 1]],
        'delivery',
        'moto',
        1.0,
        null,
        $test->address->fresh(),
    );
    $order->forceFill(['status' => $status])->save();

    return $order->fresh();
}

/** Simule le service de distances et retient chaque destination demandée. */
function c31CaptureDirections(): ArrayObject
{
    $destinations = new ArrayObject;
    $maps = Mockery::mock(GoogleMapsService::class);
    $maps->shouldReceive('getDirections')->andReturnUsing(function (array $from, array $to) use ($destinations) {
        $destinations->append($to);

        return ['distance' => 12000, 'duration' => 1500, 'source' => 'mocked'];
    });
    app()->instance(GoogleMapsService::class, $maps);

    return $destinations;
}

test('la commande fige les coordonnées de l\'adresse de livraison', function () {
    $order = c31Order($this);

    expect(round($order->delivery_latitude, 4))->toBe(C31_CHANTIER['lat'])
        ->and(round($order->delivery_longitude, 4))->toBe(C31_CHANTIER['lng'])
        ->and($order->deliveryDestination())->toBe(C31_CHANTIER);
});

test('la commande groupée fige aussi les coordonnées de l\'adresse de livraison', function () {
    c31CaptureDirections();

    $result = app(OrderService::class)->createMultiSupplierOrders(
        $this->client,
        [['supplier_id' => $this->supplier->id, 'delivery_mode' => 'delivery', 'items' => [['supplier_product_id' => $this->product->id, 'quantity' => 1]]]],
        null,
        $this->address,
    );

    $order = Order::where('supplier_id', $this->supplier->id)->where('is_parent_group', false)->latest('id')->firstOrFail();
    expect($result)->not->toBeNull()
        ->and($order->deliveryDestination())->toBe(C31_CHANTIER);
});

test('modifier l\'adresse du carnet ne déplace pas une commande déjà passée', function () {
    $order = c31Order($this);

    $this->address->setPosition(C31_AUTRE['lat'], C31_AUTRE['lng']);

    expect($order->fresh()->deliveryDestination())->toBe(C31_CHANTIER);
});

test('la course se calcule vers l\'adresse de livraison, pas vers la position du compte du client', function () {
    $destinations = c31CaptureDirections();
    $order = c31Order($this);

    app(DeliveryPricingService::class)->calculateOrderDeliveryCost($order);

    expect($destinations)->toHaveCount(1)
        ->and($destinations[0])->toBe(C31_CHANTIER);
});

test('le suivi de livraison trace l\'itinéraire vers l\'adresse de livraison', function () {
    $routed = new ArrayObject;
    $this->mock(OsrmRoutingService::class, fn ($mock) => $mock->shouldReceive('calculateRoute')
        ->andReturnUsing(function (float $fromLat, float $fromLng, float $toLat, float $toLng) use ($routed) {
            $routed->append(['lat' => $toLat, 'lng' => $toLng]);

            return ['distance_km' => 12.0, 'duration_min' => 25.0, 'is_fallback' => false];
        }));
    $order = c31Order($this, 'driver_assigned');

    $tracking = app(DeliveryTrackingService::class)->getDeliveryTrackingData($order);

    expect($routed[0])->toBe(C31_CHANTIER)
        ->and($tracking['client']['position'])->toBe(C31_CHANTIER);
});

test('une tournée groupée passe par l\'adresse figée, même si le carnet a changé depuis', function () {
    $waypoints = new ArrayObject;
    $this->mock(OsrmRoutingService::class, fn ($mock) => $mock->shouldReceive('calculateMultiDropRoute')
        ->andReturnUsing(function (array $points) use ($waypoints) {
            $waypoints->exchangeArray($points);

            return ['distance_km' => 20.0, 'duration_min' => 40.0, 'stops' => [], 'is_fallback' => false];
        }));
    c31Order($this);
    c31Order($this);

    $this->address->setPosition(C31_AUTRE['lat'], C31_AUTRE['lng']);
    app(DeliveryBatchService::class)->findAvailableBatches();

    $deliveries = collect($waypoints)->where('type', 'delivery')->values();
    expect($deliveries)->toHaveCount(2);
    foreach ($deliveries as $stop) {
        expect(['lat' => $stop['lat'], 'lng' => $stop['lng']])->toBe(C31_CHANTIER);
    }
});

test('une commande antérieure, sans coordonnées figées, garde l\'adresse du carnet puis la position du client', function () {
    $order = c31Order($this);
    $order->forceFill(['delivery_latitude' => null, 'delivery_longitude' => null])->save();

    expect($order->fresh()->deliveryDestination())->toBe(C31_CHANTIER);

    $order->forceFill(['address_id' => null])->save();
    expect($order->fresh()->deliveryDestination())->toBe(C31_COMPTE);
});
