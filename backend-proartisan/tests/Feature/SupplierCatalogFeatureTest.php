<?php

namespace Tests\Feature;

use App\Models\FournisseurAgree;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCatalogFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_suppliers_are_listed_with_search_and_active_product_count(): void
    {
        [$approvedSupplier] = $this->makeApprovedSupplier(
            name: 'Quincaillerie Abidjan',
            shopName: 'Abidjan Materiaux'
        );
        [$otherApprovedSupplier] = $this->makeApprovedSupplier(
            name: 'Quincaillerie Yopougon',
            shopName: 'Yop Materiaux'
        );
        [$pendingSupplier] = $this->makeSupplier(
            name: 'Boutique En Attente',
            shopName: 'Pending Materiaux',
            status: 'en_attente'
        );

        SupplierProduct::create([
            'supplier_id' => $approvedSupplier->id,
            'name' => 'Ciment',
            'sku' => 'CIM-001',
            'unit_price' => 5000,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $approvedSupplier->id,
            'name' => 'Fer a beton',
            'sku' => 'FER-001',
            'unit_price' => 8000,
            'stock_quantity' => 10,
            'is_active' => false,
        ]);

        SupplierProduct::create([
            'supplier_id' => $otherApprovedSupplier->id,
            'name' => 'Brique',
            'sku' => 'BRI-001',
            'unit_price' => 1000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $this->actingAs($client)
            ->getJson('/api/v1/fournisseurs?search=Abidjan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approvedSupplier->id)
            ->assertJsonPath('data.0.shopName', 'Abidjan Materiaux')
            ->assertJsonPath('data.0.activeProductsCount', 1);

        $this->assertDatabaseHas('fournisseurs_agrees', [
            'user_id' => $pendingSupplier->id,
            'statut' => 'en_attente',
        ]);
    }

    public function test_supplier_products_endpoint_returns_only_active_products_for_approved_supplier(): void
    {
        [$supplier] = $this->makeApprovedSupplier();
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $active = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'name' => 'Brique',
            'sku' => 'BRI-001',
            'unit_price' => 1000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'name' => 'Ciment archive',
            'sku' => 'CIM-OLD',
            'unit_price' => 4500,
            'stock_quantity' => 0,
            'is_active' => false,
        ]);

        $this->actingAs($client)
            ->getJson("/api/v1/fournisseurs/{$supplier->id}/articles")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.isActive', true);
    }

    public function test_approved_supplier_can_manage_own_catalog_and_inactive_items_stay_visible_in_my_products(): void
    {
        [$supplier] = $this->makeApprovedSupplier();

        $storeResponse = $this->actingAs($supplier)
            ->postJson('/api/v1/supplier-products', [
                'name' => 'Peinture blanche',
                'sku' => 'PEI-001',
                'description' => 'Seau de 20 litres',
                'unit_price' => 25000,
                'stock_quantity' => 12,
                'image_url' => 'https://example.test/peinture.jpg',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Peinture blanche')
            ->assertJsonPath('data.isActive', true);

        $productId = $storeResponse->json('data.id');

        $this->actingAs($supplier)
            ->putJson("/api/v1/supplier-products/{$productId}", [
                'unit_price' => 27000,
                'stock_quantity' => 9,
            ])
            ->assertOk()
            ->assertJsonPath('data.unitPrice', 27000)
            ->assertJsonPath('data.stockQuantity', 9);

        $this->actingAs($supplier)
            ->deleteJson("/api/v1/supplier-products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.isActive', false);

        $this->actingAs($supplier)
            ->getJson('/api/v1/supplier-products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $productId)
            ->assertJsonPath('data.0.isActive', false);
    }

    public function test_unapproved_supplier_cannot_create_catalog_item(): void
    {
        [$supplier] = $this->makeSupplier(status: 'en_attente');

        $this->actingAs($supplier)
            ->postJson('/api/v1/supplier-products', [
                'name' => 'Peinture blanche',
                'sku' => 'PEI-001',
                'unit_price' => 25000,
                'stock_quantity' => 12,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.supplier.0', "Le fournisseur doit être agréé avant de gérer un catalogue.");
    }

    public function test_supplier_cannot_modify_another_supplier_product_and_cannot_duplicate_own_sku(): void
    {
        [$owner] = $this->makeApprovedSupplier(name: 'Owner Supplier');
        [$intruder] = $this->makeApprovedSupplier(name: 'Intruder Supplier');

        $ownerProduct = SupplierProduct::create([
            'supplier_id' => $owner->id,
            'name' => 'Ciment 50kg',
            'sku' => 'CIM-001',
            'unit_price' => 5000,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        SupplierProduct::create([
            'supplier_id' => $intruder->id,
            'name' => 'Sable',
            'sku' => 'SAB-001',
            'unit_price' => 2000,
            'stock_quantity' => 25,
            'is_active' => true,
        ]);

        $this->actingAs($intruder)
            ->putJson("/api/v1/supplier-products/{$ownerProduct->id}", [
                'name' => 'Produit pirate',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.product.0', "Cet article n'appartient pas à ce fournisseur.");

        $this->actingAs($intruder)
            ->postJson('/api/v1/supplier-products', [
                'name' => 'Autre sable',
                'sku' => 'SAB-001',
                'unit_price' => 2200,
                'stock_quantity' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.sku.0', 'Ce SKU existe déjà dans votre catalogue.');
    }

    public function test_supplier_can_create_and_update_product_with_image_and_camel_case_keys(): void
    {
        [$supplier] = $this->makeApprovedSupplier();

        $response = $this->actingAs($supplier)
            ->postJson('/api/v1/supplier-products', [
                'name' => 'Marteau professionnel',
                'sku' => 'MAR-001',
                'description' => 'Marteau à manche ergonomique',
                'unitPrice' => 8500,
                'stockQuantity' => 15,
                'imageUrl' => 'https://example.com/marteau.jpg',
                'isActive' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Marteau professionnel')
            ->assertJsonPath('data.unitPrice', 8500)
            ->assertJsonPath('data.unit_price', 8500)
            ->assertJsonPath('data.stockQuantity', 15)
            ->assertJsonPath('data.stock_quantity', 15)
            ->assertJsonPath('data.imageUrl', 'https://example.com/marteau.jpg')
            ->assertJsonPath('data.image_url', 'https://example.com/marteau.jpg')
            ->assertJsonPath('data.isActive', true)
            ->assertJsonPath('data.is_active', true);

        $productId = $response->json('data.id');

        $this->actingAs($supplier)
            ->putJson("/api/v1/supplier-products/{$productId}", [
                'unitPrice' => 9000,
                'stockQuantity' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('data.unitPrice', 9000)
            ->assertJsonPath('data.unit_price', 9000)
            ->assertJsonPath('data.stockQuantity', 20)
            ->assertJsonPath('data.stock_quantity', 20);
    }

    /**
     * @return array{0: User, 1: FournisseurAgree}
     */
    private function makeApprovedSupplier(
        string $name = 'Fournisseur Agree',
        string $shopName = 'Boutique Agree'
    ): array {
        return $this->makeSupplier($name, $shopName, 'agree');
    }

    /**
     * @return array{0: User, 1: FournisseurAgree}
     */
    private function makeSupplier(
        string $name = 'Fournisseur',
        string $shopName = 'Boutique',
        string $status = 'agree'
    ): array {
        $supplier = User::factory()->create([
            'name' => $name,
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);

        $agreement = FournisseurAgree::create([
            'user_id' => $supplier->id,
            'nom_boutique' => $shopName,
            'statut' => $status,
            'approuve_at' => $status === 'agree' ? now() : null,
        ]);
        $agreement->setPosition(5.35, -4.02);

        return [$supplier, $agreement];
    }
}
