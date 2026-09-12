<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation à deux voix : la contrepartie peut confirmer depuis son propre
 * appareil, et la même validation peut arriver deux fois.
 *
 * C'est ce qui rend possible la bascule automatique hors connexion : quand le
 * livreur n'a pas de réseau, sa validation part en file d'attente pendant que
 * le fournisseur ou le client confirme immédiatement. Le rejeu tardif doit
 * alors être sans effet — surtout pas un second versement.
 */
class OrderValidationIdempotencyTest extends TestCase
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

    private function order(string $status, string $mode = 'delivery'): Order
    {
        return Order::create([
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'driver_id' => $mode === 'delivery' ? $this->driver->id : null,
            'status' => $status,
            'delivery_mode' => $mode,
            'subtotal' => 40000,
            'delivery_cost' => 5000,
            'platform_fee' => 1000,
            'total_amount' => 46000,
            'pickup_code' => 'LIVREUR-4821',
            'reception_code' => 'RECEPTION-7390',
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);
    }

    private function supplierPayouts(Order $order): int
    {
        return Transaction::where('wallet_dest', 'supplier_wallet_'.$this->supplier->id)->count();
    }

    // ── Idempotence ──────────────────────────────────────────────────────────

    public function test_a_replayed_pickup_does_not_pay_the_supplier_twice(): void
    {
        $order = $this->order('driver_assigned');

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'LIVREUR-4821'])
            ->assertOk();

        $payoutsAfterFirst = $this->supplierPayouts($order);
        $this->assertGreaterThan(0, $payoutsAfterFirst);

        // Rejeu de la requête mise en file hors connexion.
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'LIVREUR-4821'])
            ->assertOk();

        $this->assertSame('driver_picked_up', $order->fresh()->status);
        $this->assertSame($payoutsAfterFirst, $this->supplierPayouts($order));
    }

    public function test_a_replayed_delivery_does_not_pay_the_driver_twice(): void
    {
        $order = $this->order('driver_picked_up');

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'RECEPTION-7390'])
            ->assertOk();

        $payouts = Transaction::where('wallet_dest', 'driver_wallet_'.$this->driver->id)->count();
        $this->assertGreaterThan(0, $payouts);

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'RECEPTION-7390'])
            ->assertOk();

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(
            $payouts,
            Transaction::where('wallet_dest', 'driver_wallet_'.$this->driver->id)->count()
        );
    }

    public function test_a_replay_after_the_counterparty_validated_is_accepted(): void
    {
        $order = $this->order('driver_picked_up');

        // Le client, qui a du réseau, confirme la réception le premier.
        $this->actingAs($this->client)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'RECEPTION-7390'])
            ->assertOk();

        $this->assertSame('delivered', $order->fresh()->status);

        // Le livreur retrouve du réseau : sa requête rejouée ne doit pas
        // échouer, sinon la file la considérerait perdue.
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'RECEPTION-7390'])
            ->assertOk();
    }

    public function test_an_idempotent_replay_still_requires_the_right_code(): void
    {
        $order = $this->order('driver_picked_up');

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'RECEPTION-7390'])
            ->assertOk();

        // L'idempotence ne doit pas devenir un laissez-passer : un mauvais code
        // reste refusé, même sur une commande déjà livrée.
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'REC-0000'])
            ->assertStatus(400);
    }

    // ── Validation par la contrepartie ───────────────────────────────────────

    public function test_the_supplier_can_confirm_the_handover_to_the_driver(): void
    {
        $order = $this->order('driver_assigned');

        // Chemin utilisé quand le livreur n'a pas de réseau au comptoir.
        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'LIVREUR-4821'])
            ->assertOk();

        $this->assertSame('driver_picked_up', $order->fresh()->status);
    }

    public function test_the_client_can_confirm_receipt(): void
    {
        $order = $this->order('driver_picked_up');

        $this->actingAs($this->client)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'RECEPTION-7390'])
            ->assertOk();

        $this->assertSame('delivered', $order->fresh()->status);
    }

    // ── Ce que la validation à deux voix n'ouvre pas ─────────────────────────

    public function test_an_unrelated_user_cannot_validate_anything(): void
    {
        $order = $this->order('driver_assigned');
        $intruder = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        // Le contrôle d'acteur manquait sur cet endpoint : connaître le code
        // suffisait à déclencher le versement au fournisseur.
        $this->actingAs($intruder)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'LIVREUR-4821'])
            ->assertStatus(403);

        $this->assertSame('driver_assigned', $order->fresh()->status);
        $this->assertSame(0, $this->supplierPayouts($order));
    }

    public function test_the_supplier_cannot_confirm_the_delivery_to_the_client(): void
    {
        $order = $this->order('driver_picked_up');

        // Le fournisseur n'est pas présent à la livraison : il n'a rien à
        // attester à ce stade.
        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'RECEPTION-7390'])
            ->assertStatus(403);

        $this->assertSame('driver_picked_up', $order->fresh()->status);
    }

    public function test_the_client_cannot_validate_a_driver_pickup(): void
    {
        $order = $this->order('driver_assigned');

        // En mode livraison le client est chez lui : il ne peut pas attester
        // d'une remise au comptoir.
        $this->actingAs($this->client)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'LIVREUR-4821'])
            ->assertStatus(403);

        $this->assertSame('driver_assigned', $order->fresh()->status);
    }

    public function test_store_pickup_can_be_validated_by_the_client_or_the_supplier(): void
    {
        $order = $this->order('prepared', 'pickup');

        $this->actingAs($this->supplier)
            ->postJson("/api/v1/orders/{$order->id}/verify-pickup", ['code' => 'LIVREUR-4821'])
            ->assertOk();

        $this->assertSame('delivered', $order->fresh()->status);

        // Et le rejeu du client reste accepté.
        $this->actingAs($this->client)
            ->postJson("/api/v1/orders/{$order->id}/verify-pickup", ['code' => 'LIVREUR-4821'])
            ->assertOk();
    }
}
