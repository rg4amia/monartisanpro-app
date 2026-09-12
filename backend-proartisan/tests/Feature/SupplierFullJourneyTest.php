<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parcours fournisseur de bout en bout, via l'API HTTP : mise en catalogue
 * d'un article -> commande client -> réception dans le tableau de bord ->
 * préparation -> remise au livreur ou retrait client -> libération des fonds
 * -> évaluation.
 *
 * Le catalogue et le J-Code étaient déjà couverts isolément ; l'enchaînement
 * commande e-commerce vu du fournisseur (préparation, décrément de stock,
 * encaissement) ne l'était pas.
 */
class SupplierFullJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->client->setPosition(5.3599, -4.0083);

        $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $this->supplier->setPosition(5.3400, -3.9800);
        $this->supplier->fournisseurAgree()->create([
            'nom_boutique' => 'Quincaillerie Centrale',
            'position' => $this->supplier->position,
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);
    }

    private function publishProduct(int $stock = 20, int $price = 5000): SupplierProduct
    {
        $response = $this->actingAs($this->supplier)->postJson('/api/v1/supplier-products', [
            'name' => 'Ciment CPA 45',
            'sku' => 'CIM-JOURNEY',
            'unit_price' => $price,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);

        $response->assertCreated();

        return SupplierProduct::where('sku', 'CIM-JOURNEY')->firstOrFail();
    }

    private function placeOrder(SupplierProduct $product, int $quantity, string $mode): Order
    {
        $response = $this->actingAs($this->client)->postJson('/api/v1/orders', [
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => $mode,
            'items' => [
                ['supplier_product_id' => $product->id, 'quantity' => $quantity],
            ],
        ]);

        $response->assertCreated();

        return Order::latest('id')->firstOrFail();
    }

    public function test_supplier_publishes_sells_prepares_and_gets_paid_on_store_pickup(): void
    {
        // ── 1. Mise en catalogue ──
        $product = $this->publishProduct(stock: 20, price: 5000);
        $this->assertTrue($product->is_active);

        // ── 2. Commande client en retrait magasin ──
        $order = $this->placeOrder($product, 3, 'pickup');
        $this->assertSame('paid', $order->status);
        $this->assertSame($this->supplier->id, $order->supplier_id);

        // Le stock est décrémenté à la commande.
        $this->assertSame(17, (int) $product->fresh()->stock_quantity);

        // ── 3. La commande apparaît dans l'espace fournisseur ──
        $dashboardOrders = $this->actingAs($this->supplier)->getJson('/api/v1/supplier/orders');
        $dashboardOrders->assertOk();
        $this->assertSame(
            [$order->id],
            collect($dashboardOrders->json('data'))->pluck('id')->all()
        );

        // Et elle reste invisible pour un autre fournisseur.
        $otherSupplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $this->assertSame(
            [],
            collect(
                $this->actingAs($otherSupplier)
                    ->getJson('/api/v1/supplier/orders')
                    ->json('data')
            )->pluck('id')->all()
        );

        // ── 4. Préparation ──
        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/prepared")
            ->assertOk();

        $order->refresh();
        // En retrait magasin la commande attend le client, sans livreur.
        $this->assertSame('prepared', $order->status);

        // ── 5. Retrait par le client avec le code ──
        $this->actingAs($this->client)
            ->postJson("/api/v1/orders/{$order->id}/verify-pickup", [
                'code' => $order->pickup_code,
            ])
            ->assertOk();

        $order->refresh();
        $this->assertSame('delivered', $order->status);

        // ── 6. Encaissement du fournisseur, commission déduite ──
        $payout = Transaction::where('wallet_dest', 'supplier_wallet_'.$this->supplier->id)->first();
        $this->assertNotNull($payout, 'Le fournisseur doit être crédité après remise de la marchandise.');

        $expected = (int) $order->subtotal - (int) round($order->subtotal * 0.05);
        $this->assertSame($expected, (int) $payout->montant);

        // ── 7. Le fournisseur devient évaluable ──
        $actors = $this->actingAs($this->client)
            ->getJson("/api/v1/orders/{$order->id}/evaluations-status");
        $actors->assertOk();

        $supplierActor = collect($actors->json('data.actors'))->firstWhere('role', 'fournisseur');
        $this->assertNotNull($supplierActor);
        $this->assertFalse($supplierActor['is_evaluated']);

        $this->actingAs($this->client)
            ->postJson('/api/v1/evaluations', [
                'order_id' => $order->id,
                'evalue_id' => $this->supplier->id,
                'note' => 5,
                'commentaire' => 'Matériaux conformes',
            ])
            ->assertCreated();

        $after = $this->actingAs($this->client)
            ->getJson("/api/v1/orders/{$order->id}/evaluations-status");
        $supplierAfter = collect($after->json('data.actors'))->firstWhere('role', 'fournisseur');
        $this->assertTrue($supplierAfter['is_evaluated']);
    }

    public function test_prepared_order_in_delivery_mode_goes_looking_for_a_driver(): void
    {
        $product = $this->publishProduct();
        $order = $this->placeOrder($product, 2, 'delivery');

        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/prepared")
            ->assertOk();

        $order->refresh();
        // En mode livraison, la préparation met la course sur le marché.
        $this->assertSame('searching_driver', $order->status);

        $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
        $driver->setPosition(5.3450, -3.9900);

        $available = $this->actingAs($driver)->getJson('/api/v1/deliveries/available');
        $this->assertContains(
            $order->id,
            collect($available->json('data'))->pluck('id')->all()
        );
    }

    public function test_only_the_owning_supplier_can_prepare_an_order(): void
    {
        $product = $this->publishProduct();
        $order = $this->placeOrder($product, 1, 'pickup');

        $otherSupplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);

        $this->actingAs($otherSupplier)
            ->postJson("/api/v1/orders/{$order->id}/prepared")
            ->assertForbidden();

        $this->actingAs($this->client)
            ->postJson("/api/v1/orders/{$order->id}/prepared")
            ->assertForbidden();

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_an_order_cannot_be_prepared_twice(): void
    {
        $product = $this->publishProduct();
        $order = $this->placeOrder($product, 1, 'pickup');

        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/prepared")
            ->assertOk();

        // Le second appel doit être refusé : l'état n'est plus « payée ».
        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/prepared")
            ->assertStatus(400);

        $this->assertSame('prepared', $order->fresh()->status);
    }

    public function test_ordering_more_than_available_stock_is_rejected(): void
    {
        $product = $this->publishProduct(stock: 2);

        $this->actingAs($this->client)->postJson('/api/v1/orders', [
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'pickup',
            'items' => [
                ['supplier_product_id' => $product->id, 'quantity' => 5],
            ],
        ])->assertStatus(400);

        // Ni commande créée, ni stock entamé.
        $this->assertSame(0, Order::count());
        $this->assertSame(2, (int) $product->fresh()->stock_quantity);
    }

    public function test_a_wrong_pickup_code_does_not_release_supplier_funds(): void
    {
        $product = $this->publishProduct();
        $order = $this->placeOrder($product, 1, 'pickup');

        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/prepared")
            ->assertOk();

        $this->actingAs($this->client)
            ->postJson("/api/v1/orders/{$order->id}/verify-pickup", ['code' => 'RETRAIT-0000'])
            ->assertStatus(400);

        $this->assertSame('prepared', $order->fresh()->status);
        $this->assertDatabaseMissing('transactions', [
            'wallet_dest' => 'supplier_wallet_'.$this->supplier->id,
        ]);
    }
}
