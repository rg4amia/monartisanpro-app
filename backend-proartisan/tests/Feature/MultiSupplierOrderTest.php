<?php

use App\Models\Address;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GoogleMapsService;
use App\Services\OsrmRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(GoogleMapsService::class, function ($mock) {
        $mock->shouldReceive('getDirections')->andReturn([
            'distance' => 5000,
            'duration' => 600,
            'source' => 'mocked',
        ]);
    });
    $this->mock(OsrmRoutingService::class, function ($mock) {
        $mock->shouldReceive('getRoute')->andReturn([
            'distance_meters' => 4500,
            'duration_seconds' => 500,
            'distance_km' => 4.5,
            'duration_minutes' => 8,
            'source' => 'mocked_osrm',
        ]);
    });
});

test('client can estimate multi-supplier delivery', function () {
    $client = User::factory()->create(['role' => 'client', 'phone' => '+2250101010101']);
    $supplier1 = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250202020202']);
    $supplier2 = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250303030303']);

    FournisseurAgree::create([
        'position' => Geo::point(),
        'user_id' => $supplier1->id,
        'nom_boutique' => 'Quincaillerie Nord',
        'statut' => 'agree',
    ]);
    FournisseurAgree::create([
        'position' => Geo::point(),
        'user_id' => $supplier2->id,
        'nom_boutique' => 'Quincaillerie Sud',
        'statut' => 'agree',
    ]);

    $response = $this->actingAs($client)->postJson('/api/v1/orders/multi-estimate', [
        'client_latitude' => 5.3500,
        'client_longitude' => -4.0100,
        'packages' => [
            [
                'supplier_id' => $supplier1->id,
                'delivery_mode' => 'delivery',
                'vehicle_class' => 'moto',
            ],
            [
                'supplier_id' => $supplier2->id,
                'delivery_mode' => 'pickup',
            ],
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonCount(2, 'data.packages');
});

test('client can checkout split-cart with multiple suppliers in single transaction', function () {
    $client = User::factory()->create(['role' => 'client', 'phone' => '+2250101010101', 'kyc_status' => 'actif']);
    $address = Address::create([
        'user_id' => $client->id,
        'recipient_name' => $client->name,
        'recipient_phone' => '+2250101010101',
        'address_line' => 'Cocody Angré 8e Tranche',
        'city' => 'Abidjan',
        'is_default' => true,
    ]);
    $supplier1 = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250202020202']);
    $supplier2 = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250303030303']);

    FournisseurAgree::create([
        'position' => Geo::point(),
        'user_id' => $supplier1->id,
        'nom_boutique' => 'Quincaillerie Nord',
        'statut' => 'agree',
    ]);
    FournisseurAgree::create([
        'position' => Geo::point(),
        'user_id' => $supplier2->id,
        'nom_boutique' => 'Quincaillerie Sud',
        'statut' => 'agree',
    ]);

    $prod1 = SupplierProduct::create([
        'supplier_id' => $supplier1->id,
        'sku' => 'PROD-N1',
        'name' => 'Ciment Nord',
        'unit_price' => 5000,
        'stock_quantity' => 20,
    ]);

    $prod2 = SupplierProduct::create([
        'supplier_id' => $supplier2->id,
        'sku' => 'PROD-S1',
        'name' => 'Peinture Sud',
        'unit_price' => 12000,
        'stock_quantity' => 15,
    ]);

    $response = $this->actingAs($client)->postJson('/api/v1/orders/multi-store', [
        'address_id' => $address->id,
        'packages' => [
            [
                'supplier_id' => $supplier1->id,
                'delivery_mode' => 'delivery',
                'vehicle_class' => 'moto',
                'items' => [
                    [
                        'supplier_product_id' => $prod1->id,
                        'quantity' => 2,
                    ],
                ],
            ],
            [
                'supplier_id' => $supplier2->id,
                'delivery_mode' => 'pickup',
                'items' => [
                    [
                        'supplier_product_id' => $prod2->id,
                        'quantity' => 1,
                    ],
                ],
            ],
        ],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    $orderGroupId = $response->json('data.order_group_id');
    $this->assertNotEmpty($orderGroupId);
    $this->assertStringStartsWith('GRP-', $orderGroupId);
    $this->assertEquals(2, $response->json('data.packages_count'));

    // Verify sub-orders in DB
    $orders = Order::where('order_group_id', $orderGroupId)->get();
    $this->assertCount(2, $orders);

    // Verify single aggregated escrow transaction
    $this->assertDatabaseHas('transactions', [
        'user_id' => $client->id,
        'type' => 'acompte',
        'wallet_dest' => 'escrow_group_'.$orderGroupId,
        'statut' => 'confirme',
    ]);

    // Verify stock decrement
    $this->assertEquals(18, $prod1->fresh()->stock_quantity);
    $this->assertEquals(14, $prod2->fresh()->stock_quantity);
});
