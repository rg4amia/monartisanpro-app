<?php

use App\Models\Address;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\OsrmRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(OsrmRoutingService::class, function ($mock) {
        $mock->shouldReceive('calculateMultiDropRoute')->andReturn([
            'distance_km' => 7.8,
            'duration_min' => 18.5,
            'eta' => now()->addMinutes(19)->toIso8601String(),
            'geometry' => [[-4.01, 5.35], [-4.02, 5.36], [-4.03, 5.37]],
            'coordinates' => [[-4.01, 5.35], [-4.02, 5.36], [-4.03, 5.37]],
            'stops' => [
                [
                    'index' => 0,
                    'label' => 'Quincaillerie Centrale',
                    'type' => 'pickup',
                    'lat' => 5.35,
                    'lng' => -4.01,
                    'eta' => now()->toIso8601String(),
                    'leg_distance_km' => 0.0,
                    'leg_duration_min' => 0.0,
                ],
                [
                    'index' => 1,
                    'label' => 'Chantier : Cocody',
                    'type' => 'delivery',
                    'order_id' => 1,
                    'lat' => 5.36,
                    'lng' => -4.02,
                    'eta' => now()->addMinutes(10)->toIso8601String(),
                    'leg_distance_km' => 4.2,
                    'leg_duration_min' => 10.0,
                ],
                [
                    'index' => 2,
                    'label' => 'Chantier : Riviera',
                    'type' => 'delivery',
                    'order_id' => 2,
                    'lat' => 5.37,
                    'lng' => -4.03,
                    'eta' => now()->addMinutes(19)->toIso8601String(),
                    'leg_distance_km' => 3.6,
                    'leg_duration_min' => 8.5,
                ],
            ],
            'is_fallback' => false,
            'provider' => 'osrm',
        ]);
    });
});

test('non-driver or unverified user cannot access delivery batches', function () {
    $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $unverifiedDriver = User::factory()->create(['role' => 'driver', 'kyc_status' => 'en_attente']);

    $this->actingAs($client)->getJson('/api/v1/deliveries/batches')
        ->assertStatus(403);

    $this->actingAs($unverifiedDriver)->getJson('/api/v1/deliveries/batches')
        ->assertStatus(403);
});

test('verified driver can view available delivery batches grouped by supplier', function () {
    $driver = User::factory()->create([
        'role' => 'driver',
        'kyc_status' => 'actif',
        'payment_phone' => '+2250707070707',
        'preferred_payment_provider' => 'wave',
    ]);

    $supplier = User::factory()->create(['role' => 'fournisseur']);
    $fa = FournisseurAgree::create([
        'position' => Geo::point(-4.01, 5.35),
        'user_id' => $supplier->id,
        'nom_boutique' => 'Quincaillerie Centrale',
        'statut' => 'agree',
    ]);

    $client1 = User::factory()->create(['role' => 'client', 'name' => 'Client A']);
    $client2 = User::factory()->create(['role' => 'client', 'name' => 'Client B']);

    $addr1 = Address::create([
        'user_id' => $client1->id,
        'recipient_name' => 'Client A',
        'recipient_phone' => '+2250101010101',
        'address_line' => 'Cocody Angré',
        'city' => 'Abidjan',
    ]);
    $addr1->setPosition(5.36, -4.02);

    $addr2 = Address::create([
        'user_id' => $client2->id,
        'recipient_name' => 'Client B',
        'recipient_phone' => '+2250202020202',
        'address_line' => 'Riviera Palmeraie',
        'city' => 'Abidjan',
    ]);
    $addr2->setPosition(5.37, -4.03);

    $order1 = Order::create([
        'supplier_id' => $supplier->id,
        'client_id' => $client1->id,
        'address_id' => $addr1->id,
        'status' => 'searching_driver',
        'delivery_mode' => 'delivery',
        'subtotal' => 25000,
        'delivery_cost' => 3000,
        'platform_fee' => 500,
        'total_amount' => 28500,
        'pickup_code' => 'RET-101',
        'reception_code' => 'REC-101',
        'vehicle_class' => 'moto',
    ]);

    $order2 = Order::create([
        'supplier_id' => $supplier->id,
        'client_id' => $client2->id,
        'address_id' => $addr2->id,
        'status' => 'searching_driver',
        'delivery_mode' => 'delivery',
        'subtotal' => 40000,
        'delivery_cost' => 3500,
        'platform_fee' => 700,
        'total_amount' => 44200,
        'pickup_code' => 'RET-102',
        'reception_code' => 'REC-102',
        'vehicle_class' => 'moto',
    ]);

    $response = $this->actingAs($driver)->getJson('/api/v1/deliveries/batches');

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.orders_count', 2);
    $response->assertJsonPath('data.0.driver_earnings', 6500);
    $response->assertJsonPath('data.0.total_distance_km', 7.8);
});

