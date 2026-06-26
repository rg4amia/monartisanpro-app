<?php

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierProduct;
use App\Models\FournisseurAgree;
use App\Models\Transaction;
use App\Enums\WalletType;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mocker GoogleMapsService pour retourner des valeurs fixes
    $this->mock(GoogleMapsService::class, function ($mock) {
        $mock->shouldReceive('getDirections')->andReturn([
            'distance' => 5000, // 5 km
            'duration' => 600,  // 10 minutes
            'source' => 'mocked',
        ]);
    });
});

test('client can create order in pickup mode and pay it', function () {
    $client = User::factory()->create(['role' => 'client', 'phone' => '+2250101010101']);
    $supplier = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250202020202']);
    
    FournisseurAgree::create([
        'user_id' => $supplier->id,
        'nom_boutique' => 'Boutique Test',
        'statut' => 'agree',
    ]);

    $product = SupplierProduct::create([
        'supplier_id' => $supplier->id,
        'sku' => 'PROD-01',
        'name' => 'Ciment de test',
        'unit_price' => 5000,
        'stock_quantity' => 10,
    ]);

    $response = $this->actingAs($client)
        ->postJson('/api/v1/orders', [
            'supplier_id' => $supplier->id,
            'delivery_mode' => 'pickup',
            'items' => [
                [
                    'supplier_product_id' => $product->id,
                    'quantity' => 2,
                ]
            ],
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    
    $this->assertDatabaseHas('orders', [
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'delivery_mode' => 'pickup',
        'status' => 'paid',
        'subtotal' => 10000,
        'delivery_cost' => 0,
        'platform_fee' => 300, // 3% of 10000
        'total_amount' => 10300,
    ]);

    // Vérifier que le stock a été décrémenté
    $product->refresh();
    expect($product->stock_quantity)->toBe(8);

    // Vérifier que la transaction séquestre a été créée
    $this->assertDatabaseHas('transactions', [
        'user_id' => $client->id,
        'montant' => 10300,
        'wallet_dest' => 'escrow_order_1',
    ]);
});

test('client can create order in delivery mode with dynamic maps calculation', function () {
    $client = User::factory()->create(['role' => 'client', 'phone' => '+2250101010101']);
    $client->setPosition(5.35, -4.02);

    $supplier = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250202020202']);
    $agree = FournisseurAgree::create([
        'user_id' => $supplier->id,
        'nom_boutique' => 'Boutique Test',
        'statut' => 'agree',
    ]);
    $agree->setPosition(5.36, -4.01);

    $product = SupplierProduct::create([
        'supplier_id' => $supplier->id,
        'sku' => 'PROD-01',
        'name' => 'Ciment de test',
        'unit_price' => 5000,
        'stock_quantity' => 10,
    ]);

    $response = $this->actingAs($client)
        ->postJson('/api/v1/orders', [
            'supplier_id' => $supplier->id,
            'delivery_mode' => 'delivery',
            'items' => [
                [
                    'supplier_product_id' => $product->id,
                    'quantity' => 2,
                ]
            ],
        ]);

    $response->assertStatus(201);
    
    // Calcul de livraison attendu : (5 km * 150 FCFA) + (10 min * 50 FCFA) = 750 + 500 = 1250 FCFA
    $this->assertDatabaseHas('orders', [
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'delivery_mode' => 'delivery',
        'status' => 'paid',
        'subtotal' => 10000,
        'delivery_cost' => 1250,
        'platform_fee' => 300,
        'total_amount' => 11550, // 10000 + 1250 + 300
    ]);
});

test('full pickup order validation workflow', function () {
    $client = User::factory()->create(['role' => 'client']);
    $supplier = User::factory()->create(['role' => 'fournisseur']);
    FournisseurAgree::create(['user_id' => $supplier->id, 'nom_boutique' => 'Boutique Test', 'statut' => 'agree']);
    $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'sku' => 'P1', 'name' => 'P', 'unit_price' => 1000, 'stock_quantity' => 5]);

    // 1. Passer commande
    $order = app(\App\Services\OrderService::class)->createOrder($client, $supplier, [['supplier_product_id' => $product->id, 'quantity' => 1]], 'pickup');
    
    // 2. Le fournisseur prépare la commande
    $this->actingAs($supplier)
        ->postJson("/api/v1/orders/{$order->id}/prepared")
        ->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('prepared');

    // 3. Retrait en magasin (verify-pickup)
    $response = $this->actingAs($supplier)
        ->postJson("/api/v1/orders/{$order->id}/verify-pickup", [
            'code' => $order->pickup_code,
        ]);

    $response->assertStatus(200);
    
    $order->refresh();
    expect($order->status)->toBe('delivered');

    // 4. Vérifier la libération de la part matériel au fournisseur (1000 - 5% com = 950 FCFA)
    $supplier->refresh();
    $balance = app(\App\Services\WalletService::class)->getBalance($supplier, WalletType::WALLET_MATERIAUX);
    expect($balance)->toBe(950);
});

