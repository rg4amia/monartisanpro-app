<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parcours livreur de bout en bout, uniquement via l'API HTTP :
 * course disponible -> acceptation -> télémétrie GPS -> retrait chez le
 * fournisseur -> livraison chez le client -> libération des fonds ->
 * évaluation par le client.
 *
 * Chaque étape était testée isolément au mieux ; l'enchaînement complet et
 * les règles qui le protègent (course prise par un seul livreur, codes
 * obligatoires, ordre des étapes) ne l'étaient pas.
 */
class DriverFullJourneyTest extends TestCase
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

    private function searchingOrder(): Order
    {
        return Order::create([
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'searching_driver',
            'delivery_mode' => 'delivery',
            'subtotal' => 40000,
            'delivery_cost' => 5000,
            'platform_fee' => 1000,
            'total_amount' => 46000,
            'pickup_code' => 'RET-JOURNEY',
            'reception_code' => 'REC-JOURNEY',
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);
    }

    public function test_driver_completes_a_delivery_from_acceptance_to_evaluation(): void
    {
        $order = $this->searchingOrder();

        // ── 1. La course apparaît dans les courses disponibles ──
        $available = $this->actingAs($this->driver)->getJson('/api/v1/deliveries/available');
        $available->assertOk();
        $this->assertContains(
            $order->id,
            collect($available->json('data'))->pluck('id')->all(),
            'La course en recherche de livreur doit être proposée.'
        );

        // ── 2. Acceptation ──
        $this->actingAs($this->driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk();

        $order->refresh();
        $this->assertSame('driver_assigned', $order->status);
        $this->assertSame($this->driver->id, $order->driver_id);
        $this->assertNotNull($order->driver_assigned_at);

        // Elle ne doit plus être proposée aux autres livreurs.
        $stillAvailable = $this->actingAs($this->driver)->getJson('/api/v1/deliveries/available');
        $this->assertNotContains(
            $order->id,
            collect($stillAvailable->json('data'))->pluck('id')->all()
        );

        // ── 3. Télémétrie GPS en course ──
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/location", [
                'latitude' => 5.3460,
                'longitude' => -3.9850,
                'speed_kmh' => 32.5,
                'heading' => 120,
            ])
            ->assertOk();

        // ── 4. Retrait chez le fournisseur ──
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'RET-JOURNEY'])
            ->assertOk();

        $order->refresh();
        $this->assertSame('driver_picked_up', $order->status);

        // ── 5. Livraison chez le client ──
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'REC-JOURNEY'])
            ->assertOk();

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);

        // ── 6. Libération de la part course au livreur ──
        $payout = Transaction::where('user_id', $this->driver->id)
            ->where('wallet_dest', 'driver_wallet_'.$this->driver->id)
            ->first();

        $this->assertNotNull($payout, 'La part livraison doit être libérée au livreur.');

        // Le montant n'est pas figé dans le test : `assignDriver` recalcule le
        // coût de la course via le moteur tarifaire au moment de l'acceptation.
        // On vérifie donc l'invariant métier — le livreur touche la course
        // moins 10 % de commission plateforme — sur le coût réellement retenu.
        $expectedPayout = (int) $order->delivery_cost - (int) round($order->delivery_cost * 0.10);
        $this->assertSame($expectedPayout, (int) $payout->montant);
        $this->assertGreaterThan(0, $expectedPayout);

        // ── 7. Le livreur devient évaluable, puis est évalué ──
        $actors = $this->actingAs($this->client)
            ->getJson("/api/v1/orders/{$order->id}/evaluations-status");
        $actors->assertOk();
        $actors->assertJsonPath('data.is_delivered', true);

        $driverActor = collect($actors->json('data.actors'))->firstWhere('role', 'livreur');
        $this->assertNotNull($driverActor, 'Le livreur doit figurer parmi les acteurs à évaluer.');
        $this->assertFalse($driverActor['is_evaluated']);

        $this->actingAs($this->client)
            ->postJson('/api/v1/evaluations', [
                'order_id' => $order->id,
                'evalue_id' => $this->driver->id,
                'note' => 5,
                'commentaire' => 'Livraison rapide et soignée',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('evaluations', [
            'order_id' => $order->id,
            'evaluateur_id' => $this->client->id,
            'evalue_id' => $this->driver->id,
            'note' => 5,
        ]);

        // L'écran de notation doit refléter l'avis déposé.
        $after = $this->actingAs($this->client)
            ->getJson("/api/v1/orders/{$order->id}/evaluations-status");
        $driverAfter = collect($after->json('data.actors'))->firstWhere('role', 'livreur');
        $this->assertTrue($driverAfter['is_evaluated']);
        $this->assertSame(5, $driverAfter['evaluation']['note']);
    }

    public function test_a_course_cannot_be_accepted_twice(): void
    {
        $order = $this->searchingOrder();
        $otherDriver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        $this->actingAs($this->driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk();

        // Le second livreur arrive trop tard : la course ne doit pas changer de main.
        $this->actingAs($otherDriver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertStatus(400);

        $order->refresh();
        $this->assertSame($this->driver->id, $order->driver_id);
    }

    public function test_delivery_cannot_be_confirmed_before_pickup(): void
    {
        $order = $this->searchingOrder();

        $this->actingAs($this->driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk();

        // On saute l'étape de retrait : la livraison doit être refusée.
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'REC-JOURNEY'])
            ->assertStatus(400);

        $order->refresh();
        $this->assertSame('driver_assigned', $order->status);
        $this->assertDatabaseMissing('transactions', [
            'wallet_dest' => 'driver_wallet_'.$this->driver->id,
        ]);
    }

    public function test_wrong_codes_block_pickup_and_delivery(): void
    {
        $order = $this->searchingOrder();

        $this->actingAs($this->driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk();

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'RET-FAUX'])
            ->assertStatus(400);

        $order->refresh();
        $this->assertSame('driver_assigned', $order->status);

        // Retrait avec le bon code, puis réception avec un code erroné.
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'RET-JOURNEY'])
            ->assertOk();

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'REC-FAUX'])
            ->assertStatus(400);

        $order->refresh();
        $this->assertSame('driver_picked_up', $order->status);
    }

    public function test_a_client_cannot_take_a_delivery_job(): void
    {
        $order = $this->searchingOrder();

        $this->actingAs($this->client)
            ->getJson('/api/v1/deliveries/available')
            ->assertForbidden();

        // Le rôle est refusé en amont (403), la course n'est pas prise.
        $this->actingAs($this->client)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertForbidden();

        $order->refresh();
        $this->assertNull($order->driver_id);
    }

    public function test_client_cannot_evaluate_the_driver_twice(): void
    {
        $order = $this->searchingOrder();

        $this->actingAs($this->driver)->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();
        $this->actingAs($this->driver)->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => 'RET-JOURNEY'])->assertOk();
        $this->actingAs($this->driver)->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => 'REC-JOURNEY'])->assertOk();

        $payload = [
            'order_id' => $order->id,
            'evalue_id' => $this->driver->id,
            'note' => 4,
        ];

        $this->actingAs($this->client)->postJson('/api/v1/evaluations', $payload)->assertCreated();
        $this->actingAs($this->client)->postJson('/api/v1/evaluations', $payload)->assertStatus(422);

        $this->assertSame(
            1,
            Evaluation::where('order_id', $order->id)->where('evalue_id', $this->driver->id)->count()
        );
    }
}
