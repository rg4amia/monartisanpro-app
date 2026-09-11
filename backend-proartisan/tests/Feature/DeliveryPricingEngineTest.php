<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\DeliveryPricingService;
use App\Services\GoogleMapsService;
use App\Services\OsrmRoutingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DeliveryPricingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fare_calculation_for_vehicle_classes_and_surge(): void
    {
        $mockMaps = Mockery::mock(GoogleMapsService::class);
        $mockMaps->shouldReceive('getDirections')
            ->andReturn([
                'distance' => 10000, // 10 km
                'duration' => 900,   // 15 min
            ]);

        $pricingService = new DeliveryPricingService($mockMaps);

        // Base = (10 * 150) + (15 * 50) = 1500 + 750 = 2250 FCFA
        $estimateMoto = $pricingService->estimateFare([
            'from' => ['lat' => 5.35, 'lng' => -4.00],
            'to' => ['lat' => 5.40, 'lng' => -3.95],
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);
        $this->assertSame(2250, $estimateMoto['delivery_cost']);
        $this->assertSame(1.0, $estimateMoto['vehicle_multiplier']);

        $estimateVoiture = $pricingService->estimateFare([
            'from' => ['lat' => 5.35, 'lng' => -4.00],
            'to' => ['lat' => 5.40, 'lng' => -3.95],
            'vehicle_class' => 'voiture',
            'surge_multiplier' => 1.0,
        ]);
        $this->assertSame(3375, $estimateVoiture['delivery_cost']); // 2250 * 1.5 = 3375

        $estimateCargo = $pricingService->estimateFare([
            'from' => ['lat' => 5.35, 'lng' => -4.00],
            'to' => ['lat' => 5.40, 'lng' => -3.95],
            'vehicle_class' => 'cargo',
            'surge_multiplier' => 2.0,
        ]);
        $this->assertSame(11250, $estimateCargo['delivery_cost']); // 2250 * 2.5 * 2.0 = 11250
    }

    public function test_intelligent_vehicle_recommendation_based_on_items(): void
    {
        $mockMaps = Mockery::mock(GoogleMapsService::class);
        $pricingService = new DeliveryPricingService($mockMaps);

        // Heavy material -> cargo
        $rec1 = $pricingService->recommendVehicleClass([
            ['name' => 'Sac de ciment CPJ 42.5', 'quantity' => 2],
        ]);
        $this->assertSame('cargo', $rec1);

        // Medium material -> voiture
        $rec2 = $pricingService->recommendVehicleClass([
            ['name' => 'Pot de peinture blanche 15L', 'quantity' => 1],
        ]);
        $this->assertSame('voiture', $rec2);

        // Light items -> moto
        $rec3 = $pricingService->recommendVehicleClass([
            ['name' => 'Boîte de vis placo', 'quantity' => 1],
        ]);
        $this->assertSame('moto', $rec3);

        // Bulk count >= 8 -> cargo
        $rec4 = $pricingService->recommendVehicleClass([
            ['name' => 'Interrupteur va-et-vient', 'quantity' => 10],
        ]);
        $this->assertSame('cargo', $rec4);
    }

    public function test_minimum_fare_enforced(): void
    {
        $mockMaps = Mockery::mock(GoogleMapsService::class);
        $mockMaps->shouldReceive('getDirections')
            ->andReturn([
                'distance' => 100, // 0.1 km
                'duration' => 60,  // 1 min
            ]);

        $pricingService = new DeliveryPricingService($mockMaps);

        // Raw: (0.1 * 150) + (1 * 50) = 15 + 50 = 65 FCFA -> clamped to 1,000 FCFA
        $estimate = $pricingService->estimateFare([
            'from' => ['lat' => 5.35, 'lng' => -4.00],
            'to' => ['lat' => 5.351, 'lng' => -4.001],
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);

        $this->assertSame(1000, $estimate['delivery_cost']);
        $this->assertSame(1000, $estimate['fare_breakdown']['minimum_fare']);
    }

    public function test_fallback_fare_when_coordinates_missing(): void
    {
        $mockMaps = Mockery::mock(GoogleMapsService::class);
        $pricingService = new DeliveryPricingService($mockMaps);

        $estimate = $pricingService->estimateFare([
            'from' => null,
            'to' => null,
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);

        $this->assertTrue($estimate['is_fallback']);
        $this->assertSame(2500, $estimate['delivery_cost']);
    }

    public function test_admin_setting_override_for_surge_pricing(): void
    {
        Setting::updateOrCreate(
            ['key' => 'delivery_surge_multiplier'],
            ['value' => '1.8', 'type' => 'float']
        );

        $mockMaps = Mockery::mock(GoogleMapsService::class);
        $mockMaps->shouldReceive('getDirections')
            ->andReturn([
                'distance' => 10000, // 10 km
                'duration' => 900,   // 15 min
            ]);

        $pricingService = new DeliveryPricingService($mockMaps);

        $estimate = $pricingService->estimateFare([
            'from' => ['lat' => 5.35, 'lng' => -4.00],
            'to' => ['lat' => 5.40, 'lng' => -3.95],
            'vehicle_class' => 'moto',
        ]);

        $this->assertSame(1.8, $estimate['surge_multiplier']);
        // 2250 * 1.0 * 1.8 = 4050 FCFA
        $this->assertSame(4050, $estimate['delivery_cost']);
    }

    public function test_estimate_delivery_api_endpoints(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);
        $client->setPosition(5.3599, -4.0083);

        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);
        $supplier->setPosition(5.3400, -3.9800);

        $supplier->fournisseurAgree()->create([
            'nom_boutique' => 'Quincaillerie Centrale',
            'position' => $supplier->position,
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);

        $this->mock(GoogleMapsService::class, function ($mock) {
            $mock->shouldReceive('getDirections')
                ->andReturn([
                    'distance' => 5000, // 5 km
                    'duration' => 600,  // 10 min
                ]);
        });

        // Test POST /api/v1/deliveries/estimate
        $response = $this->actingAs($client)->postJson('/api/v1/deliveries/estimate', [
            'supplier_id' => $supplier->id,
            'vehicle_class' => 'voiture',
            'surge_multiplier' => 1.0,
            'items' => [
                ['name' => 'Pot de peinture 10L', 'quantity' => 2],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'vehicle_class' => 'voiture',
                'vehicle_multiplier' => 1.5,
                'surge_multiplier' => 1.0,
                'recommended_vehicle_class' => 'voiture',
            ],
        ]);

        // (5 * 150 + 10 * 50) * 1.5 = (750 + 500) * 1.5 = 1250 * 1.5 = 1875 FCFA
        $this->assertSame(1875, $response->json('data.delivery_cost'));

        // Test POST /api/v1/orders/estimate-delivery alias
        $responseAlias = $this->actingAs($client)->postJson('/api/v1/orders/estimate-delivery', [
            'supplier_id' => $supplier->id,
            'vehicle_class' => 'cargo',
            'surge_multiplier' => 1.0,
            'items' => [
                ['name' => 'Sac de ciment', 'quantity' => 1],
            ],
        ]);

        $responseAlias->assertOk();
        $this->assertSame('cargo', $responseAlias->json('data.recommended_vehicle_class'));
        // (1250) * 2.5 = 3125 FCFA
        $this->assertSame(3125, $responseAlias->json('data.delivery_cost'));
    }

    public function test_night_surge_window_extends_until_six_am(): void
    {
        $mockMaps = Mockery::mock(GoogleMapsService::class);
        $pricingService = new DeliveryPricingService($mockMaps);

        // 05h30 (Africa/Abidjan = UTC+0) : encore dans la fenêtre de nuit 22h-06h.
        Carbon::setTestNow(Carbon::parse('2026-01-15 05:30:00', 'UTC'));
        $this->assertSame(1.20, $pricingService->calculateSurgeMultiplier());

        // 06h00 pile : la surcharge de nuit ne s'applique plus.
        Carbon::setTestNow(Carbon::parse('2026-01-15 06:00:00', 'UTC'));
        $this->assertSame(1.0, $pricingService->calculateSurgeMultiplier());

        Carbon::setTestNow();
    }

    public function test_heavy_order_is_billed_at_cargo_rate_even_if_moto_was_selected(): void
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

        $product = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'name' => 'Sac de ciment CPJ 42.5',
            'unit_price' => 6000,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'delivery_mode' => 'delivery',
            'status' => 'paid',
            'subtotal' => 12000,
            'delivery_cost' => 0,
            'platform_fee' => 0,
            'total_amount' => 12000,
            'pickup_code' => 'LIVREUR-0001',
            'reception_code' => 'RECEPTION-0001',
            'vehicle_class' => 'moto', // Choix initial sous-évalué par le client
            'surge_multiplier' => 1.0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'supplier_product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 6000,
        ]);

        $this->mock(GoogleMapsService::class, function ($mock) {
            $mock->shouldReceive('getDirections')->andReturn([
                'distance' => 10000, // 10 km
                'duration' => 900,   // 15 min
            ]);
        });

        $pricingService = app(DeliveryPricingService::class);
        $freshOrder = $order->fresh();
        $cost = $pricingService->calculateOrderDeliveryCost($freshOrder);

        // Base (2250) * cargo (2.5) et non moto (1.0) : le ciment force la classe cargo.
        $this->assertSame(5625, $cost);
        $this->assertSame('cargo', $freshOrder->vehicle_class);
    }

    public function test_osrm_is_used_as_free_fallback_when_maps_providers_are_unavailable(): void
    {
        $mockMaps = Mockery::mock(GoogleMapsService::class);
        $mockMaps->shouldReceive('getDirections')->andReturn([
            'distance' => 0,
            'duration' => 0,
            'source' => 'haversine_fallback',
        ]);

        $mockOsrm = Mockery::mock(OsrmRoutingService::class);
        $mockOsrm->shouldReceive('calculateRoute')
            ->once()
            ->andReturn([
                'distance_km' => 8.0,
                'duration_min' => 12.0,
                'is_fallback' => false,
            ]);

        $pricingService = new DeliveryPricingService($mockMaps, $mockOsrm);

        // (8 * 150) + (12 * 50) = 1200 + 600 = 1800 FCFA (classe moto, sans surcharge)
        $estimate = $pricingService->estimateFare([
            'from' => ['lat' => 5.35, 'lng' => -4.00],
            'to' => ['lat' => 5.40, 'lng' => -3.95],
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);

        $this->assertSame(1800, $estimate['delivery_cost']);
        $this->assertFalse($estimate['is_fallback']);
    }
}