test('full delivery order validation workflow', function () {
    $client = User::factory()->create(['role' => 'client']);
    $client->setPosition(5.35, -4.02);
    
    $supplier = User::factory()->create(['role' => 'fournisseur']);
    $agree = FournisseurAgree::create(['user_id' => $supplier->id, 'nom_boutique' => 'Boutique Test', 'statut' => 'agree']);
    $agree->setPosition(5.36, -4.01);
    
    $driver = User::factory()->create(['role' => 'driver']);
    
    $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'sku' => 'P1', 'name' => 'P', 'unit_price' => 1000, 'stock_quantity' => 5]);

    // 1. Passer commande
    $order = app(\App\Services\OrderService::class)->createOrder($client, $supplier, [['supplier_product_id' => $product->id, 'quantity' => 1]], 'delivery');
    
    // 2. Le fournisseur prépare la commande -> Passe à searching_driver
    $this->actingAs($supplier)
        ->postJson("/api/v1/orders/{$order->id}/prepared")
        ->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('searching_driver');

    // 3. Le livreur consulte les courses disponibles
    $availableResponse = $this->actingAs($driver)->getJson('/api/v1/deliveries/available');
    $availableResponse->assertStatus(200);
    $availableResponse->assertJsonCount(1, 'data');

    // 4. Le livreur accepte la course -> Passe à driver_assigned
    $this->actingAs($driver)
        ->postJson("/api/v1/deliveries/{$order->id}/accept")
        ->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('driver_assigned');
    expect($order->driver_id)->toBe($driver->id);

    // 5. Retrait chez le fournisseur -> Libère la part matériel au fournisseur
    $this->actingAs($driver)
        ->postJson("/api/v1/orders/{$order->id}/verify-pickup", [
            'code' => $order->pickup_code,
        ])
        ->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('driver_picked_up');

    $supplier->refresh();
    $supplierBalance = app(\App\Services\WalletService::class)->getBalance($supplier, WalletType::WALLET_MATERIAUX);
    expect($supplierBalance)->toBe(950); // 1000 - 5% com = 950 FCFA

    // Le livreur n'est pas encore payé
    $driver->refresh();
    $driverBalance = app(\App\Services\WalletService::class)->getBalance($driver, WalletType::WALLET_MO);
    expect($driverBalance)->toBe(0);

    // 6. Livraison chez le client -> Libère la part livraison au livreur
    $this->actingAs($driver)
        ->postJson("/api/v1/orders/{$order->id}/verify-delivery", [
            'code' => $order->reception_code,
        ])
        ->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('delivered');

    // Le livreur reçoit son gain de livraison (1250 FCFA)
    $driver->refresh();
    $driverBalance = app(\App\Services\WalletService::class)->getBalance($driver, WalletType::WALLET_MO);
    expect($driverBalance)->toBe(1250);
});
