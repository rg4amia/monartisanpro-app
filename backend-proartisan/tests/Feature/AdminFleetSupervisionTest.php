<?php

use App\Models\Address;
use App\Models\DeliveryTracking;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo;

uses(RefreshDatabase::class);

test('non-admin user cannot access fleet map supervision', function () {
    $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

    $this->actingAs($client)->getJson('/api/v1/deliveries/fleet-map')
        ->assertStatus(403);

    $this->actingAs($driver)->getJson('/api/v1/deliveries/fleet-map')
        ->assertStatus(403);
});

test('admin can view fleet map supervision with summary and driver statuses', function () {
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

    // 1. Chauffeur disponible à Cocody (position récente, sans commande)
    $driverAvailable = User::factory()->create([
        'role' => 'livreur',
        'name' => 'Kouamé Livreur',
        'phone' => '+2250701010101',
        'kyc_status' => 'actif',
    ]);
    $driverAvailable->setPosition(5.3544, -3.9856);

    // 2. Chauffeur en transit avec commande à Yopougon
    $driverDelivering = User::factory()->create([
        'role' => 'livreur',
        'name' => 'Sékou Livreur',
        'phone' => '+2250702020202',
        'kyc_status' => 'actif',
    ]);
    $driverDelivering->setPosition(5.3400, -4.0800);

    $supplier = User::factory()->create(['role' => 'fournisseur']);
    $fa = FournisseurAgree::create([
        'position' => Geo::point(-4.0197, 5.3261),
        'user_id' => $supplier->id,
        'nom_boutique' => 'Quincaillerie Plateau',
        'statut' => 'agree',
    ]);

    $client = User::factory()->create(['role' => 'client', 'name' => 'Aïcha Diallo']);
    $clientAddr = Address::create([
        'user_id' => $client->id,
        'recipient_name' => 'Aïcha Diallo',
        'recipient_phone' => '+2250505050505',
        'address_line' => 'Yopougon Maroc',
        'city' => 'Abidjan',
    ]);
    $clientAddr->setPosition(5.3450, -4.0850);

    $order = Order::create([
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'driver_id' => $driverDelivering->id,
        'status' => 'driver_picked_up',
        'subtotal' => 25000,
        'delivery_cost' => 2000,
        'platform_fee' => 0,
        'total_amount' => 27000,
        'pickup_code' => '4821',
        'reception_code' => '7734',
        'delivery_mode' => 'delivery',
        'driver_picked_up_at' => now()->subMinutes(10),
    ]);

    DeliveryTracking::create([
        'order_id' => $order->id,
        'driver_id' => $driverDelivering->id,
        'latitude' => 5.3400,
        'longitude' => -4.0800,
        'speed_kmh' => 28.5,
        'heading' => 180.0,
        'battery_level' => 65,
        'created_at' => now()->subMinutes(2),
    ]);

    // 3. Chauffeur bloqué / en retard (> 25 min sans ping en cours de livraison)
    $driverStalled = User::factory()->create([
        'role' => 'livreur',
        'name' => 'Moussa Bloqué',
        'phone' => '+2250703030303',
        'kyc_status' => 'actif',
    ]);
    $driverStalled->setPosition(5.3000, -3.9833); // Marcory

    $orderStalled = Order::create([
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'driver_id' => $driverStalled->id,
        'status' => 'driver_picked_up',
        'subtotal' => 15000,
        'delivery_cost' => 1500,
        'platform_fee' => 0,
        'total_amount' => 16500,
        'pickup_code' => '1234',
        'reception_code' => '5678',
        'delivery_mode' => 'delivery',
        'driver_picked_up_at' => now()->subMinutes(40),
    ]);

    DeliveryTracking::create([
        'order_id' => $orderStalled->id,
        'driver_id' => $driverStalled->id,
        'latitude' => 5.3000,
        'longitude' => -3.9833,
        'speed_kmh' => 0.0,
        'battery_level' => 15,
        'created_at' => now()->subMinutes(30), // > 25 min -> stalled
    ]);

    $response = $this->actingAs($admin)->getJson('/api/v1/deliveries/fleet-map');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'total_drivers',
                    'online_drivers',
                    'in_transit',
                    'available',
                    'stalled_alerts',
                    'active_orders_count',
                    'updated_at',
                ],
                'drivers' => [
                    '*' => [
                        'id',
                        'name',
                        'phone',
                        'status',
                        'is_online',
                        'is_stalled',
                        'position',
                        'speed_kmh',
                        'battery_level',
                        'estimated_commune',
                        'active_order',
                    ],
                ],
            ],
        ]);

    $summary = $response->json('data.summary');
    expect($summary['total_drivers'])->toBe(3);
    expect($summary['in_transit'])->toBe(2);
    expect($summary['available'])->toBe(1);
    expect($summary['stalled_alerts'])->toBe(1);

    $drivers = collect($response->json('data.drivers'));
    $stalled = $drivers->firstWhere('id', $driverStalled->id);
    expect($stalled['is_stalled'])->toBeTrue();
    expect($stalled['stalled_reason'])->toContain('> 25 min');

    $delivering = $drivers->firstWhere('id', $driverDelivering->id);
    expect($delivering['is_stalled'])->toBeFalse();
    expect($delivering['status'])->toBe('delivering');
    expect($delivering['speed_kmh'])->toBe(28.5);
    expect($delivering['active_order']['id'])->toBe($order->id);
});

test('fleet map can filter drivers by commune', function () {
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

    $driverCocody = User::factory()->create([
        'role' => 'livreur',
        'name' => 'Livreur Cocody',
        'kyc_status' => 'actif',
    ]);
    $driverCocody->setPosition(5.3544, -3.9856);

    $driverYopougon = User::factory()->create([
        'role' => 'livreur',
        'name' => 'Livreur Yopougon',
        'kyc_status' => 'actif',
    ]);
    $driverYopougon->setPosition(5.3400, -4.0800);

    $response = $this->actingAs($admin)->getJson('/api/v1/deliveries/fleet-map?commune=Cocody');

    $response->assertOk();
    $drivers = collect($response->json('data.drivers'));
    expect($drivers)->toHaveCount(1);
    expect($drivers->first()['id'])->toBe($driverCocody->id);
});

test('admin missions panel data includes fleet overview', function () {
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    $panelData = app(\App\Services\Admin\AdminPanelData::class);

    $request = \Illuminate\Http\Request::create('/admin/missions', 'GET');
    $data = $panelData->missions($request);

    expect($data)->toHaveKey('fleetOverview');
    expect($data['fleetOverview'])->toHaveKey('summary');
    expect($data['fleetOverview'])->toHaveKey('drivers');
});

test('web route admin.deliveries.fleet-map returns fleet data for authenticated admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

    $response = $this->actingAs($admin)->getJson('/admin/deliveries/fleet-map');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'summary',
                'drivers',
            ],
        ]);
});

