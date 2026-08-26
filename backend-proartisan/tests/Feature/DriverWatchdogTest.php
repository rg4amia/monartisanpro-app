<?php

use App\Models\User;
use App\Models\Order;
use App\Models\Setting;
use App\Services\GoogleMapsService;
use App\Services\OrderService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->mock(GoogleMapsService::class, function ($mock) {
        $mock->shouldReceive('getDirections')->andReturn([
            'distance' => 5000,
            'duration' => 600,
            'source'   => 'mocked',
        ]);
    });
});

/**
 * Helper : crée une commande en statut `driver_assigned` avec un livreur assigné.
 */
function createAssignedOrder(int $minutesAgo, int $reassignmentCount = 0): Order
{
    $client = User::factory()->create(['role' => 'client', 'phone' => '+225010' . rand(1000000, 9999999)]);
    $supplier = User::factory()->create(['role' => 'fournisseur', 'phone' => '+225020' . rand(1000000, 9999999)]);
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+225030' . rand(1000000, 9999999)]);

    \App\Models\FournisseurAgree::create([
        'user_id'      => $supplier->id,
        'nom_boutique' => 'Quincaillerie Test',
        'statut'       => 'agree',
    ]);

    return Order::create([
        'client_id'                 => $client->id,
        'supplier_id'               => $supplier->id,
        'driver_id'                 => $driver->id,
        'delivery_mode'             => 'delivery',
        'status'                    => 'driver_assigned',
        'subtotal'                  => 15000,
        'delivery_cost'             => 2500,
        'platform_fee'              => 450,
        'total_amount'              => 17950,
        'pickup_code'               => 'LIVREUR-1234',
        'reception_code'            => 'RECEPTION-5678',
        'driver_assigned_at'        => Carbon::now()->subMinutes($minutesAgo),
        'driver_reassignment_count' => $reassignmentCount,
    ]);
}

// ─── Test 1 : Commande > 15 min est réaffectée ───────────────────────────────

test('watchdog reassigns order when driver is stale for more than 15 minutes', function () {
    $order = createAssignedOrder(minutesAgo: 20);
    $originalDriverId = $order->driver_id;

    $this->artisan('prosartisan:driver-watchdog')
        ->assertExitCode(0);

    $order->refresh();

    expect($order->status)->toBe('searching_driver');
    expect($order->driver_id)->toBeNull();
    expect($order->driver_assigned_at)->toBeNull();
    expect($order->driver_reassignment_count)->toBe(1);

    // Le livreur retiré doit avoir reçu une notification
    $this->assertDatabaseHas('notifications', [
        'user_id' => $originalDriverId,
        'title'   => 'Course retirée',
    ]);
});

// ─── Test 2 : Commande < 15 min n'est PAS réaffectée ─────────────────────────

test('watchdog does NOT reassign order when driver is within timeout', function () {
    $order = createAssignedOrder(minutesAgo: 5);
    $originalDriverId = $order->driver_id;

    $this->artisan('prosartisan:driver-watchdog')
        ->assertExitCode(0);

    $order->refresh();

    expect($order->status)->toBe('driver_assigned');
    expect($order->driver_id)->toBe($originalDriverId);
    expect($order->driver_reassignment_count)->toBe(0);
});

// ─── Test 3 : Commande driver_picked_up n'est PAS touchée ────────────────────

test('watchdog ignores orders where driver already picked up', function () {
    $order = createAssignedOrder(minutesAgo: 30);
    $order->update(['status' => 'driver_picked_up']);

    $this->artisan('prosartisan:driver-watchdog')
        ->assertExitCode(0);

    $order->refresh();
    expect($order->status)->toBe('driver_picked_up');
});

// ─── Test 4 : Le compteur de réaffectation s'incrémente ──────────────────────

test('reassignment counter increments on each watchdog cycle', function () {
    $order = createAssignedOrder(minutesAgo: 20, reassignmentCount: 1);

    $this->artisan('prosartisan:driver-watchdog')
        ->assertExitCode(0);

    $order->refresh();
    expect($order->driver_reassignment_count)->toBe(2);
    expect($order->status)->toBe('searching_driver');
});

// ─── Test 5 : Max réaffectations → escalade admin, pas de boucle ─────────────

