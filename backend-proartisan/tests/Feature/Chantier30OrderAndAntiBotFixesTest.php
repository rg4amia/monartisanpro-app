<?php

use App\Models\Address;
use App\Models\FournisseurAgree;
use App\Models\Notification;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\AntiBotService;
use App\Services\DeliveryPricingService;
use App\Services\GoogleMapsService;
use App\Services\OrderService;
use App\Services\OsrmRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Geo;

/*
 * Chantier 30 — défauts relevés à la relecture d'un rapport d'audit
 * (04/10/2026) : prix de course inventé dans la notification aux livreurs,
 * commande groupée acceptée chez un fournisseur sans boutique, clé de
 * signature de secours du défi anti-robot, anti-rejeu en deux temps.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(GoogleMapsService::class, fn ($mock) => $mock->shouldReceive('getDirections')->andReturn(['distance' => 5000, 'duration' => 600, 'source' => 'mocked']));
    $this->mock(OsrmRoutingService::class, fn ($mock) => $mock->shouldReceive('getRoute')->andReturn([
        'distance_meters' => 4500, 'duration_seconds' => 500, 'distance_km' => 4.5, 'duration_minutes' => 8, 'source' => 'mocked_osrm',
    ]));

    $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $this->address = Address::create([
        'user_id' => $this->client->id,
        'recipient_name' => 'Client Test',
        'recipient_phone' => '+2250101010101',
        'address_line' => 'Cocody Angré',
        'city' => 'Abidjan',
        'is_default' => true,
    ]);
    $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    $this->product = SupplierProduct::create(['supplier_id' => $this->supplier->id, 'sku' => 'CIM-1', 'name' => 'Ciment', 'unit_price' => 5000, 'stock_quantity' => 50, 'is_active' => true]);
});

function chantier30Shop(User $supplier): void
{
    FournisseurAgree::create(['position' => Geo::point(), 'user_id' => $supplier->id, 'nom_boutique' => 'Quincaillerie Nord', 'statut' => 'agree', 'approuve_at' => now()]);
}

function chantier30Order(User $client, User $supplier): Order
{
    return Order::create([
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'delivery_mode' => 'delivery',
        'status' => 'searching_driver',
        'subtotal' => 5000,
        'delivery_cost' => 0,
        'platform_fee' => 150,
        'total_amount' => 5150,
        'pickup_code' => 'LIVREUR-4821',
        'reception_code' => 'RECEPTION-9917',
        'vehicle_class' => 'moto',
    ]);
}

// ─── Notification aux livreurs ─────────────────────────────────────────────

test('la notification aux livreurs annonce la course estimée par le serveur, jamais un montant par défaut', function () {
    chantier30Shop($this->supplier);
    $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
    $this->mock(DeliveryPricingService::class, fn ($mock) => $mock->shouldReceive('calculateOrderDeliveryCost')->andReturn(2300));

    app(OrderService::class)->notifyDriversInArea(chantier30Order($this->client, $this->supplier));

    $notification = Notification::where('user_id', $driver->id)->sole();
    expect($notification->event_key)->toBe('course.disponible.livreur')
        ->and($notification->body)->toContain('2 300')
        ->and($notification->body)->not->toContain('1 500');
});

test('sans estimation possible, la notification n\'annonce aucun montant', function () {
    chantier30Shop($this->supplier);
    $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
    $this->mock(DeliveryPricingService::class, fn ($mock) => $mock->shouldReceive('calculateOrderDeliveryCost')->andThrow(new RuntimeException('Itinéraire indisponible')));

    app(OrderService::class)->notifyDriversInArea(chantier30Order($this->client, $this->supplier));

    $notification = Notification::where('user_id', $driver->id)->sole();
    expect($notification->event_key)->toBe('course.disponible_sans_estimation.livreur')
        ->and($notification->body)->not->toContain('FCFA')
        ->and($notification->body)->toContain('Quincaillerie Nord');
});

test('seuls les livreurs qui peuvent accepter une course sont prévenus', function () {
    chantier30Shop($this->supplier);
    $active = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
    $pending = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'en_attente']);
    $suspended = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif', 'account_status' => 'suspendu']);
    $this->mock(DeliveryPricingService::class, fn ($mock) => $mock->shouldReceive('calculateOrderDeliveryCost')->andReturn(2300));

    app(OrderService::class)->notifyDriversInArea(chantier30Order($this->client, $this->supplier));

    expect(Notification::where('user_id', $active->id)->count())->toBe(1)
        ->and(Notification::whereIn('user_id', [$pending->id, $suspended->id])->count())->toBe(0);
});

// ─── Commande groupée chez un fournisseur sans boutique ────────────────────

test('une commande groupée en livraison est refusée chez un fournisseur sans boutique', function () {
    $this->actingAs($this->client)->postJson('/api/v1/orders/multi-store', [
        'address_id' => $this->address->id,
        'packages' => [[
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'delivery',
            'vehicle_class' => 'moto',
            'items' => [['supplier_product_id' => $this->product->id, 'quantity' => 1]],
        ]],
    ])->assertStatus(400)->assertJsonFragment(['success' => false]);

    expect(Order::count())->toBe(0)
        ->and($this->product->fresh()->stock_quantity)->toBe(50);
});

test('la commande groupée reste acceptée chez un fournisseur qui a sa boutique', function () {
    chantier30Shop($this->supplier);

    $this->actingAs($this->client)->postJson('/api/v1/orders/multi-store', [
        'address_id' => $this->address->id,
        'packages' => [[
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'delivery',
            'vehicle_class' => 'moto',
            'items' => [['supplier_product_id' => $this->product->id, 'quantity' => 1]],
        ]],
    ])->assertCreated();
});

// ─── Défi anti-robot ───────────────────────────────────────────────────────

test('sans clé d\'application, aucun défi anti-robot n\'est émis ni accepté', function () {
    // Jeton forgé avec l'ancienne clé de secours, écrite dans le code.
    $payload = ['action' => 'login', 'a' => 2, 'b' => 3, 'ts' => microtime(true) - 5, 'nonce' => 'nonce-forge'];
    $payload['sig'] = hash_hmac('sha256', sprintf('%s:%s:%s:%s:%s', 'login', 2, 3, $payload['ts'], 'nonce-forge'), 'prosartisan-default-fallback-key-2026');

    config(['app.key' => '']);
    $service = app(AntiBotService::class);

    $result = $service->check(request()->merge(['_bot_token' => base64_encode(json_encode($payload)), '_bot_answer' => '5']), 'login');

    expect($result['success'])->toBeFalse()
        ->and(fn () => $service->generateChallenge('login'))->toThrow(RuntimeException::class);
});

test('l\'anti-rejeu réserve le jeton en une seule opération', function () {
    $service = app(AntiBotService::class);
    $challenge = $service->generateChallenge('login');

    // Une requête concurrente a réservé le jeton entre-temps : `add` échoue.
    Cache::shouldReceive('add')->once()->andReturn(false);
    Cache::shouldReceive('has')->never();

    $result = $service->check(request()->merge(['_bot_token' => $challenge['token'], '_bot_answer' => (string) $challenge['answer']]), 'login');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('déjà');
});
