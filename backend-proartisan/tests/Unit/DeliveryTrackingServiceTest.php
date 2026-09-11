<?php

namespace Tests\Unit;

use App\Models\DeliveryTracking;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\RealtimeEvent;
use App\Models\User;
use App\Services\OrderService;
use App\Services\OsrmRoutingService;
use App\Services\RealtimeEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private OsrmRoutingService $osrmService;
    private OrderService $orderService;
    private RealtimeEventService $realtimeEventService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->osrmService = new OsrmRoutingService();
        $this->realtimeEventService = new RealtimeEventService();
        $this->orderService = app(OrderService::class);
    }

    private function createTestOrder(array $attributes = []): Order
    {
        $client = User::factory()->create([
            'role'     => 'client',
            'phone'    => '+225070' . rand(1000000, 9999999),
            'position' => '5.3599,-4.0083',
        ]);

        $supplier = User::factory()->create([
            'role'     => 'fournisseur',
            'phone'    => '+225050' . rand(1000000, 9999999),
            'position' => '5.3484,-4.0267',
        ]);

        $driver = User::factory()->create([
            'role'     => 'livreur',
            'phone'    => '+225010' . rand(1000000, 9999999),
            'position' => '5.3480,-4.0250',
        ]);

        FournisseurAgree::updateOrCreate(
            ['user_id' => $supplier->id],
            [
                'nom_boutique' => 'Quincaillerie Centrale Abidjan',
                'statut'       => 'agree',
                'position'     => '5.3484,-4.0267',
            ]
        );

        return Order::create(array_merge([
            'client_id'                 => $client->id,
            'supplier_id'               => $supplier->id,
            'driver_id'                 => $driver->id,
            'delivery_mode'             => 'delivery',
            'status'                    => 'driver_assigned',
            'subtotal'                  => 25000,
            'delivery_cost'             => 3500,
            'platform_fee'              => 500,
            'total_amount'              => 29000,
            'pickup_code'               => 'LIV-PICK-1234',
            'reception_code'            => 'LIV-RECP-5678',
            'delivery_latitude'         => 5.3599,
            'delivery_longitude'        => -4.0083,
            'driver_assigned_at'        => Carbon::now(),
            'driver_reassignment_count' => 0,
        ], $attributes));
    }

    public function test_osrm_routing_fallback_calculation(): void
    {
        // Forcing fallback by requesting coordinates with no network or timeout
        Http::fake([
            '*router.project-osrm.org*' => Http::response(null, 500),
        ]);

        $route = $this->osrmService->calculateRoute(5.3484, -4.0267, 5.3599, -4.0083);

        $this->assertIsArray($route);
        $this->assertGreaterThan(0, $route['distance_km']);
        $this->assertGreaterThan(0, $route['duration_minutes']);
        $this->assertEquals('haversine_estimate', $route['source']);
        $this->assertCount(2, $route['coordinates']);
    }

    public function test_osrm_routing_with_successful_api(): void
    {
        Http::fake([
            '*router.project-osrm.org*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 6500, // 6.5 km
                        'duration' => 900,  // 15 min
                        'geometry' => [
                            'coordinates' => [
                                [-4.0267, 5.3484],
                                [-4.0150, 5.3520],
                                [-4.0083, 5.3599],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $route = $this->osrmService->calculateRoute(5.3484, -4.0267, 5.3599, -4.0083);

        $this->assertEquals(6.5, $route['distance_km']);
        $this->assertEquals(15.0, $route['duration_minutes']);
        $this->assertEquals('osrm', $route['source']);
        $this->assertCount(3, $route['coordinates']);
    }

    public function test_record_driver_location_and_realtime_broadcast(): void
    {
        $order = $this->createTestOrder();
        $driver = $order->driver;

        $tracking = $this->orderService->recordDriverLocation(
            $order,
            $driver,
            5.3480,
            -4.0250,
            42.5,
            180.0,
            92
        );

        $this->assertInstanceOf(DeliveryTracking::class, $tracking);
        $this->assertEquals(5.3480, $tracking->latitude);
        $this->assertEquals(-4.0250, $tracking->longitude);
        $this->assertEquals(42.5, $tracking->speed_kmh);
        $this->assertEquals(180.0, $tracking->heading);
        $this->assertEquals(92, $tracking->battery_level);

        // Verify relation latestTracking
        $order->refresh();
        $this->assertNotNull($order->latestTracking);
        $this->assertEquals($tracking->id, $order->latestTracking->id);

        // Verify RealtimeEvent emitted on order channel
        $this->assertDatabaseHas('mission_realtime_events', [
            'event_type' => 'driver_position_updated',
        ]);
    }

    public function test_get_delivery_tracking_data_payload(): void
    {
        Http::fake([
            '*router.project-osrm.org*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 4000,
                        'duration' => 600,
                        'geometry' => [
                            'coordinates' => [
                                [-4.0250, 5.3480],
                                [-4.0083, 5.3599],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $order = $this->createTestOrder();
        $driver = $order->driver;

        $this->orderService->recordDriverLocation($order, $driver, 5.3480, -4.0250, 30.0, 90.0, 80);

        $payload = $this->orderService->getDeliveryTrackingData($order);

        $this->assertIsArray($payload);
        $this->assertEquals($order->id, $payload['order_id']);
        $this->assertEquals('driver_assigned', $payload['status']);
        $this->assertEquals($driver->id, $payload['driver']['id']);
        $this->assertNotNull($payload['driver_latest_position']);
        $this->assertEquals(5.3480, $payload['driver_latest_position']['latitude']);
        $this->assertArrayHasKey('routing', $payload);
        $this->assertEquals(4.0, $payload['routing']['distance_km']);
        $this->assertEquals('LIV-PICK-1234', $payload['codes']['pickup_code']);
    }

    public function test_driver_location_api_endpoint(): void
    {
        $order = $this->createTestOrder();
        $driver = $order->driver;

        $response = $this->actingAs($driver, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/location", [
                'latitude'      => 5.3490,
                'longitude'     => -4.0210,
                'speed_kmh'     => 38.0,
                'heading'       => 120.5,
                'battery_level' => 78,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order_id' => $order->id,
            ]);

        $this->assertDatabaseHas('delivery_trackings', [
            'order_id'      => $order->id,
            'driver_id'     => $driver->id,
            'battery_level' => 78,
        ]);
    }

    public function test_order_tracking_api_endpoint(): void
    {
        $order = $this->createTestOrder();
        $client = $order->client;

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order_id' => $order->id,
            ]);
    }

    public function test_order_pickup_and_delivery_validation(): void
    {
        Storage::fake('public');
        $order = $this->createTestOrder();
        $driver = $order->driver;

        $photoPickup = UploadedFile::fake()->image('pickup.jpg', 600, 400);

        // 1. Pickup
        $responsePickup = $this->actingAs($driver, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/pickup", [
                'pickup_code' => 'LIV-PICK-1234',
                'photo'       => $photoPickup,
            ]);

        $responsePickup->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status'  => 'driver_picked_up',
            ]);

        $order->refresh();
        $this->assertEquals('driver_picked_up', $order->status);
        $this->assertNotNull($order->pickup_photo_url);

        // 2. Deliver
        $photoDelivery = UploadedFile::fake()->image('delivery.jpg', 600, 400);

        $responseDelivery = $this->actingAs($driver, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/deliver", [
                'reception_code' => 'LIV-RECP-5678',
                'photo'          => $photoDelivery,
            ]);

        $responseDelivery->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status'  => 'delivered',
            ]);

        $order->refresh();
        $this->assertEquals('delivered', $order->status);
        $this->assertNotNull($order->delivery_photo_url);
    }

    public function test_admin_emergency_reassign(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '+2250700000099',
        ]);

        $order = $this->createTestOrder();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/reassign", [
                'reason' => 'Livreur immobilisé pour panne mécanique',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status'  => 'searching_driver',
            ]);

        $order->refresh();
        $this->assertEquals('searching_driver', $order->status);
        $this->assertNull($order->driver_id);
        $this->assertEquals(1, $order->driver_reassignment_count);
    }
}
