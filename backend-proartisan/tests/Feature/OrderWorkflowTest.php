<?php

use App\Enums\WalletType;
use App\Models\Address;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GoogleMapsService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo;
use Tests\Support\PaysOrders;

uses(RefreshDatabase::class, PaysOrders::class);

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
    $client = User::factory()->create(['role' => 'client', 'phone' => '+2250101010101', 'kyc_status' => 'actif']);
    $supplier = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250202020202']);

    FournisseurAgree::create([
        'position' => Geo::point(),
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
                ],
            ],
            'payment_provider' => 'wave',
            'payment_phone' => '+2250101010101',
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    expect($response->json('payment.payment_url'))->not->toBeEmpty();

    // Encaissement réel (Chantier 11) : la commande attend le paiement, stock réservé.
    $this->assertDatabaseHas('orders', [
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'delivery_mode' => 'pickup',
        'status' => 'pending',
        'subtotal' => 10000,
        'delivery_cost' => 0,
        'platform_fee' => 300, // 3% of 10000
        'total_amount' => 10300,
    ]);

    $product->refresh();
    expect($product->stock_quantity)->toBe(8);

    // (id réel de la commande : MariaDB ne réinitialise pas l'AUTO_INCREMENT au rollback)
    $orderId = Order::where('client_id', $client->id)->value('id');
    $payment = Transaction::where('user_id', $client->id)->where('wallet_dest', "escrow_order_{$orderId}")->sole();
    expect((int) $payment->montant)->toBe(10300)
        ->and($payment->statut->value)->toBe('en_attente');

    // Confirmation de l'opérateur : la commande est encaissée.
    app(PaymentService::class)->confirmSimulatedPayment($payment);

    $order = Order::findOrFail($orderId);
    expect($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull()
        ->and($payment->fresh()->statut->value)->toBe('confirme');
});

test('client can create order in delivery mode with dynamic maps calculation', function () {
    $client = User::factory()->create(['role' => 'client', 'phone' => '+2250101010101', 'kyc_status' => 'actif']);
    $client->setPosition(5.35, -4.02);

    $address = Address::create([
        'user_id' => $client->id,
        'recipient_name' => $client->name,
        'recipient_phone' => '+2250101010101',
        'address_line' => 'Cocody Angré 8e Tranche',
        'city' => 'Abidjan',
        'is_default' => true,
    ]);

    $supplier = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250202020202']);
    $agree = FournisseurAgree::create([
        'position' => Geo::point(),
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
            'address_id' => $address->id,
            'items' => [
                [
                    'supplier_product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

    $response->assertStatus(201);

    // Le frais de livraison est initialement à 0 (course révélée et payée à la livraison)
    $this->assertDatabaseHas('orders', [
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'delivery_mode' => 'delivery',
        'status' => 'pending',
        'subtotal' => 10000,
        'delivery_cost' => 0,
        'platform_fee' => 300,
        'total_amount' => 10300,
    ]);
});

test('full pickup order validation workflow', function () {
    $admin = User::factory()->create(['role' => 'admin', 'phone' => '+2250000000000']);
    $client = User::factory()->create(['role' => 'client']);
    $supplier = User::factory()->create(['role' => 'fournisseur']);
    FournisseurAgree::create(['position' => Geo::point(), 'user_id' => $supplier->id, 'nom_boutique' => 'Boutique Test', 'statut' => 'agree']);
    $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'sku' => 'P1', 'name' => 'P', 'unit_price' => 1000, 'stock_quantity' => 5]);

    // 1. Passer commande
    $order = app(OrderService::class)->createOrder($client, $supplier, [['supplier_product_id' => $product->id, 'quantity' => 1]], 'pickup');
    $this->payOrder($order);

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
    $balance = app(WalletService::class)->getBalance($supplier, WalletType::WALLET_MATERIAUX);
    expect($balance)->toBe(950);

    // Vérifier que la commission plateforme a été créditée sur le compte admin (3% client + 5% fournisseur = 30 + 50 = 80 FCFA)
    $admin->refresh();
    $adminBalance = app(WalletService::class)->getBalance($admin, WalletType::WALLET_MO);
    expect($adminBalance)->toBe(80);
});

test('full delivery order validation workflow', function () {
    $admin = User::factory()->create(['role' => 'admin', 'phone' => '+2250000000000']);
    $client = User::factory()->create(['role' => 'client']);
    $client->setPosition(5.35, -4.02);

    $supplier = User::factory()->create(['role' => 'fournisseur']);
    $agree = FournisseurAgree::create(['position' => Geo::point(), 'user_id' => $supplier->id, 'nom_boutique' => 'Boutique Test', 'statut' => 'agree']);
    $agree->setPosition(5.36, -4.01);

    $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

    $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'sku' => 'P1', 'name' => 'P', 'unit_price' => 1000, 'stock_quantity' => 5]);

    // 1. Passer commande
    $order = app(OrderService::class)->createOrder($client, $supplier, [['supplier_product_id' => $product->id, 'quantity' => 1]], 'delivery');
    $this->payOrder($order);

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
    $supplierBalance = app(WalletService::class)->getBalance($supplier, WalletType::WALLET_MATERIAUX);
    expect($supplierBalance)->toBe(950); // 1000 - 5% com = 950 FCFA

    // Le livreur n'est pas encore payé
    $driver->refresh();
    $driverBalance = app(WalletService::class)->getBalance($driver, WalletType::WALLET_MO);
    expect($driverBalance)->toBe(0);

    // 6. Livraison chez le client -> Libère la part livraison au livreur
    $this->actingAs($driver)
        ->postJson("/api/v1/orders/{$order->id}/verify-delivery", [
            'code' => $order->reception_code,
        ])
        ->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('delivered');

    // Modèle « à la Yango » : le montant de la course est révélé à la
    // livraison et le livreur n'est crédité qu'au paiement du client.
    expect($order->delivery_fare_status)->toBe('a_payer');
    expect(app(WalletService::class)->getBalance($driver->fresh(), WalletType::WALLET_MO))->toBe(0);

    $this->actingAs($client)
        ->postJson("/api/v1/payments/orders/{$order->id}/delivery-fare", ['provider' => 'wave', 'phone' => '+2250700000009'])
        ->assertStatus(200);
    app(PaymentService::class)->confirmSimulatedPayment(
        Transaction::where('type', 'paiement_livraison')->latest('id')->firstOrFail()
    );

    // Le livreur reçoit son gain de livraison (1250 FCFA - 10% com = 1125 FCFA)
    $driver->refresh();
    $driverBalance = app(WalletService::class)->getBalance($driver, WalletType::WALLET_MO);
    expect($driverBalance)->toBe(1125);

    // Vérifier que la commission plateforme totale a été créditée sur le compte admin (80 FCFA part matériaux + 125 FCFA part livraison = 205 FCFA)
    $admin->refresh();
    $adminBalance = app(WalletService::class)->getBalance($admin, WalletType::WALLET_MO);
    expect($adminBalance)->toBe(205);
});
