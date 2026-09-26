<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\AdminActivityLog;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\SupplierProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankTransferSettingsService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\WaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Support\Geo;
use Tests\TestCase;

/**
 * Encaissement réel des commandes de matériaux (Chantier 11).
 *
 * Auparavant, la commande était créée « payée » avec une transaction Wave
 * confirmée que rien n'avait encaissée : la quincaillerie préparait des
 * matériaux jamais réglés.
 */
class OrderPaymentCollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $supplier;

    private SupplierProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif', 'phone' => '+2250700000001']);
        $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $this->supplier->fournisseurAgree()->create([
            'nom_boutique' => 'Quincaillerie Centrale',
            'position' => Geo::point(),
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);
        $this->product = SupplierProduct::create([
            'supplier_id' => $this->supplier->id,
            'sku' => 'CIM-C11',
            'name' => 'Ciment CPA 45',
            'unit_price' => 5000,
            'stock_quantity' => 10,
        ]);
    }

    private function placeOrder(int $quantity = 2, array $extra = []): Order
    {
        $this->actingAs($this->client)->postJson('/api/v1/orders', array_merge([
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'pickup',
            'items' => [['supplier_product_id' => $this->product->id, 'quantity' => $quantity]],
        ], $extra))->assertCreated();

        return Order::latest('id')->firstOrFail();
    }

    private function openPayment(Order $order, string $provider = 'wave'): Transaction
    {
        $this->actingAs($this->client)
            ->postJson("/api/v1/payments/orders/{$order->id}/checkout", ['provider' => $provider, 'phone' => '+2250700000001'])
            ->assertOk();

        return Transaction::where('type', 'acompte')->latest('id')->firstOrFail();
    }

    public function test_a_new_order_awaits_a_real_payment(): void
    {
        $order = $this->placeOrder();

        $this->assertSame('pending', $order->status);
        $this->assertNotNull($order->payment_expires_at);
        $this->assertSame(8, (int) $this->product->fresh()->stock_quantity, 'Le stock est réservé.');

        // Rien n'est encaissé, et la quincaillerie n'est pas encore prévenue.
        $this->assertSame(0, Transaction::where('statut', PaymentStatus::CONFIRME)->count());
        $this->assertFalse(Notification::where('user_id', $this->supplier->id)->exists());

        // Elle ne peut pas préparer une commande impayée.
        $this->actingAs($this->supplier)->postJson("/api/v1/orders/{$order->id}/prepared")->assertStatus(400);
    }

    public function test_confirmation_pays_the_order_once_and_notifies_the_supplier(): void
    {
        $order = $this->placeOrder();
        $payment = $this->openPayment($order);

        $this->assertSame((int) $order->total_amount, (int) $payment->montant, 'Montant fixé par le serveur.');

        $payments = app(PaymentService::class);
        $payments->confirmSimulatedPayment($payment);
        $payments->applyConfirmedPayment($payment->fresh());

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(1, Notification::where('user_id', $this->supplier->id)->where('title', 'Nouvelle commande reçue')->count());
    }

    public function test_only_the_client_can_pay_and_a_mismatched_amount_confirms_nothing(): void
    {
        $order = $this->placeOrder();

        $intruder = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->actingAs($intruder)
            ->postJson("/api/v1/payments/orders/{$order->id}/checkout", ['provider' => 'wave', 'phone' => '+2250700000009'])
            ->assertForbidden();

        $forged = Transaction::create([
            'user_id' => $this->client->id,
            'type' => 'acompte',
            'montant' => 100,
            'wallet_source' => 'client_mobile_money_'.$this->client->id,
            'wallet_dest' => 'escrow_order_'.$order->id,
            'provider' => PaymentProvider::WAVE,
            'statut' => PaymentStatus::CONFIRME,
            'paid_at' => now(),
            'metadata' => ['payment_type' => 'order', 'order_ids' => [$order->id]],
        ]);
        app(PaymentService::class)->applyConfirmedPayment($forged);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_expired_order_is_cancelled_with_stock_and_promo_restored(): void
    {
        PromoCode::create([
            'code' => 'CIMENT10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
            'used_count' => 0,
        ]);

        $order = $this->placeOrder(2, ['promo_code' => 'CIMENT10']);
        $payment = $this->openPayment($order);
        $this->assertSame(1, (int) PromoCode::where('code', 'CIMENT10')->value('used_count'));

        // L'opérateur n'a rien encaissé.
        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('checkPaymentStatus')->andReturn(['status' => 'pending', 'payment_id' => null]);
        });

        $this->travel(31)->minutes();
        $this->artisan('prosartisan:expire-unpaid-orders')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, (int) $this->product->fresh()->stock_quantity);
        $this->assertSame(0, (int) PromoCode::where('code', 'CIMENT10')->value('used_count'));
        $this->assertSame('echoue', $payment->fresh()->statut->value);
    }

    public function test_expiry_encashes_a_payment_the_webhook_never_reported(): void
    {
        $order = $this->placeOrder();
        $this->openPayment($order);

        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('checkPaymentStatus')->andReturn(['status' => 'completed', 'payment_id' => 'WAVE-LATE']);
        });

        $this->travel(31)->minutes();
        app(OrderService::class)->expireUnpaidOrders();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(8, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_a_late_payment_revives_the_order_or_flags_a_refund(): void
    {
        $order = $this->placeOrder(2);
        $payment = $this->openPayment($order);

        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('checkPaymentStatus')->andReturn(['status' => 'pending', 'payment_id' => null]);
        });
        $this->travel(31)->minutes();
        app(OrderService::class)->expireUnpaidOrders();
        $this->assertSame('cancelled', $order->fresh()->status);

        // Stock encore disponible : le paiement tardif réactive la commande.
        $payment->update(['statut' => PaymentStatus::CONFIRME, 'paid_at' => now()]);
        app(PaymentService::class)->applyConfirmedPayment($payment->fresh());
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(8, (int) $this->product->fresh()->stock_quantity);

        // Second cas : le stock a été vendu entre-temps.
        $this->travelBack();
        $other = $this->placeOrder(2);
        $late = $this->openPayment($other);
        $this->travel(31)->minutes();
        app(OrderService::class)->expireUnpaidOrders();
        $this->product->update(['stock_quantity' => 0]);

        $late->update(['statut' => PaymentStatus::CONFIRME, 'paid_at' => now()]);
        app(PaymentService::class)->applyConfirmedPayment($late->fresh());

        $this->assertSame('cancelled', $other->fresh()->status);
        $this->assertTrue((bool) ($late->fresh()->metadata['refund_required'] ?? false), 'Le remboursement est signalé à l\'admin.');
    }

    public function test_a_bank_transfer_is_confirmed_by_an_admin_and_audited(): void
    {
        $this->mock(BankTransferSettingsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('details')->andReturn(['bank_name' => 'SGCI', 'account_name' => 'ProsArtisan', 'iban' => 'CI93CI0080111301134291200589']);
        });

        $order = $this->placeOrder();
        $payment = $this->openPayment($order, 'virement_bancaire');

        $this->assertTrue($order->fresh()->payment_expires_at->gt(now()->addHours(70)), 'Un virement dispose de 72 h.');

        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $this->actingAs($admin)
            ->post("/admin/collections/transactions/{$payment->id}/confirm-bank-transfer", ['bank_reference' => 'VIR-SGCI-2291'])
            ->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertTrue(AdminActivityLog::where('action', 'order.bank_transfer_confirmed')->exists());
    }

    public function test_an_orange_money_payment_keeps_the_order_reference(): void
    {
        $order = $this->placeOrder();
        $payment = $this->openPayment($order, 'orange_money');

        $this->assertSame($order->id, (int) $payment->metadata['order_id'], "La référence Orange Money n'écrase plus l'identifiant de la commande.");
        $this->assertNotEmpty($payment->metadata['orange_order_reference']);
    }
}
