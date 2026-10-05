<?php

use App\Models\Address;
use App\Models\AdminActivityLog;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\DeliveryPricingService;
use App\Services\OrderService;
use App\Services\OsrmRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Geo;
use Tests\Support\Routing;

/*
 * Chantier 34 — suites des Chantiers 31 à 33 :
 *  - une livraison se commande à une adresse qui porte une position ;
 *  - une estimation de course à vol d'oiseau se déclare comme telle ;
 *  - un administrateur lève la suspension d'un code de commande.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $this->client->setPosition(5.3200, -4.0200);
    $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    FournisseurAgree::create(['position' => Geo::point(5.3484, -4.0267), 'user_id' => $this->supplier->id, 'nom_boutique' => 'Quincaillerie Centrale', 'statut' => 'agree', 'approuve_at' => now()]);
    $this->product = SupplierProduct::create(['supplier_id' => $this->supplier->id, 'sku' => 'VIS-1', 'name' => 'Vis', 'unit_price' => 5000, 'stock_quantity' => 50, 'is_active' => true]);
    $this->address = Address::create([
        'user_id' => $this->client->id,
        'recipient_name' => 'Chef de chantier',
        'recipient_phone' => '+2250101010101',
        'address_line' => 'Chantier Bingerville',
        'city' => 'Bingerville',
        'is_default' => true,
    ]);
});

function c34Payload($test, string $mode = 'delivery'): array
{
    return [
        'supplier_id' => $test->supplier->id,
        'delivery_mode' => $mode,
        'address_id' => $test->address->id,
        'items' => [['supplier_product_id' => $test->product->id, 'quantity' => 1]],
    ];
}

// ─── Adresse sans position ──────────────────────────────────────────────────

test('une livraison à une adresse sans position est refusée, avec la marche à suivre', function () {
    $response = $this->actingAs($this->client, 'sanctum')->postJson('/api/v1/orders', c34Payload($this));

    expect($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500)
        ->and($response->json('message'))->toContain('pas de position sur la carte')
        ->and(Order::count())->toBe(0)
        ->and($this->product->fresh()->stock_quantity)->toBe(50);
});

test('la commande groupée refuse aussi une adresse sans position', function () {
    expect(fn () => app(OrderService::class)->createMultiSupplierOrders(
        $this->client,
        [['supplier_id' => $this->supplier->id, 'delivery_mode' => 'delivery', 'items' => [['supplier_product_id' => $this->product->id, 'quantity' => 1]]]],
        null,
        $this->address,
    ))->toThrow(Exception::class, 'pas de position sur la carte');

    expect(Order::count())->toBe(0);
});

test('un retrait en boutique ne demande aucune position', function () {
    $order = app(OrderService::class)->createOrder(
        $this->client,
        $this->supplier,
        [['supplier_product_id' => $this->product->id, 'quantity' => 1]],
        'pickup',
        'moto',
        1.0,
        null,
        $this->address,
    );

    expect($order->delivery_mode)->toBe('pickup')
        ->and($order->delivery_latitude)->toBeNull();
});

test('une fois la position indiquée, la livraison est acceptée et figée sur la commande', function () {
    $this->address->setPosition(5.3550, -3.8850);

    $order = app(OrderService::class)->createOrder(
        $this->client,
        $this->supplier,
        [['supplier_product_id' => $this->product->id, 'quantity' => 1]],
        'delivery',
        'moto',
        1.0,
        null,
        $this->address->fresh(),
    );

    expect($order->fresh()->deliveryDestination())->toBe(['lat' => 5.355, 'lng' => -3.885]);
});

// ─── Estimation à vol d'oiseau ──────────────────────────────────────────────

test('sans Yandex ni OSRM, l\'estimation se déclare approximative et suit la route, pas la ligne droite', function () {
    Http::fake(['*router.project-osrm.org*' => Http::response(null, 503)]);
    $from = ['lat' => 5.3484, 'lng' => -4.0267];
    $to = ['lat' => 5.3550, 'lng' => -3.8850];

    $estimate = app(DeliveryPricingService::class)->estimateFare(['from' => $from, 'to' => $to, 'surge_multiplier' => 1.0]);

    $straightKm = app(OsrmRoutingService::class)->haversineDistanceKm($from['lat'], $from['lng'], $to['lat'], $to['lng']);
    $straightFare = app(DeliveryPricingService::class)->fareFromTrip($straightKm, $straightKm / 40 * 60, 'moto');

    expect($estimate['is_fallback'])->toBeTrue()
        ->and($estimate['distance_source'])->toBe(DeliveryPricingService::SOURCE_ROAD_ESTIMATE)
        // Sinuosité urbaine : la distance retenue dépasse le vol d'oiseau de 30 %.
        ->and($estimate['distance_km'])->toEqualWithDelta($straightKm * 1.3, 0.05)
        // La ligne droite à 40 km/h sous-évaluait la course d'un tiers environ.
        ->and($estimate['delivery_cost'])->toBeGreaterThan((int) round($straightFare * 1.3));
});

test('avec un itinéraire OSRM, l\'estimation ne se déclare pas approximative', function () {
    Routing::fakeOsrm(8.0, 12.0);

    $estimate = app(DeliveryPricingService::class)->estimateFare([
        'from' => ['lat' => 5.3484, 'lng' => -4.0267],
        'to' => ['lat' => 5.3550, 'lng' => -3.8850],
        'surge_multiplier' => 1.0,
    ]);

    // (8 × 150) + (12 × 50) = 1 800 FCFA, classe moto.
    expect($estimate['is_fallback'])->toBeFalse()
        ->and($estimate['distance_source'])->toBe('osrm')
        ->and($estimate['delivery_cost'])->toBe(1800);
});

// ─── Levée d'une suspension par un administrateur ───────────────────────────

function c34SuspendedOrder($test): Order
{
    $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
    $order = Order::create([
        'client_id' => $test->client->id,
        'supplier_id' => $test->supplier->id,
        'driver_id' => $driver->id,
        'status' => 'driver_picked_up',
        'delivery_mode' => 'delivery',
        'subtotal' => 40000,
        'delivery_cost' => 5000,
        'platform_fee' => 1000,
        'total_amount' => 46000,
        'pickup_code' => 'LIVREUR-4821',
        'reception_code' => 'RECEPTION-7390',
        'vehicle_class' => 'moto',
    ]);

    for ($i = 0; $i < 5; $i++) {
        try {
            app(OrderService::class)->verifyPickup($order->fresh(), '0000');
        } catch (Exception) {
            // Codes faux attendus.
        }
    }

    return $order->fresh();
}

test('la commande annonce la suspension en cours, sans le code ni le nombre d\'essais', function () {
    $order = c34SuspendedOrder($this);

    $json = $order->toArray();
    expect($json['code_suspensions']['pickup'])->not->toBeNull()
        ->and($json['code_suspensions']['reception'])->toBeNull()
        ->and($json)->not->toHaveKeys(['pickup_code', 'pickup_code_attempts', 'pickup_code_locked_until']);

    $this->travel(6)->minutes();
    expect($order->fresh()->toArray()['code_suspensions']['pickup'])->toBeNull();
});

test('un administrateur lève la suspension : le bon code repasse, l\'action est auditée', function () {
    $order = c34SuspendedOrder($this);
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

    $this->actingAs($admin)
        ->from('/admin/missions')
        ->post("/admin/orders/{$order->id}/codes/unlock", ['code' => 'pickup', 'reason' => 'Fournisseur joint par téléphone'])
        ->assertRedirect('/admin/missions')
        ->assertSessionHas('success');

    $order = $order->fresh();
    expect($order->pickup_code_attempts)->toBe(0)
        ->and($order->pickup_code_locked_until)->toBeNull();

    app(OrderService::class)->verifyPickup($order, '4821');

    $log = AdminActivityLog::where('action', 'order.code_suspension.lifted')->sole();
    expect($log->admin_id)->toBe($admin->id)
        ->and(json_encode($log->context, JSON_UNESCAPED_UNICODE))->toContain('Fournisseur joint par téléphone')
        ->and(json_encode($log->context))->not->toContain('4821');
});

test('la levée exige un motif et un code connu', function () {
    $order = c34SuspendedOrder($this);
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

    $this->actingAs($admin)->post("/admin/orders/{$order->id}/codes/unlock", ['code' => 'pickup', 'reason' => 'ok'])
        ->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post("/admin/orders/{$order->id}/codes/unlock", ['code' => 'autre', 'reason' => 'Motif valable'])
        ->assertSessionHasErrors('code');

    expect($order->fresh()->pickup_code_locked_until)->not->toBeNull();
});

test('lever une suspension qui n\'existe pas est refusé et n\'est pas audité', function () {
    $order = c34SuspendedOrder($this);
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

    $this->actingAs($admin)
        ->post("/admin/orders/{$order->id}/codes/unlock", ['code' => 'reception', 'reason' => 'Motif valable'])
        ->assertSessionHas('error');

    expect(AdminActivityLog::where('action', 'order.code_suspension.lifted')->count())->toBe(0);
});

test('sans la capacité de gérer les missions, la levée est refusée', function () {
    $order = c34SuspendedOrder($this);
    $root = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    $lecteur = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    app(AdminPermissionService::class)->sync($lecteur, ['admin.missions.view'], $root);

    $this->actingAs($lecteur)
        ->post("/admin/orders/{$order->id}/codes/unlock", ['code' => 'pickup', 'reason' => 'Motif valable'])
        ->assertForbidden();

    expect($order->fresh()->pickup_code_locked_until)->not->toBeNull();
});
