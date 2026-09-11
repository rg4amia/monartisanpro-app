<?php

namespace Tests\Feature;

use App\Models\DeliveryTracking;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTelemetryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_send_location_telemetry(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $client->setPosition(5.3599, -4.0083);

        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $supplier->setPosition(5.3400, -3.9800);
        $supplier->fournisseurAgree()->create([
            'nom_boutique' => 'Quincaillerie Centrale',
            'position' => $supplier->position,
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);

        $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
        $driver->setPosition(5.3450, -3.9900);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'driver_id' => $driver->id,
            'delivery_mode' => 'delivery',
            'status' => 'driver_picked_up',
            'subtotal' => 10000,
            'delivery_cost' => 2000,
            'platform_fee' => 300,
            'total_amount' => 12300,
            'pickup_code' => 'LIVREUR-1111',
            'reception_code' => 'RECEPTION-2222',
        ]);

        $response = $this->actingAs($driver)->postJson("/api/v1/orders/{$order->id}/location", [
            'latitude' => 5.3512,
            'longitude' => -4.0015,
            'speed_kmh' => 34.5,
            'heading' => 88.0,
            'battery_level' => 85,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('delivery_trackings', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'latitude' => 5.3512,
            'longitude' => -4.0015,
            'speed_kmh' => 34.5,
            'heading' => 88.0,
            'battery_level' => 85,
        ]);
    }

    public function test_unassigned_driver_cannot_send_location(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $assignedDriver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
        $strangerDriver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'driver_id' => $assignedDriver->id,
            'delivery_mode' => 'delivery',
            'status' => 'driver_picked_up',
            'subtotal' => 10000,
            'delivery_cost' => 2000,
            'platform_fee' => 300,
            'total_amount' => 12300,
            'pickup_code' => 'LIVREUR-1111',
            'reception_code' => 'RECEPTION-2222',
        ]);

        $response = $this->actingAs($strangerDriver)->postJson("/api/v1/orders/{$order->id}/location", [
            'latitude' => 5.3512,
            'longitude' => -4.0015,
        ]);

        $response->assertForbidden();
    }

    public function test_regular_client_cannot_send_driver_location(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'driver_id' => $driver->id,
            'delivery_mode' => 'delivery',
            'status' => 'driver_picked_up',
            'subtotal' => 10000,
            'delivery_cost' => 2000,
            'platform_fee' => 300,
            'total_amount' => 12300,
            'pickup_code' => 'LIVREUR-1111',
            'reception_code' => 'RECEPTION-2222',
        ]);

        $response = $this->actingAs($client)->postJson("/api/v1/orders/{$order->id}/location", [
            'latitude' => 5.3512,
            'longitude' => -4.0015,
        ]);

        $response->assertForbidden();
    }

    public function test_client_can_retrieve_delivery_tracking_data(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $client->setPosition(5.3599, -4.0083);

        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $supplier->setPosition(5.3400, -3.9800);
        $supplier->fournisseurAgree()->create([
            'nom_boutique' => 'Quincaillerie Centrale',
            'position' => $supplier->position,
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);

        $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
        $driver->setPosition(5.3450, -3.9900);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'driver_id' => $driver->id,
            'delivery_mode' => 'delivery',
            'status' => 'driver_picked_up',
            'subtotal' => 10000,
            'delivery_cost' => 2000,
            'platform_fee' => 300,
            'total_amount' => 12300,
            'pickup_code' => 'LIVREUR-1111',
            'reception_code' => 'RECEPTION-2222',
        ]);

        DeliveryTracking::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'latitude' => 5.3470,
            'longitude' => -3.9920,
            'speed_kmh' => 42.0,
            'heading' => 120.0,
            'battery_level' => 90,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($client)->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'status' => 'driver_picked_up',
                'driver' => [
                    'id' => $driver->id,
                ],
            ],
        ]);
        $this->assertSame(5.3470, (float) $response->json('data.driver_latest_position.latitude'));
        $this->assertSame(-3.9920, (float) $response->json('data.driver_latest_position.longitude'));
    }
}
