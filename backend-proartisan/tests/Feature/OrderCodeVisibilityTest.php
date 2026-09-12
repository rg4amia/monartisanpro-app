<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qui connaît les codes de retrait et de réception.
 *
 * Ces codes sont la seule preuve qu'une remise a physiquement eu lieu. Celui
 * qui remet la marchandise les détient et les vérifie ; celui qui la reçoit
 * doit les demander. Le livreur ne doit donc en connaître aucun : les lui
 * fournir lui permettrait de valider retrait et livraison — donc de se faire
 * payer — sans avoir rencontré personne.
 */
class OrderCodeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $supplier;

    private User $driver;

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

        $this->driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
        $this->driver->setPosition(5.3450, -3.9900);
    }

    private function order(string $mode = 'delivery', array $overrides = []): Order
    {
        return Order::create(array_merge([
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'paid',
            'delivery_mode' => $mode,
            'subtotal' => 40000,
            'delivery_cost' => 5000,
            'platform_fee' => 1000,
            'total_amount' => 46000,
            'pickup_code' => 'LIVREUR-4821',
            'reception_code' => 'RECEPTION-7390',
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ], $overrides));
    }

    // ── Sérialisation par défaut ─────────────────────────────────────────────

    public function test_codes_are_never_serialised_by_default(): void
    {
        $payload = $this->order()->toArray();

        // La protection est portée par le modèle : tout endpoint renvoyant une
        // commande en bénéficie, y compris ceux qui n'existent pas encore.
        $this->assertArrayNotHasKey('pickup_code', $payload);
        $this->assertArrayNotHasKey('reception_code', $payload);
    }

    // ── Détail de commande ───────────────────────────────────────────────────

    public function test_the_driver_gets_no_code_from_the_order_endpoint(): void
    {
        $order = $this->order(overrides: ['driver_id' => $this->driver->id, 'status' => 'driver_assigned']);

        $response = $this->actingAs($this->driver)->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonMissingPath('data.pickup_code');
        $response->assertJsonMissingPath('data.reception_code');
        $response->assertDontSee('LIVREUR-4821');
        $response->assertDontSee('RECEPTION-7390');
    }

    public function test_the_supplier_sees_the_pickup_code_only(): void
    {
        $order = $this->order();

        $response = $this->actingAs($this->supplier)->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('data.pickup_code', 'LIVREUR-4821');
        $response->assertJsonMissingPath('data.reception_code');
    }

    public function test_the_client_sees_the_reception_code_on_a_delivery(): void
    {
        $order = $this->order();

        $response = $this->actingAs($this->client)->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('data.reception_code', 'RECEPTION-7390');
        $response->assertJsonMissingPath('data.pickup_code');
    }

    public function test_the_client_sees_the_pickup_code_when_collecting_in_store(): void
    {
        // Le client commande et vient lui-même retirer : c'est lui qui présente
        // le code au comptoir.
        $order = $this->order('pickup');

        $response = $this->actingAs($this->client)->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('data.pickup_code', 'LIVREUR-4821');
        $response->assertJsonMissingPath('data.reception_code');
    }

    // ── Suivi temps réel ─────────────────────────────────────────────────────

    public function test_the_tracking_endpoint_leaks_no_code_to_the_driver(): void
    {
        $order = $this->order(overrides: ['driver_id' => $this->driver->id, 'status' => 'driver_assigned']);

        $response = $this->actingAs($this->driver)->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk();
        $this->assertSame([], $response->json('data.codes'));
        $response->assertDontSee('LIVREUR-4821');
        $response->assertDontSee('RECEPTION-7390');
    }

    public function test_the_tracking_endpoint_gives_each_actor_its_own_code(): void
    {
        $order = $this->order(overrides: ['driver_id' => $this->driver->id, 'status' => 'driver_assigned']);

        $supplierView = $this->actingAs($this->supplier)
            ->getJson("/api/v1/orders/{$order->id}/tracking")->json('data.codes');
        $this->assertSame(['pickup_code' => 'LIVREUR-4821'], $supplierView);

        $clientView = $this->actingAs($this->client)
            ->getJson("/api/v1/orders/{$order->id}/tracking")->json('data.codes');
        $this->assertSame(['reception_code' => 'RECEPTION-7390'], $clientView);
    }

    // ── Backoffice ───────────────────────────────────────────────────────────

    public function test_the_backoffice_still_sees_both_codes(): void
    {
        $this->order();

        // Le masquage par defaut ne doit pas priver l'administration de la
        // tracabilite des livraisons ni de l'arbitrage des litiges : quatre
        // panneaux affichent ces codes.
        $orders = app(AdminService::class)->listOrders(null, null, null, 10);

        $payload = $orders->items()[0]->toArray();

        $this->assertSame('LIVREUR-4821', $payload['pickup_code']);
        $this->assertSame('RECEPTION-7390', $payload['reception_code']);
    }

    // ── Notifications ────────────────────────────────────────────────────────

    private function notificationsFor(User $user): string
    {
        return Notification::where('user_id', $user->id)->pluck('body')->implode(' | ');
    }

    public function test_the_driver_notifications_never_carry_a_code(): void
    {
        $order = $this->order(overrides: ['status' => 'searching_driver']);

        $this->actingAs($this->driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk();

        $order->refresh();

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => $order->pickup_code])
            ->assertOk();

        $driverMessages = $this->notificationsFor($this->driver);

        // Le livreur est guidé, mais doit demander les codes sur place.
        $this->assertStringNotContainsString((string) $order->pickup_code, $driverMessages);
        $this->assertStringNotContainsString((string) $order->reception_code, $driverMessages);
        $this->assertStringContainsString('Demandez le code', $driverMessages);
    }

    public function test_the_supplier_is_told_the_pickup_code_when_a_driver_comes(): void
    {
        $order = $this->order(overrides: ['status' => 'searching_driver']);

        $this->actingAs($this->driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk();

        // Sans cela, le fournisseur n'avait rien à quoi comparer le code que le
        // livreur saisissait : il ne contrôlait rien.
        $this->assertStringContainsString(
            (string) $order->refresh()->pickup_code,
            $this->notificationsFor($this->supplier)
        );
    }

    public function test_the_client_is_told_the_reception_code_at_pickup(): void
    {
        $order = $this->order(overrides: ['status' => 'searching_driver']);

        $this->actingAs($this->driver)->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();
        $order->refresh();
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => $order->pickup_code])
            ->assertOk();

        $this->assertStringContainsString(
            (string) $order->reception_code,
            $this->notificationsFor($this->client)
        );
    }

    public function test_store_pickup_tells_both_the_client_and_the_supplier(): void
    {
        $product = SupplierProduct::create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Ciment',
            'sku' => 'CIM-VIS',
            'unit_price' => 5000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($this->client)->postJson('/api/v1/orders', [
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'pickup',
            'items' => [['supplier_product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = Order::latest('id')->firstOrFail();

        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/prepared")
            ->assertOk();

        // Le client vient lui-même : il présente le code, le fournisseur le
        // vérifie. Les deux doivent donc le connaître.
        $this->assertStringContainsString($order->pickup_code, $this->notificationsFor($this->client));
        $this->assertStringContainsString($order->pickup_code, $this->notificationsFor($this->supplier));
    }
}
