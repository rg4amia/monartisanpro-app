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

    public function test_supplier_dashboard_redirect_on_login(): void
    {
        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);

        $response = $this->actingAs($supplier)
            ->get('/admin/login');

        $response->assertRedirect(route('supplier.dashboard'));
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

    public function test_supplier_can_access_supplier_backoffice_views(): void
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

        $response = $this->actingAs($supplier)->get('/supplier/dashboard');
        $response->assertOk();

        $response = $this->actingAs($supplier)->get('/supplier/catalog');
        $response->assertOk();

        $response = $this->actingAs($supplier)->get('/supplier/orders');
        $response->assertOk();

        $response = $this->actingAs($supplier)->get('/supplier/litiges');
        $response->assertOk();
    }

    public function test_client_cannot_access_supplier_backoffice(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $response = $this->actingAs($client)->get('/supplier/dashboard');
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

        $response = $this->actingAs($supplier)
            ->getJson('/api/v1/supplier/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.catalog_count', 1)
            ->assertJsonPath('data.stats.total_orders', 0);
    }
}
