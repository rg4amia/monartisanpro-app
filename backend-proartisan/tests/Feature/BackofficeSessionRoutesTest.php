<?php

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Le backoffice appelle ses propres routes `/admin/*` (session web), jamais les
 * routes `/api/v1` de l'application mobile, qui exigent un jeton et répondaient
 * 401 en production : l'écran renvoyait alors au tableau de bord.
 */

function backofficeAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
}

function backofficeOrder(string $status = 'driver_assigned'): Order
{
    $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

    return Order::create([
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'driver_id' => $driver->id,
        'status' => $status,
        'subtotal' => 25000,
        'delivery_cost' => 2000,
        'platform_fee' => 0,
        'total_amount' => 27000,
        'pickup_code' => '4821',
        'reception_code' => '7734',
        'delivery_mode' => 'delivery',
        'driver_assigned_at' => now()->subMinutes(5),
    ]);
}

test('le suivi d\'une course se lit par la route du backoffice', function () {
    $order = backofficeOrder();

    $this->actingAs(backofficeAdmin())
        ->getJson("/admin/orders/{$order->id}/tracking")
        ->assertOk()
        ->assertJsonPath('order_id', $order->id)
        ->assertJsonStructure(['tracking']);
});

test('la réaffectation par le backoffice redirige, relance la recherche et est auditée', function () {
    $order = backofficeOrder();
    $driverId = $order->driver_id;
    $admin = backofficeAdmin();

    $this->actingAs($admin)
        ->from('/admin/missions')
        ->post("/admin/orders/{$order->id}/reassign", ['reason' => 'Inactivité constatée'], ['X-Inertia' => 'true'])
        ->assertRedirect('/admin/missions')
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('searching_driver')
        ->and($order->driver_id)->toBeNull();

    $log = AdminActivityLog::where('action', 'delivery.reassigned')->first();
    expect($log)->not->toBeNull()
        ->and($log->admin_id)->toBe($admin->id)
        ->and($log->context['previous_driver_id'])->toBe($driverId);
});

test('un administrateur sans la capacité de gestion des missions ne réaffecte pas une course', function () {
    $order = backofficeOrder();
    $lecteur = backofficeAdmin();
    app(AdminPermissionService::class)->sync($lecteur, ['admin.missions.view'], backofficeAdmin());

    $this->actingAs($lecteur)
        ->post("/admin/orders/{$order->id}/reassign", ['reason' => 'Test'])
        ->assertForbidden();
    $this->actingAs($lecteur)
        ->postJson("/api/v1/orders/{$order->id}/reassign", ['reason' => 'Test'])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe('driver_assigned');
});

test('les droits d\'un rôle se modifient par la route du backoffice', function () {
    $admin = backofficeAdmin();
    $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

    $this->actingAs($admin)
        ->from('/admin/roles-permissions')
        ->post('/admin/roles-permissions/revoke', ['role' => 'artisan', 'permission' => 'devis.update'], ['X-Inertia' => 'true'])
        ->assertRedirect('/admin/roles-permissions');
    expect($artisan->fresh()->hasPermissionTo('devis.update'))->toBeFalse();

    $this->actingAs($admin)
        ->from('/admin/roles-permissions')
        ->post('/admin/roles-permissions/assign', ['role' => 'artisan', 'permission' => 'devis.update'], ['X-Inertia' => 'true'])
        ->assertRedirect('/admin/roles-permissions');
    expect($artisan->fresh()->hasPermissionTo('devis.update'))->toBeTrue();
});

test('un compte qui n\'est pas administrateur n\'atteint aucune de ces routes', function () {
    $order = backofficeOrder();
    $client = User::find($order->client_id);

    $this->actingAs($client)->get("/admin/orders/{$order->id}/tracking")->assertStatus(403);
    $this->actingAs($client)->post("/admin/orders/{$order->id}/reassign")->assertStatus(403);
    $this->actingAs($client)->post('/admin/roles-permissions/assign', ['role' => 'artisan', 'permission' => 'mission.create'])->assertStatus(403);
});
