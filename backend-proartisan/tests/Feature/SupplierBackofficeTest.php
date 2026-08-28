<?php

namespace Tests\Feature;

use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierBackofficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_login_is_rejected_on_admin_login(): void
    {
        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/admin/login', [
            'identifier' => $supplier->phone,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_supplier_cannot_access_admin_dashboard(): void
    {
        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);

        $response = $this->actingAs($supplier)
            ->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_supplier_can_access_supplier_api_endpoints(): void
    {
        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);

        FournisseurAgree::create([
            'user_id' => $supplier->id,
            'nom_boutique' => 'Quincaillerie Centrale',
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);

        // Dashboard statistics
        $response = $this->actingAs($supplier, 'sanctum')->getJson('/api/v1/supplier/dashboard');
        $response->assertOk()->assertJsonPath('success', true);

        // Orders list
        $response = $this->actingAs($supplier, 'sanctum')->getJson('/api/v1/supplier/orders');
        $response->assertOk()->assertJsonPath('success', true);

        // Litiges list
        $response = $this->actingAs($supplier, 'sanctum')->getJson('/api/v1/supplier/litiges');
        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_client_cannot_access_supplier_api_endpoints(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/v1/supplier/dashboard');
        $response->assertForbidden();

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/v1/supplier/orders');
        $response->assertForbidden();

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/v1/supplier/litiges');
        $response->assertForbidden();
    }

    public function test_supplier_api_dashboard_returns_correct_stats(): void
    {
        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);

        FournisseurAgree::create([
            'user_id' => $supplier->id,
            'nom_boutique' => 'Quincaillerie Centrale',
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);

        // Créer un produit
        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'name' => 'Ciment',
            'sku' => 'CIM-10',
            'unit_price' => 5000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($supplier, 'sanctum')
            ->getJson('/api/v1/supplier/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.catalog_count', 1)
            ->assertJsonPath('data.stats.total_orders', 0);
    }
}