test('watchdog escalates to admin when max reassignments reached', function () {
    $order = createAssignedOrder(minutesAgo: 20, reassignmentCount: 3);
    $admin = User::factory()->create(['role' => 'admin', 'phone' => '+225099' . rand(1000000, 9999999)]);

    $this->artisan('prosartisan:driver-watchdog')
        ->assertExitCode(0);

    $order->refresh();

    // La commande reste en driver_assigned (pas de réaffectation)
    expect($order->status)->toBe('driver_assigned');
    expect($order->driver_reassignment_count)->toBe(3);

    // L'admin reçoit une alerte d'escalade
    $this->assertDatabaseHas('notifications', [
        'user_id' => $admin->id,
        'title'   => 'Commande sans livreur — escalade requise',
    ]);
});

// ─── Test 6 : Le client reçoit une notification de changement ────────────────

test('client receives notification when driver is reassigned', function () {
    $order = createAssignedOrder(minutesAgo: 20);
    $clientId = $order->client_id;

    $this->artisan('prosartisan:driver-watchdog')
        ->assertExitCode(0);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $clientId,
        'title'   => 'Changement de livreur',
    ]);
});

// ─── Test 7 : Dry-run n'effectue aucune modification ─────────────────────────

test('dry-run mode does not modify any order', function () {
    $order = createAssignedOrder(minutesAgo: 20);
    $originalDriverId = $order->driver_id;

    $this->artisan('prosartisan:driver-watchdog', ['--dry-run' => true])
        ->assertExitCode(0);

    $order->refresh();

    expect($order->status)->toBe('driver_assigned');
    expect($order->driver_id)->toBe($originalDriverId);
    expect($order->driver_reassignment_count)->toBe(0);
});

// ─── Test 8 : Le timeout est configurable via settings ───────────────────────

test('watchdog respects custom timeout from settings', function () {
    // Configurer un timeout de 30 minutes au lieu de 15
    Setting::updateOrCreate(
        ['key' => 'driver_watchdog_timeout_minutes'],
        ['value' => '30', 'type' => 'integer', 'group' => 'logistique', 'label' => 'Watchdog timeout']
    );

    // Commande assignée il y a 20 min → ne devrait PAS être réaffectée avec timeout de 30min
    $order = createAssignedOrder(minutesAgo: 20);

    $this->artisan('prosartisan:driver-watchdog')
        ->assertExitCode(0);

    $order->refresh();
    expect($order->status)->toBe('driver_assigned');
});

// ─── Test 9 : assignDriver timestamp correctement ────────────────────────────

test('assignDriver sets driver_assigned_at timestamp', function () {
    $client = User::factory()->create(['role' => 'client', 'phone' => '+2250701000001']);
    $supplier = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250702000002']);
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250703000003']);

    \App\Models\FournisseurAgree::create([
        'user_id'      => $supplier->id,
        'nom_boutique' => 'Quincaillerie Assignation',
        'statut'       => 'agree',
    ]);

    $order = Order::create([
        'client_id'      => $client->id,
        'supplier_id'    => $supplier->id,
        'delivery_mode'  => 'delivery',
        'status'         => 'searching_driver',
        'subtotal'       => 10000,
        'delivery_cost'  => 0,
        'platform_fee'   => 300,
        'total_amount'   => 10300,
        'pickup_code'    => 'LIVREUR-9999',
        'reception_code' => 'RECEPTION-9999',
    ]);

    $orderService = app(OrderService::class);
    $orderService->assignDriver($order, $driver);

    $order->refresh();
    expect($order->driver_assigned_at)->not->toBeNull();
    expect($order->driver_id)->toBe($driver->id);
    expect($order->status)->toBe('driver_assigned');
});

// ─── Test 10 : Le helper isDriverStale fonctionne correctement ───────────────

test('Order::isDriverStale returns correct values', function () {
    $order = createAssignedOrder(minutesAgo: 20);
    expect($order->isDriverStale(15))->toBeTrue();
    expect($order->isDriverStale(25))->toBeFalse();

    // Un order qui n'est pas driver_assigned ne peut pas être stale
    $order->update(['status' => 'driver_picked_up']);
    expect($order->isDriverStale(15))->toBeFalse();
});
