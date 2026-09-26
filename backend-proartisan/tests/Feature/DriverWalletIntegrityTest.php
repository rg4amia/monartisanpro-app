<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Models\Evaluation;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DeliveryPricingService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\Support\Geo;
use Tests\TestCase;

/**
 * Course livreur « à la Yango » et portefeuille du livreur (Chantier 10).
 *
 * - L'acceptation n'enregistre plus aucun paiement : l'ancienne transaction
 *   Wave « confirmée » ne correspondait à aucun débit du client.
 * - Le bonus d'attente s'accumule à part et s'ajoute au montant révélé à la
 *   livraison, que le client règle alors ; le livreur est crédité à la
 *   confirmation, une seule fois.
 * - Les gains en attente couvrent `driver_picked_up` et les courses livrées
 *   non encore réglées, nets de commission.
 * - Le tableau de bord n'invente aucune note (Règle d'or 29).
 */
class DriverWalletIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $supplier;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $this->supplier->fournisseurAgree()->create([
            'nom_boutique' => 'Quincaillerie Centrale',
            'position' => Geo::point(),
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);
        $this->driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif']);
    }

    private function pricingReturns(int $cost): void
    {
        $this->mock(DeliveryPricingService::class, function (MockInterface $mock) use ($cost) {
            $mock->shouldReceive('calculateOrderDeliveryCost')->andReturn($cost);
        });
    }

    private function order(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'searching_driver',
            'delivery_mode' => 'delivery',
            'subtotal' => 40000,
            'delivery_cost' => 0,
            'platform_fee' => 1200,
            'total_amount' => 41200,
            'pickup_code' => 'LIVREUR-'.random_int(1000, 9999),
            'reception_code' => 'RECEPTION-'.random_int(1000, 9999),
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ], $attributes));
    }

    private function deliveryCharges(Order $order): Collection
    {
        return Transaction::where('wallet_dest', 'escrow_order_'.$order->id)->get();
    }

    private function driverPayouts(): Collection
    {
        return Transaction::where('wallet_dest', 'driver_wallet_'.$this->driver->id)->get();
    }

    /** Acceptation, retrait, livraison : renvoie la réponse de livraison. */
    private function deliver(Order $order, int $waitingMinutes = 0): TestResponse
    {
        $this->actingAs($this->driver)->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();
        $this->actingAs($this->driver)->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => $order->pickup_code])->assertOk();

        if ($waitingMinutes > 0) {
            $this->actingAs($this->driver)
                ->postJson("/api/v1/orders/{$order->id}/waiting-surge", ['waiting_minutes' => $waitingMinutes])
                ->assertOk();
        }

        return $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => $order->reception_code])
            ->assertOk();
    }

    private function clientPays(Order $order): Transaction
    {
        $this->actingAs($this->client)
            ->postJson("/api/v1/payments/orders/{$order->id}/delivery-fare", ['provider' => 'wave', 'phone' => '+2250700000001'])
            ->assertOk();

        $payment = Transaction::where('type', 'paiement_livraison')->latest('id')->firstOrFail();
        app(PaymentService::class)->confirmSimulatedPayment($payment);

        return $payment;
    }

    public function test_acceptance_records_no_payment_and_only_estimates_the_fare(): void
    {
        $this->pricingReturns(3000);
        $order = $this->order();

        $this->actingAs($this->driver)
            ->postJson("/api/v1/deliveries/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.delivery_fare.is_estimate', true)
            ->assertJsonPath('data.delivery_fare.total', 3000);

        $this->assertCount(0, $this->deliveryCharges($order), 'Aucun paiement fictif ne doit être créé à l\'acceptation.');
        $this->assertSame(41200, (int) $order->fresh()->total_amount);
    }

    public function test_waiting_bonus_accumulates_without_payment(): void
    {
        $this->pricingReturns(3000);
        $order = $this->order(['status' => 'driver_assigned', 'driver_id' => $this->driver->id, 'delivery_cost' => 3000]);

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/waiting-surge", ['waiting_minutes' => 25])
            ->assertOk();

        $order->refresh();
        $this->assertSame(500, $order->waiting_fee);
        $this->assertSame(3000, $order->delivery_cost, 'Le bonus reste distinct du tarif de la course.');
        $this->assertCount(0, $this->deliveryCharges($order));
    }

    public function test_waiting_bonus_is_refused_once_delivered(): void
    {
        $order = $this->order(['status' => 'delivered', 'driver_id' => $this->driver->id, 'delivery_cost' => 3000]);

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/waiting-surge", ['waiting_minutes' => 10])
            ->assertStatus(400);

        $this->assertSame(0, $order->fresh()->waiting_fee);
    }

    public function test_delivery_reveals_the_final_fare_with_waiting_bonus(): void
    {
        $this->pricingReturns(3000);
        $order = $this->order();

        $response = $this->deliver($order, waitingMinutes: 25);

        $response->assertJsonPath('delivery_fare.base', 3000)
            ->assertJsonPath('delivery_fare.waiting_bonus', 500)
            ->assertJsonPath('delivery_fare.total', 3500)
            ->assertJsonPath('delivery_fare.status', 'a_payer')
            ->assertJsonPath('delivery_fare.due', 3500);

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame(41200 + 3500, (int) $order->total_amount);
        $this->assertCount(0, $this->driverPayouts(), 'Le livreur n\'est crédité qu\'au paiement du client.');
    }

    public function test_client_payment_credits_the_driver_once(): void
    {
        $this->pricingReturns(3000);
        $order = $this->order();
        $this->deliver($order, waitingMinutes: 25);

        $payment = $this->clientPays($order);

        $this->assertSame(3500, (int) $payment->montant);
        $this->assertSame('paye', $order->fresh()->delivery_fare_status);

        // 3 500 − 10 % de commission = 3 150.
        $this->assertSame([3150], $this->driverPayouts()->pluck('montant')->map(fn ($m) => (int) $m)->all());
        $this->assertSame(3150, $this->driver->fresh()->wallet_mo);

        // Webhook rejoué : aucun second crédit.
        app(PaymentService::class)->applyConfirmedPayment($payment->fresh());
        $this->assertCount(1, $this->driverPayouts());
        $this->assertSame(3150, $this->driver->fresh()->wallet_mo);
    }

    public function test_only_the_order_client_can_pay_the_fare(): void
    {
        $this->pricingReturns(3000);
        $order = $this->order();
        $this->deliver($order);

        $stranger = $this->makeUser('client');

        $this->actingAs($stranger)
            ->postJson("/api/v1/payments/orders/{$order->id}/delivery-fare", ['provider' => 'wave', 'phone' => '+2250700000002'])
            ->assertForbidden();

        $this->actingAs($this->driver)
            ->postJson("/api/v1/payments/orders/{$order->id}/delivery-fare", ['provider' => 'wave', 'phone' => '+2250700000002'])
            ->assertForbidden();
    }

    public function test_a_payment_of_another_amount_does_not_settle_the_fare(): void
    {
        $this->pricingReturns(3000);
        $order = $this->order();
        $this->deliver($order);

        $forged = Transaction::create([
            'user_id' => $this->client->id,
            'type' => 'paiement_livraison',
            'montant' => 100,
            'wallet_source' => 'client_mobile_money_'.$this->client->id,
            'wallet_dest' => 'escrow_order_'.$order->id,
            'provider' => 'wave',
            'statut' => 'confirme',
            'metadata' => ['order_id' => $order->id, 'payment_type' => 'delivery_fare'],
        ]);

        $this->assertFalse(app(OrderService::class)->settleDeliveryFare($order, $forged));
        $this->assertSame('a_payer', $order->fresh()->delivery_fare_status);
        $this->assertCount(0, $this->driverPayouts());
    }

    public function test_a_legacy_prepaid_fare_is_settled_at_delivery(): void
    {
        // Course facturée à l'acceptation sous l'ancien modèle.
        $this->pricingReturns(2500);
        $order = $this->order([
            'status' => 'driver_picked_up',
            'driver_id' => $this->driver->id,
            'delivery_cost' => 3000,
            'delivery_fare_prepaid' => 3000,
        ]);

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => $order->reception_code])
            ->assertOk()
            ->assertJsonPath('delivery_fare.status', 'paye')
            ->assertJsonPath('delivery_fare.due', 0);

        // Le client ne paie jamais moins que ce qu'il a déjà réglé : 3 000 − 10 %.
        $this->assertSame(2700, $this->driver->fresh()->wallet_mo);
    }

    public function test_multi_store_checkout_no_longer_prepays_the_delivery(): void
    {
        $this->mock(DeliveryPricingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('estimateFare')->andReturn(['delivery_cost' => 2000]);
        });

        $product = SupplierProduct::create([
            'supplier_id' => $this->supplier->id,
            'name' => 'Ciment 50 kg',
            'unit_price' => 5000,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $address = $this->client->addresses()->create([
            'label' => 'Chantier',
            'recipient_name' => 'Client',
            'recipient_phone' => '+2250700000003',
            'address_line' => 'Cocody',
            'city' => 'Abidjan',
            'is_default' => true,
        ]);

        $result = app(OrderService::class)->createMultiSupplierOrders($this->client, [[
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'delivery',
            'items' => [['supplier_product_id' => $product->id, 'quantity' => 2]],
        ]], null, $address);

        $order = $result['orders'][0];
        $this->assertSame(2000, (int) $order->delivery_cost, 'La course reste estimée sur la commande.');
        $this->assertSame((int) $order->subtotal + (int) $order->platform_fee, (int) $order->total_amount);
        // Le paiement du panier (Chantier 11) porte exactement ce total, sans la course.
        app(PaymentService::class)->initiateOrderPayment($this->client, $order, PaymentProvider::WAVE, '+2250700000003');
        $this->assertSame((int) $result['total_amount'], (int) Transaction::where('wallet_dest', 'like', 'escrow_group_%')->value('montant'));
    }

    public function test_pending_earnings_are_net_and_cover_transit_and_unpaid_fares(): void
    {
        $this->order(['driver_id' => $this->driver->id, 'status' => 'driver_assigned', 'delivery_cost' => 2000]);
        $this->order(['driver_id' => $this->driver->id, 'status' => 'driver_picked_up', 'delivery_cost' => 2500, 'waiting_fee' => 500]);
        $this->order(['driver_id' => $this->driver->id, 'status' => 'delivered', 'delivery_cost' => 4000, 'delivery_fare_status' => 'a_payer']);
        $this->order(['driver_id' => $this->driver->id, 'status' => 'delivered', 'delivery_cost' => 9000, 'delivery_fare_status' => 'paye']);

        // En course : 5 000 bruts → 4 500 nets. Livrées non réglées : 4 000 → 3 600.
        $this->actingAs($this->driver)
            ->getJson('/api/v1/wallets/balance')
            ->assertOk()
            ->assertJsonPath('data.wallet_escrow_livreur', 4500 + 3600);

        $this->actingAs($this->driver)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.pending_deliveries', 2)
            ->assertJsonPath('data.pending_earnings', 4500)
            ->assertJsonPath('data.awaiting_payment_earnings', 3600);
    }

    public function test_an_unrated_driver_has_no_rating(): void
    {
        $response = $this->actingAs($this->driver)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertNull($response->json('data.rating'));
        $this->assertSame(0, $response->json('data.ratings_count'));
        $this->assertSame(0, $response->json('data.score_prosartisan'));
    }

    public function test_rating_summary_reflects_received_evaluations(): void
    {
        foreach ([5, 5, 4, 3] as $note) {
            Evaluation::create([
                'order_id' => $this->order(['driver_id' => $this->driver->id, 'status' => 'delivered'])->id,
                'evaluateur_id' => $this->client->id,
                'evalue_id' => $this->driver->id,
                'note' => $note,
            ]);
        }

        $response = $this->actingAs($this->driver)->getJson('/api/v1/dashboard')->assertOk();

        $this->assertEquals(4.3, $response->json('data.rating'));
        $this->assertSame(4, $response->json('data.ratings_count'));
        $this->assertEquals(0.5, $response->json('data.ratings_distribution.5'));
        $this->assertEquals(0.25, $response->json('data.ratings_distribution.3'));
        $this->assertEquals(0, $response->json('data.ratings_distribution.1'));
    }
}
