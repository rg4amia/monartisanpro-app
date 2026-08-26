<?php

namespace Tests\Feature;

use App\Models\Devis;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\Order;
use App\Models\User;
use App\Models\FournisseurAgree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileMoneyValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_must_provide_mobile_money_when_requesting_devis_if_missing(): void
    {
        /** @var User $client */
        $client = User::factory()->create([
            'name' => 'Client Test',
            'role' => 'client',
            'kyc_status' => 'actif',
            'payment_phone' => null,
            'preferred_payment_provider' => null,
        ]);

        // 1. Attempt to create mission without payment_phone -> 422 Validation Error
        $response = $this->actingAs($client)
            ->postJson('/api/v1/missions', [
                'description' => 'Besoin de reparer la toiture de ma maison principale.',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_phone', 'preferred_payment_provider']);

        // 2. Supply payment details in request -> succeeds and updates profile
        $responseSuccess = $this->actingAs($client)
            ->postJson('/api/v1/missions', [
                'description' => 'Besoin de reparer la toiture de ma maison principale.',
                'payment_phone' => '0709090909',
                'preferred_payment_provider' => 'wave',
            ]);

        $responseSuccess->assertCreated();
        $this->assertEquals('0709090909', $client->fresh()->payment_phone);
        $this->assertEquals('wave', $client->fresh()->preferred_payment_provider);
    }

    public function test_artisan_must_provide_mobile_money_when_submitting_devis_if_missing(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['name' => 'Client Test', 'role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create([
            'name' => 'Artisan Test',
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'payment_phone' => null,
            'preferred_payment_provider' => null,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Maconnerie generale et construction de cloture.',
            'status' => 'pending_artisan_acceptance',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ]);

        // 1. Create devis without payment_phone -> 422 Validation Error
        $response = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 40000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Phase unique', 'montant' => 40000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_phone', 'preferred_payment_provider']);

        // 2. Supply payment details -> succeeds and updates profile
        $responseSuccess = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 40000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Phase unique', 'montant' => 40000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
                'payment_phone' => '0711112222',
                'preferred_payment_provider' => 'orange_money',
            ]);

        $responseSuccess->assertCreated();
        $this->assertEquals('0711112222', $artisan->fresh()->payment_phone);
        $this->assertEquals('orange_money', $artisan->fresh()->preferred_payment_provider);
    }

    public function test_supplier_must_provide_mobile_money_when_scanning_jcode_if_missing(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['name' => 'Client Test', 'role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create(['name' => 'Artisan Test', 'role' => 'artisan', 'kyc_status' => 'actif']);
        /** @var User $supplier */
        $supplier = User::factory()->create([
            'name' => 'Supplier Test',
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
            'payment_phone' => null,
            'preferred_payment_provider' => null,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Menuiserie',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 50000,
            'montant_mo' => 50000,
            'ratio_materiaux' => 0.50,
        ]);

        $product = \App\Models\SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'name' => 'Ciment',
            'sku' => 'CIM-001',
            'unit_price' => 10000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        FournisseurAgree::create([
            'user_id' => $supplier->id,
            'nom_boutique' => 'Ma Boutique',
            'statut' => 'agree',
            'approuve_at' => now(),
        ])->setPosition(5.33, -4.06);

        $jcode = app(\App\Services\JCodeService::class)->generate($mission, $artisan, $supplier, [
            [
                'supplier_product_id' => $product->id,
                'quantity' => 2,
            ],
        ]);

        // 1. Scan J-Code without payment_phone -> 422
        $response = $this->actingAs($supplier)
            ->postJson("/api/v1/jcodes/{$jcode->code}/scan", [
                'lat' => 5.33,
                'lng' => -4.06,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_phone', 'preferred_payment_provider']);

        // 2. Scan J-Code with payment details -> succeeds and updates profile
        $responseSuccess = $this->actingAs($supplier)
            ->postJson("/api/v1/jcodes/{$jcode->code}/scan", [
                'lat' => 5.33,
                'lng' => -4.06,
                'payment_phone' => '0708080808',
                'preferred_payment_provider' => 'wave',
            ]);

        $responseSuccess->assertOk();
        $this->assertEquals('0708080808', $supplier->fresh()->payment_phone);
        $this->assertEquals('wave', $supplier->fresh()->preferred_payment_provider);
    }

    public function test_driver_must_provide_mobile_money_when_accepting_delivery_if_missing(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['name' => 'Client Test', 'role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $supplier */
        $supplier = User::factory()->create(['name' => 'Supplier Test', 'role' => 'fournisseur', 'kyc_status' => 'actif']);
        /** @var User $driver */
        $driver = User::factory()->create([
            'name' => 'Driver Test',
            'role' => 'driver',
            'kyc_status' => 'actif',
            'payment_phone' => null,
            'preferred_payment_provider' => null,
        ]);

        FournisseurAgree::create([
            'user_id' => $supplier->id,
            'nom_boutique' => 'Boutique Test',
            'statut' => 'agree',
            'approuve_at' => now(),
        ])->setPosition(5.33, -4.06);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'delivery_mode' => 'delivery',
            'status' => 'searching_driver',
            'subtotal' => 15000,
            'delivery_cost' => 2000,
            'platform_fee' => 500,
            'total_amount' => 17500,
            'pickup_code' => 'LIVREUR-1234',
            'reception_code' => 'RECEPTION-1234',
        ]);

        // 1. Accept course without payment_phone -> 422
        $response = $this->actingAs($driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_phone', 'preferred_payment_provider']);

        // 2. Accept course with payment details -> succeeds and updates profile
        $responseSuccess = $this->actingAs($driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept", [
                'payment_phone' => '0707070707',
                'preferred_payment_provider' => 'wave',
            ]);

        $responseSuccess->assertOk();
        $this->assertEquals('0707070707', $driver->fresh()->payment_phone);
        $this->assertEquals('wave', $driver->fresh()->preferred_payment_provider);
    }
}
