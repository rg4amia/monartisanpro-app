<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint5ComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_surge_pricing_and_vehicle_multipliers(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $client->setPosition(5.3484, -4.0305); // Abidjan Cocody

        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $supplier->setPosition(5.3598, -4.0083); // Abidjan Bingerville
        $supplier->fournisseurAgree()->create([
            'nom_boutique' => 'Bingerville Quincaillerie',
            'position' => $supplier->position,
            'statut' => 'agree',
        ]);

        $product = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'sku' => 'CIM-425',
            'name' => 'Ciment Portland CPJ 42.5',
            'description' => 'Sac de ciment',
            'unit_price' => 5000,
            'stock_quantity' => 100,
            'status' => 'active',
        ]);

        // Mock Maps Service to return a fixed distance of 10km (10,000m) and 15 mins (900s)
        $this->mock(GoogleMapsService::class, function ($mock) {
            $mock->shouldReceive('getDirections')
                ->andReturn([
                    'distance' => 10000, // 10 km
                    'duration' => 900,  // 15 mins
                ]);
        });

        // 1. Order with Moto and Surge = 1.0 (default)
        // Base cost: (10 km * 150) + (15 mins * 50) = 1500 + 750 = 2250 FCFA
        $driver = User::factory()->create(['role' => 'driver', 'kyc_status' => 'actif']);

        $response1 = $this->actingAs($client)
            ->postJson('/api/v1/orders', [
                'supplier_id' => $supplier->id,
                'delivery_mode' => 'delivery',
                'items' => [
                    [
                        'supplier_product_id' => $product->id,
                        'quantity' => 1,
                    ]
                ],
                'vehicle_class' => 'moto',
                'surge_multiplier' => 1.0,
            ]);

        $response1->assertCreated();
        $order1 = Order::findOrFail($response1->json('data.id'));
        
        // Préparer et accepter la commande pour déclencher le calcul de livraison
        $this->actingAs($supplier)->postJson("/api/v1/orders/{$order1->id}/prepared")->assertOk();
        $this->actingAs($driver)->postJson("/api/v1/deliveries/{$order1->id}/accept")->assertOk();
        $this->assertSame(2250, $order1->fresh()->delivery_cost);

        // 2. Order with Voiture (1.5x) and Surge = 1.0
        // Expected: 2250 * 1.5 = 3375 FCFA
        $response2 = $this->actingAs($client)
            ->postJson('/api/v1/orders', [
                'supplier_id' => $supplier->id,
                'delivery_mode' => 'delivery',
                'items' => [
                    [
                        'supplier_product_id' => $product->id,
                        'quantity' => 1,
                    ]
                ],
                'vehicle_class' => 'voiture',
                'surge_multiplier' => 1.0,
            ]);

        $response2->assertCreated();
        $order2 = Order::findOrFail($response2->json('data.id'));
        
        $this->actingAs($supplier)->postJson("/api/v1/orders/{$order2->id}/prepared")->assertOk();
        $this->actingAs($driver)->postJson("/api/v1/deliveries/{$order2->id}/accept")->assertOk();
        $this->assertSame(3375, $order2->fresh()->delivery_cost);

        // 3. Order with Cargo (2.5x) and Surge = 2.0
        // Expected: 2250 * 2.5 * 2.0 = 11250 FCFA
        $response3 = $this->actingAs($client)
            ->postJson('/api/v1/orders', [
                'supplier_id' => $supplier->id,
                'delivery_mode' => 'delivery',
                'items' => [
                    [
                        'supplier_product_id' => $product->id,
                        'quantity' => 1,
                    ]
                ],
                'vehicle_class' => 'cargo',
                'surge_multiplier' => 2.0,
            ]);

        $response3->assertCreated();
        $order3 = Order::findOrFail($response3->json('data.id'));
        
        $this->actingAs($supplier)->postJson("/api/v1/orders/{$order3->id}/prepared")->assertOk();
        $this->actingAs($driver)->postJson("/api/v1/deliveries/{$order3->id}/accept")->assertOk();
        $this->assertSame(11250, $order3->fresh()->delivery_cost);
        $this->assertSame('cargo', $order3->fresh()->vehicle_class);
        $this->assertSame(2.0, (float)$order3->fresh()->surge_multiplier);
    }
}
