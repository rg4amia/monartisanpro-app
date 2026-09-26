<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DeliveryFareCollectionService;
use App\Services\DeliveryPricingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Support\Geo;
use Tests\TestCase;

/**
 * Recouvrement des courses livrées impayées (Chantier 11) : relances push +
 * SMS, restriction du client au-delà du plafond (paiement présumé hors
 * plateforme), levée automatique au paiement.
 */
class DeliveryFareCollectionTest extends TestCase
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

        $this->partialMock(DeliveryPricingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('calculateOrderDeliveryCost')->andReturn(3000);
        });
    }

    /** Commande livrée dont la course (3 000 FCFA) reste à régler. */
    private function deliveredUnpaidOrder(): Order
    {
        $order = Order::create([
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

        $this->actingAs($this->driver)->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();
        $this->actingAs($this->driver)->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => $order->pickup_code])->assertOk();
        $this->actingAs($this->driver)->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => $order->reception_code])->assertOk();

        $order->refresh();
        $this->assertSame('a_payer', $order->delivery_fare_status);

        return $order;
    }

    private function reminders(): int
    {
        return Notification::where('user_id', $this->client->id)->where('title', 'like', 'Course à régler — rappel%')->count();
    }

    public function test_the_client_is_reminded_at_each_interval_only(): void
    {
        $order = $this->deliveredUnpaidOrder();
        $collection = app(DeliveryFareCollectionService::class);

        $collection->processDue();
        $this->assertSame(0, $this->reminders(), 'Pas de rappel avant le premier intervalle.');

        $this->travel(25)->hours();
        $this->artisan('prosartisan:remind-unpaid-delivery-fares')->assertSuccessful();
        $this->assertSame(1, $this->reminders());
        $this->assertSame(1, $order->fresh()->delivery_fare_reminders_count);

        // Même heure : pas de doublon.
        $collection->processDue();
        $this->assertSame(1, $this->reminders());
    }

    public function test_five_unanswered_reminders_restrict_the_client_who_can_still_pay(): void
    {
        $order = $this->deliveredUnpaidOrder();
        $collection = app(DeliveryFareCollectionService::class);

        for ($i = 0; $i < 5; $i++) {
            $this->travel(25)->hours();
            $collection->processDue();
        }
        $this->assertSame(5, $this->reminders());
        $this->assertFalse($this->client->fresh()->isPaymentRestricted(), 'Cinq rappels, pas encore de restriction.');

        $this->travel(25)->hours();
        $collection->processDue();

        $client = $this->client->fresh();
        $this->assertTrue($client->isPaymentRestricted());
        $this->assertStringContainsString("conditions d'utilisation", $client->payment_restriction_reason);
        $this->assertTrue(AdminActivityLog::where('action', 'client.payment_restricted')->exists());

        // Plus de commande ni de mission…
        $this->actingAs($client)->postJson('/api/v1/orders', [
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'pickup',
            'items' => [['supplier_product_id' => 1, 'quantity' => 1]],
        ])->assertForbidden()->assertJsonPath('payment_restricted', true)->assertJsonPath('unpaid_order_ids', [$order->id]);

        $this->actingAs($client)->postJson('/api/v1/missions', [])->assertForbidden();

        // … mais le paiement de la course reste possible, et il lève la restriction.
        $this->actingAs($client)
            ->postJson("/api/v1/payments/orders/{$order->id}/delivery-fare", ['provider' => 'wave', 'phone' => '+2250700000001'])
            ->assertOk();
        app(PaymentService::class)->confirmSimulatedPayment(Transaction::where('type', 'paiement_livraison')->latest('id')->firstOrFail());

        $this->assertFalse($this->client->fresh()->isPaymentRestricted());
        $this->assertTrue(Notification::where('user_id', $this->client->id)->where('title', 'Restriction levée')->exists());
    }

    public function test_an_admin_can_remind_and_lift_a_restriction_with_audit(): void
    {
        $order = $this->deliveredUnpaidOrder();
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $this->actingAs($admin)->post("/admin/collections/orders/{$order->id}/remind")->assertRedirect();
        $this->assertSame(1, $this->reminders());
        $this->assertTrue(AdminActivityLog::where('action', 'delivery_fare.reminded')->exists());

        $this->client->update(['payment_restricted_at' => now(), 'payment_restriction_reason' => 'Test']);

        $this->actingAs($admin)->post("/admin/collections/users/{$this->client->id}/lift-restriction", ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/collections/users/{$this->client->id}/lift-restriction", ['reason' => 'Règlement constaté en agence'])
            ->assertRedirect();

        $this->assertFalse($this->client->fresh()->isPaymentRestricted());
        $this->assertTrue(AdminActivityLog::where('action', 'client.payment_restriction_lifted')->exists());

        // Un non-admin n'accède pas à ces actions.
        $this->actingAs($this->client)->post("/admin/collections/orders/{$order->id}/remind")->assertForbidden();
    }
}
