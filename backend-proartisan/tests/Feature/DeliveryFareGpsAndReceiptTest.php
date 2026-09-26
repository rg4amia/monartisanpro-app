<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\DeliveryTracking;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DeliveryPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Support\Geo;
use Tests\TestCase;

/**
 * Course au trajet GPS réel et reçus PDF (Chantier 11).
 *
 * Auparavant, le montant final était recalculé sur l'itinéraire théorique
 * au moment de la livraison, quelle que soit la route réellement suivie ;
 * et aucun reçu n'était accessible depuis l'application.
 */
class DeliveryFareGpsAndReceiptTest extends TestCase
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

    private function routedFareIs(int $cost): void
    {
        // Seul l'itinéraire théorique est simulé : le tarif du trajet réel
        // (`fareFromTrip`) reste celui du moteur.
        $this->partialMock(DeliveryPricingService::class, function (MockInterface $mock) use ($cost) {
            $mock->shouldReceive('calculateOrderDeliveryCost')->andReturn($cost);
        });
    }

    private function order(): Order
    {
        return Order::create([
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
        ]);
    }

    /**
     * Retrait, trajet de 20 minutes relevé par le GPS, livraison.
     *
     * @param  list<array{0: float, 1: float}>  $trace  points (lat, lng), un toutes les ~3 minutes
     */
    private function deliverAlong(Order $order, array $trace): Order
    {
        $this->actingAs($this->driver)->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();
        $this->actingAs($this->driver)->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => $order->pickup_code])->assertOk();

        $start = now();
        foreach ($trace as $index => [$lat, $lng]) {
            DeliveryTracking::create([
                'order_id' => $order->id,
                'driver_id' => $this->driver->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'created_at' => $start->copy()->addSeconds(($index + 1) * 170),
            ]);
        }

        $this->travel(20)->minutes();

        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => $order->reception_code])
            ->assertOk();

        return $order->fresh();
    }

    /** Trajet rectiligne d'environ 6 km vers le nord (7 relevés). */
    private function sixKilometres(): array
    {
        return array_map(fn (int $i) => [5.30 + 0.009 * $i, -4.00], range(0, 6));
    }

    public function test_the_final_fare_follows_the_real_gps_trip(): void
    {
        $this->routedFareIs(3000);
        $order = $this->deliverAlong($this->order(), $this->sixKilometres());

        $this->assertSame('gps', $order->fare_source);
        $this->assertEqualsWithDelta(6.0, $order->actual_distance_km, 0.1);
        $this->assertEqualsWithDelta(20.0, $order->actual_duration_min, 0.5);

        $expected = app(DeliveryPricingService::class)->fareFromTrip($order->actual_distance_km, $order->actual_duration_min, 'moto', 1.0);
        $this->assertSame($expected, (int) $order->delivery_cost);
        $this->assertNotSame(3000, (int) $order->delivery_cost, "Le tarif n'est plus celui de l'itinéraire théorique.");
        $this->assertSame('Calculée sur le trajet réel', $order->delivery_fare['fare_source_label']);
    }

    public function test_an_abnormally_long_trip_is_capped(): void
    {
        $this->routedFareIs(1000);
        $order = $this->deliverAlong($this->order(), $this->sixKilometres());

        $this->assertSame('gps_plafonne', $order->fare_source);
        $this->assertSame(1500, (int) $order->delivery_cost, 'Plafond : 1,5 × l\'itinéraire.');
    }

    public function test_an_insufficient_trace_falls_back_to_the_route(): void
    {
        $this->routedFareIs(3000);
        $order = $this->deliverAlong($this->order(), [[5.30, -4.00], [5.309, -4.00]]);

        $this->assertSame('estimation', $order->fare_source);
        $this->assertSame(3000, (int) $order->delivery_cost);
    }

    public function test_an_aberrant_gps_jump_is_ignored(): void
    {
        $this->routedFareIs(3000);
        $trace = $this->sixKilometres();
        array_splice($trace, 3, 0, [[5.80, -4.00]]); // saut de ~50 km en 3 minutes

        $order = $this->deliverAlong($this->order(), $trace);

        $this->assertSame('gps', $order->fare_source);
        $this->assertEqualsWithDelta(6.0, $order->actual_distance_km, 0.1);
    }

    private function confirmedPayment(User $owner, string $type = 'paiement_livraison'): Transaction
    {
        return Transaction::create([
            'user_id' => $owner->id,
            'type' => $type,
            'montant' => 3000,
            'wallet_source' => 'client_mobile_money_'.$owner->id,
            'wallet_dest' => 'escrow_order_1',
            'provider' => PaymentProvider::WAVE,
            'statut' => PaymentStatus::CONFIRME,
            'paid_at' => now(),
            'metadata' => ['order_id' => 1, 'payment_type' => 'delivery_fare'],
        ]);
    }

    public function test_the_owner_gets_a_signed_receipt_link_and_the_pdf(): void
    {
        $payment = $this->confirmedPayment($this->client);

        $url = $this->actingAs($this->client)
            ->getJson("/api/v1/transactions/{$payment->id}/receipt-link")
            ->assertOk()
            ->json('data.url');

        $this->assertStringContainsString('signature=', $url);

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_receipt_is_neither_given_to_a_third_party_nor_before_payment(): void
    {
        $payment = $this->confirmedPayment($this->client);

        $this->actingAs($this->driver)->getJson("/api/v1/transactions/{$payment->id}/receipt-link")->assertForbidden();

        $payment->update(['statut' => PaymentStatus::EN_ATTENTE]);
        $this->actingAs($this->client)->getJson("/api/v1/transactions/{$payment->id}/receipt-link")->assertStatus(422);

        // Sans signature, le fichier n'est pas servi.
        $this->get("/receipts/transactions/{$payment->id}")->assertForbidden();
    }

    public function test_the_history_flags_downloadable_receipts(): void
    {
        $this->confirmedPayment($this->client);

        $this->actingAs($this->client)
            ->getJson('/api/v1/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.receiptAvailable', true);
    }
}