test('verified driver can atomically accept a delivery batch', function () {
    $driver = User::factory()->create([
        'role' => 'driver',
        'kyc_status' => 'actif',
        'payment_phone' => '+2250707070707',
        'preferred_payment_provider' => 'wave',
    ]);

    $supplier = User::factory()->create(['role' => 'fournisseur']);
    FournisseurAgree::create([
        'position' => Geo::point(-4.01, 5.35),
        'user_id' => $supplier->id,
        'nom_boutique' => 'Quincaillerie Centrale',
        'statut' => 'agree',
    ]);

    $client = User::factory()->create(['role' => 'client']);

    $order1 = Order::create([
        'supplier_id' => $supplier->id,
        'client_id' => $client->id,
        'status' => 'searching_driver',
        'delivery_mode' => 'delivery',
        'subtotal' => 10000,
        'delivery_cost' => 2000,
        'platform_fee' => 200,
        'total_amount' => 12200,
        'pickup_code' => 'RET-201',
        'reception_code' => 'REC-201',
    ]);

    $order2 = Order::create([
        'supplier_id' => $supplier->id,
        'client_id' => $client->id,
        'status' => 'searching_driver',
        'delivery_mode' => 'delivery',
        'subtotal' => 15000,
        'delivery_cost' => 2500,
        'platform_fee' => 300,
        'total_amount' => 17800,
        'pickup_code' => 'RET-202',
        'reception_code' => 'REC-202',
    ]);

    $response = $this->actingAs($driver)->postJson('/api/v1/deliveries/batch-accept', [
        'order_ids' => [$order1->id, $order2->id],
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.orders_count', 2);

    $fresh1 = $order1->fresh();
    $fresh2 = $order2->fresh();

    expect($fresh1->status)->toBe('driver_assigned');
    expect($fresh2->status)->toBe('driver_assigned');
    expect($fresh1->driver_id)->toBe($driver->id);
    expect($fresh2->driver_id)->toBe($driver->id);
    expect($fresh1->order_group_id)->not->toBeNull();
    expect($fresh1->order_group_id)->toBe($fresh2->order_group_id);
});

test('driver cannot accept a batch if any order is already taken', function () {
    $driver1 = User::factory()->create(['role' => 'driver', 'kyc_status' => 'actif']);
    $driver2 = User::factory()->create(['role' => 'driver', 'kyc_status' => 'actif']);

    $supplier = User::factory()->create(['role' => 'fournisseur']);
    FournisseurAgree::create([
        'position' => Geo::point(-4.01, 5.35),
        'user_id' => $supplier->id,
        'nom_boutique' => 'Quincaillerie Centrale',
        'statut' => 'agree',
    ]);

    $client = User::factory()->create(['role' => 'client']);

    $order1 = Order::create([
        'supplier_id' => $supplier->id,
        'client_id' => $client->id,
        'driver_id' => $driver2->id,
        'status' => 'driver_assigned',
        'delivery_mode' => 'delivery',
        'subtotal' => 10000,
        'delivery_cost' => 2000,
        'platform_fee' => 200,
        'total_amount' => 12200,
        'pickup_code' => 'RET-301',
        'reception_code' => 'REC-301',
    ]);

    $order2 = Order::create([
        'supplier_id' => $supplier->id,
        'client_id' => $client->id,
        'status' => 'searching_driver',
        'delivery_mode' => 'delivery',
        'subtotal' => 15000,
        'delivery_cost' => 2500,
        'platform_fee' => 300,
        'total_amount' => 17800,
        'pickup_code' => 'RET-302',
        'reception_code' => 'REC-302',
    ]);

    $response = $this->actingAs($driver1)->postJson('/api/v1/deliveries/batch-accept', [
        'order_ids' => [$order1->id, $order2->id],
    ]);

    $response->assertStatus(400);
    $response->assertJsonPath('success', false);
});

test('driver can retrieve active multi-drop tour itinerary', function () {
    $driver = User::factory()->create([
        'role' => 'driver',
        'kyc_status' => 'actif',
        'payment_phone' => '+2250707070707',
        'preferred_payment_provider' => 'wave',
    ]);

    $supplier = User::factory()->create(['role' => 'fournisseur']);
    FournisseurAgree::create([
        'position' => Geo::point(-4.01, 5.35),
        'user_id' => $supplier->id,
        'nom_boutique' => 'Quincaillerie Centrale',
        'statut' => 'agree',
    ]);

    $client = User::factory()->create(['role' => 'client']);

    $tourId = 'TOUR-TEST001';

    $order1 = Order::create([
        'supplier_id' => $supplier->id,
        'client_id' => $client->id,
        'driver_id' => $driver->id,
        'status' => 'driver_assigned',
        'delivery_mode' => 'delivery',
        'order_group_id' => $tourId,
        'subtotal' => 10000,
        'delivery_cost' => 2000,
        'platform_fee' => 200,
        'total_amount' => 12200,
        'pickup_code' => 'RET-401',
        'reception_code' => 'REC-401',
        'delivery_latitude' => 5.36,
        'delivery_longitude' => -4.02,
    ]);

    $order2 = Order::create([
        'supplier_id' => $supplier->id,
        'client_id' => $client->id,
        'driver_id' => $driver->id,
        'status' => 'driver_assigned',
        'delivery_mode' => 'delivery',
        'order_group_id' => $tourId,
        'subtotal' => 15000,
        'delivery_cost' => 2500,
        'platform_fee' => 300,
        'total_amount' => 17800,
        'pickup_code' => 'RET-402',
        'reception_code' => 'REC-402',
        'delivery_latitude' => 5.37,
        'delivery_longitude' => -4.03,
    ]);

    $response = $this->actingAs($driver)->getJson('/api/v1/deliveries/active-tour');

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.tour_id', $tourId);
    $response->assertJsonPath('data.total_orders', 2);
    $response->assertJsonPath('data.pending_pickups', 2);
});
