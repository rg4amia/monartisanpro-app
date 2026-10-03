<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\WalletType;
use App\Models\AdminActivityLog;
use App\Models\FournisseurAgree;
use App\Models\MobileMoneyPayout;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderDisputeDebt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OrangeMoneyService;
use App\Services\OrderDisputeDebtService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SupplierCashoutService;
use App\Services\WalletService;
use App\Services\WaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Support\Geo;
use Tests\TestCase;

/**
 * Chantier 22 — effet financier d'un litige de commande : gel à l'ouverture,
 * remboursement du client à la clôture, dette du responsable, recouvrement et
 * blocage de son profil.
 *
 * Commande type : 20 000 FCFA d'articles, commission fournisseur 5 % (le
 * fournisseur a reçu 19 000), course 1 500 FCFA (le livreur reçoit 1 350).
 */
class Chantier22OrderDisputeRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $supplier;

    private User $livreur;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif', 'payment_phone' => '+2250700000010']);
        $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $this->livreur = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        // Compte financier de ProsArtisan : il avance les remboursements.
        app(WalletService::class)->credit($this->admin, WalletType::WALLET_MO, 500000, 'Commissions encaissées');

        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferToMobileMoney')->andReturn(['id' => 'WAVE-C22'])->byDefault();
            $mock->shouldReceive('createCheckout')->andReturn([
                'checkout_id' => 'CHK-C22', 'checkout_url' => 'https://pay.test/c22', 'wave_launch_url' => 'wave://c22',
            ])->byDefault();
        });
        $this->mock(OrangeMoneyService::class);
    }

    /**
     * Commande livrée, fournisseur déjà crédité de son prix net.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function deliveredOrder(array $attributes = [], bool $creditSupplier = true): Order
    {
        $order = Order::create(array_merge([
            'client_id' => $this->client->id, 'supplier_id' => $this->supplier->id, 'driver_id' => $this->livreur->id,
            'delivery_mode' => 'delivery', 'status' => 'delivered', 'delivered_at' => now(),
            'subtotal' => 20000, 'platform_fee' => 600, 'delivery_cost' => 1500, 'total_amount' => 22100,
            'pickup_code' => '4821', 'reception_code' => '7734',
            'delivery_fare_status' => 'paye',
        ], $attributes));

        Transaction::create([
            'user_id' => $this->supplier->id, 'type' => 'paiement_fournisseur', 'montant' => 19000,
            'wallet_source' => 'escrow_order_'.$order->id, 'wallet_dest' => 'supplier_wallet_'.$this->supplier->id,
            'provider' => 'wave', 'statut' => 'confirme', 'reference_externe' => 'TXN-C22-'.$order->id,
            'metadata' => ['order_id' => $order->id, 'supplier_commission' => 1000],
        ]);

        if ($creditSupplier) {
            app(WalletService::class)->credit($this->supplier, WalletType::WALLET_MATERIAUX, 19000, "Vente commande #{$order->id}");
        }

        return $order;
    }

    private function dispute(Order $order): OrderDispute
    {
        app(OrderService::class)->openOrderDispute($order, $this->client, 'Colis incomplet à la réception');

        return OrderDispute::where('order_id', $order->id)->sole();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolve(Order $order, array $payload)
    {
        return $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/dispute/resolve", $payload + [
            'outcome' => 'reclamation_acceptee', 'note' => 'Sacs manquants confirmés par la photo.',
        ]);
    }

    private function wallet(User $user, WalletType $type): int
    {
        return (int) $user->fresh()->getWalletBalance($type);
    }

    // ── Gel ──────────────────────────────────────────────────────────────

    public function test_l_ouverture_du_litige_gele_le_montant_de_la_commande_chez_le_fournisseur(): void
    {
        app(WalletService::class)->credit($this->supplier, WalletType::WALLET_MATERIAUX, 5000, 'Autre vente');
        $order = $this->deliveredOrder();

        $this->assertSame(24000, app(SupplierCashoutService::class)->getAvailableBalance($this->supplier));

        $dispute = $this->dispute($order);

        $this->assertSame(19000, $dispute->frozen_amount);
        $this->assertSame($this->supplier->id, (int) $dispute->frozen_user_id);
        // Le reste du solde demeure retirable ; le portefeuille lui-même n'est pas débité.
        $this->assertSame(5000, app(SupplierCashoutService::class)->getAvailableBalance($this->supplier->fresh()));
        $this->assertSame(24000, $this->wallet($this->supplier, WalletType::WALLET_MATERIAUX));
    }

    public function test_une_reclamation_rejetee_leve_le_gel_sans_rien_rembourser(): void
    {
        $order = $this->deliveredOrder();
        $this->dispute($order);

        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/dispute/resolve", [
            'outcome' => 'reclamation_rejetee', 'note' => 'Livraison conforme à la photo.',
        ])->assertSessionHas('success');

        $this->assertSame(19000, app(SupplierCashoutService::class)->getAvailableBalance($this->supplier->fresh()));
        $this->assertSame(0, MobileMoneyPayout::count());
        $this->assertSame(0, OrderDisputeDebt::count());
    }

    // ── Remboursement ────────────────────────────────────────────────────

    public function test_un_remboursement_partiel_est_verse_au_client_et_supporte_par_le_fournisseur(): void
    {
        $order = $this->deliveredOrder();
        $this->dispute($order);

        $this->resolve($order, ['refund_amount' => 10000, 'responsible' => 'fournisseur'])->assertSessionHas('success');

        // Client : 10 000 FCFA sur son Mobile Money.
        $payout = MobileMoneyPayout::sole();
        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, $payout->statut);
        $this->assertSame(10000, (int) $payout->montant);
        $this->assertSame($this->client->id, (int) $payout->user_id);
        $this->assertSame('+2250700000010', $payout->phone);

        // Fournisseur : il rend ce qu'il avait reçu pour ces articles (10 000 moins 5 %).
        $this->assertSame(19000 - 9500, $this->wallet($this->supplier, WalletType::WALLET_MATERIAUX));
        $this->assertSame(0, OrderDisputeDebt::count());

        // ProsArtisan : reçoit 9 500, verse 10 000 — il rend sa commission de 500.
        $this->assertSame(500000 + 9500 - 10000, $this->wallet($this->admin, WalletType::WALLET_MO));

        $dispute = OrderDispute::sole();
        $this->assertSame(10000, $dispute->refund_amount);
        $this->assertSame('fournisseur', $dispute->responsible_role);
        $this->assertSame('delivered', $order->fresh()->status);

        $this->assertTrue(Notification::where('user_id', $this->client->id)->where('event_key', 'commande.litige_rembourse.client')->exists());
        $this->assertTrue(AdminActivityLog::where('action', 'order_dispute.resolved')->exists());

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/orders/disputes')
            ->assertOk()
            ->assertJsonPath('data.0.refund_amount', 10000)
            ->assertJsonPath('data.0.responsible_label', 'Fournisseur');
    }

    public function test_le_remboursement_est_plafonne_au_prix_des_articles_et_exige_un_responsable(): void
    {
        $order = $this->deliveredOrder();
        $this->dispute($order);

        $this->resolve($order, ['refund_amount' => 20001, 'responsible' => 'fournisseur'])->assertSessionHasErrors('refund_amount');
        $this->resolve($order, ['refund_amount' => 0, 'responsible' => 'fournisseur'])->assertSessionHasErrors('refund_amount');
        $this->resolve($order, ['refund_amount' => 5000])->assertSessionHasErrors('responsible');
        $this->resolve($order, ['refund_amount' => 5000, 'responsible' => 'client'])->assertSessionHasErrors('responsible');

        $this->assertSame('disputed', $order->fresh()->status);
        $this->assertSame('ouvert', OrderDispute::sole()->statut);
        $this->assertSame(0, MobileMoneyPayout::count());
        $this->assertSame(19000, $this->wallet($this->supplier, WalletType::WALLET_MATERIAUX));
    }

    public function test_le_livreur_ne_peut_etre_designe_que_si_la_commande_en_a_un(): void
    {
        $order = $this->deliveredOrder(['driver_id' => null, 'delivery_mode' => 'pickup']);
        $this->dispute($order);

        $this->resolve($order, ['refund_amount' => 5000, 'responsible' => 'livreur'])->assertSessionHasErrors('responsible');
        $this->assertSame(0, MobileMoneyPayout::count());
    }

    public function test_un_virement_de_remboursement_en_echec_reste_du_au_client(): void
    {
        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferToMobileMoney')->andThrow(new \RuntimeException('Opérateur indisponible'));
        });
        $order = $this->deliveredOrder();
        $this->dispute($order);

        $this->resolve($order, ['refund_amount' => 10000, 'responsible' => 'fournisseur'])->assertSessionHas('success');

        $payout = MobileMoneyPayout::sole();
        $this->assertSame(MobileMoneyPayout::STATUT_ECHOUE, $payout->statut);
        $this->assertNotNull($payout->next_retry_at);
        // Le compte de ProsArtisan n'est débité qu'au virement réussi.
        $this->assertSame(500000 + 9500, $this->wallet($this->admin, WalletType::WALLET_MO));
    }

    // ── Dette, blocage, recouvrement ─────────────────────────────────────

    public function test_si_le_fournisseur_a_deja_retire_la_somme_le_client_est_rembourse_et_une_dette_est_creee(): void
    {
        $order = $this->deliveredOrder(creditSupplier: false);
        $this->dispute($order);

        $this->resolve($order, ['refund_amount' => 20000, 'responsible' => 'fournisseur'])->assertSessionHas('success');

        // ProsArtisan avance : le client est remboursé tout de suite.
        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, MobileMoneyPayout::sole()->statut);
        $this->assertSame(500000 - 20000, $this->wallet($this->admin, WalletType::WALLET_MO));

        $debt = OrderDisputeDebt::sole();
        $this->assertSame($this->supplier->id, (int) $debt->user_id);
        $this->assertSame(19000, $debt->montant);
        $this->assertSame('en_cours', $debt->statut);
        $this->assertTrue(Notification::where('user_id', $this->supplier->id)->where('event_key', 'litige_commande.dette_creee.responsable')->exists());
    }

    public function test_un_fournisseur_debiteur_est_bloque_jusqu_au_remboursement(): void
    {
        FournisseurAgree::create(['user_id' => $this->supplier->id, 'nom_boutique' => 'Quincaillerie du Plateau', 'statut' => 'agree', 'position' => Geo::point()]);
        $order = $this->deliveredOrder(creditSupplier: false);
        $this->dispute($order);
        $this->resolve($order, ['refund_amount' => 20000, 'responsible' => 'fournisseur']);

        // Retrait refusé.
        $this->actingAs($this->supplier, 'sanctum')
            ->postJson('/api/v1/supplier/cashouts', ['montant' => 1000, 'mode_retrait' => 'wave'])
            ->assertForbidden()
            ->assertJsonPath('dispute_debt', true);

        // Boutique retirée de la liste des fournisseurs.
        $ids = collect($this->actingAs($this->client, 'sanctum')->getJson('/api/v1/fournisseurs')->assertOk()->json('data'))->pluck('id');
        $this->assertNotContains($this->supplier->id, $ids);

        // Le règlement de la dette, lui, reste accessible.
        $this->actingAs($this->supplier, 'sanctum')
            ->getJson('/api/v1/dispute-debts')
            ->assertOk()
            ->assertJsonPath('data.0.restant', 19000)
            ->assertJsonPath('data.0.statut_label', 'À rembourser');
    }

    public function test_la_dette_est_prelevee_sur_les_gains_suivants_puis_le_compte_est_debloque(): void
    {
        $order = $this->deliveredOrder(creditSupplier: false);
        $this->dispute($order);
        $this->resolve($order, ['refund_amount' => 20000, 'responsible' => 'fournisseur']);
        $debts = app(OrderDisputeDebtService::class);

        // Un premier gain de 12 000 : entièrement prélevé.
        app(WalletService::class)->credit($this->supplier, WalletType::WALLET_MATERIAUX, 12000, 'Vente');
        $this->assertSame(12000, $debts->recover($this->supplier));

        $debt = OrderDisputeDebt::sole();
        $this->assertSame(7000, $debt->remaining());
        $this->assertSame('en_cours', $debt->statut);
        $this->assertSame(0, $this->wallet($this->supplier, WalletType::WALLET_MATERIAUX));
        $this->assertTrue($debts->hasOpenDebt($this->supplier));

        // Un second gain de 10 000 : solde la dette, le reste lui revient.
        app(WalletService::class)->credit($this->supplier, WalletType::WALLET_MATERIAUX, 10000, 'Vente');
        $this->assertSame(7000, $debts->recover($this->supplier));

        $debt->refresh();
        $this->assertSame('soldee', $debt->statut);
        $this->assertNotNull($debt->settled_at);
        $this->assertSame(3000, $this->wallet($this->supplier, WalletType::WALLET_MATERIAUX));
        $this->assertFalse($debts->hasOpenDebt($this->supplier));
        $this->assertSame(2, $debt->entries()->count());
        $this->assertTrue(Notification::where('user_id', $this->supplier->id)->where('event_key', 'litige_commande.dette_soldee.responsable')->exists());

        // ProsArtisan a récupéré son avance : 20 000 versés, 19 000 recouvrés, 1 000 de commission rendue.
        $this->assertSame(500000 - 20000 + 19000, $this->wallet($this->admin, WalletType::WALLET_MO));

        // Plus rien à prélever.
        $this->assertSame(0, $debts->recover($this->supplier));
    }

    public function test_le_debiteur_regle_sa_dette_par_mobile_money_et_se_debloque(): void
    {
        $order = $this->deliveredOrder(creditSupplier: false);
        $this->dispute($order);
        $this->resolve($order, ['refund_amount' => 20000, 'responsible' => 'fournisseur']);
        $debt = OrderDisputeDebt::sole();

        // Un tiers ne peut pas régler ni consulter la dette d'un autre.
        $this->actingAs($this->livreur, 'sanctum')
            ->postJson("/api/v1/dispute-debts/{$debt->id}/pay", ['provider' => 'wave'])
            ->assertForbidden();
        $this->actingAs($this->livreur, 'sanctum')->getJson('/api/v1/dispute-debts')->assertOk()->assertJsonCount(0, 'data');

        $response = $this->actingAs($this->supplier, 'sanctum')
            ->postJson("/api/v1/dispute-debts/{$debt->id}/pay", ['provider' => 'wave', 'montant' => 1])
            ->assertOk()
            ->assertJsonPath('data.payment_url', 'https://pay.test/c22');

        // Le montant est le restant dû établi par le serveur, jamais un montant posté.
        $payment = Transaction::findOrFail($response->json('data.transaction_id'));
        $this->assertSame(19000, (int) $payment->montant);
        $this->assertSame('reglement_dette_litige', $payment->type);

        $payment->update(['statut' => PaymentStatus::CONFIRME, 'paid_at' => now()]);
        app(PaymentService::class)->applyConfirmedPayment($payment->fresh());
        // Seconde confirmation (webhook puis interrogation de statut) : sans effet.
        app(PaymentService::class)->applyConfirmedPayment($payment->fresh());

        $debt->refresh();
        $this->assertSame('soldee', $debt->statut);
        $this->assertSame(19000, $debt->montant_recouvre);
        $this->assertSame(1, $debt->entries()->count());
        $this->assertSame(500000 - 20000 + 19000, $this->wallet($this->admin, WalletType::WALLET_MO));

        // Dette soldée : plus rien à régler.
        $this->actingAs($this->supplier, 'sanctum')
            ->postJson("/api/v1/dispute-debts/{$debt->id}/pay", ['provider' => 'wave'])
            ->assertStatus(422);
    }

    public function test_l_administrateur_peut_annuler_une_dette_avec_un_motif(): void
    {
        $order = $this->deliveredOrder(creditSupplier: false);
        $this->dispute($order);
        $this->resolve($order, ['refund_amount' => 20000, 'responsible' => 'fournisseur']);
        $debt = OrderDisputeDebt::sole();

        $this->actingAs($this->admin)->post("/admin/dispute-debts/{$debt->id}/cancel", ['reason' => 'Non'])->assertSessionHasErrors('reason');

        $this->actingAs($this->admin)
            ->post("/admin/dispute-debts/{$debt->id}/cancel", ['reason' => 'Erreur de désignation du responsable.'])
            ->assertSessionHas('success');

        $this->assertSame('annulee', $debt->fresh()->statut);
        $this->assertFalse(app(OrderDisputeDebtService::class)->hasOpenDebt($this->supplier));
        $this->assertTrue(AdminActivityLog::where('action', 'order_dispute_debt.cancelled')->exists());
    }

    // ── Faute du livreur ─────────────────────────────────────────────────

    public function test_la_faute_du_livreur_rembourse_la_course_payee_et_lui_impute_la_marchandise(): void
    {
        $order = $this->deliveredOrder();
        app(WalletService::class)->credit($this->livreur, WalletType::WALLET_MO, 1350, 'Gain de course');
        $this->dispute($order);

        $this->resolve($order, ['refund_amount' => 5000, 'responsible' => 'livreur'])->assertSessionHas('success');

        // Client : articles 5 000 + course 1 500.
        $this->assertSame(6500, (int) MobileMoneyPayout::sole()->montant);
        $this->assertSame('remboursee', $order->fresh()->delivery_fare_status);

        // Livreur : il doit 5 000 d'articles + son gain net de 1 350 ; son portefeuille en couvre 1 350.
        $this->assertSame(0, $this->wallet($this->livreur, WalletType::WALLET_MO));
        $debt = OrderDisputeDebt::sole();
        $this->assertSame($this->livreur->id, (int) $debt->user_id);
        $this->assertSame(5000, $debt->montant);

        // Le fournisseur n'est pas touché, et son gel est levé.
        $this->assertSame(19000, app(SupplierCashoutService::class)->getAvailableBalance($this->supplier->fresh()));

        // Le livreur débiteur ne peut plus accepter de course ni retirer ses gains.
        $course = $this->deliveredOrder(['status' => 'searching_driver', 'driver_id' => null, 'delivered_at' => null], creditSupplier: false);
        $this->actingAs($this->livreur, 'sanctum')
            ->postJson("/api/v1/deliveries/{$course->id}/accept")
            ->assertForbidden()
            ->assertJsonPath('dispute_debt', true);
        $this->actingAs($this->livreur, 'sanctum')
            ->postJson('/api/v1/driver/cashouts', ['montant' => 1000, 'mode_retrait' => 'wave'])
            ->assertForbidden();
    }

    public function test_la_faute_du_livreur_annule_une_course_non_encore_payee(): void
    {
        $order = $this->deliveredOrder(['delivery_fare_status' => 'a_payer']);
        $this->assertSame(1500, $order->deliveryFareDue());
        $this->dispute($order);

        $this->resolve($order, ['refund_amount' => 5000, 'responsible' => 'livreur'])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('annulee', $order->delivery_fare_status);
        $this->assertSame(0, $order->deliveryFareDue());
        $this->assertSame(22100 - 1500, (int) $order->total_amount);
        // Seuls les articles sont remboursés : la course n'avait pas été payée.
        $this->assertSame(5000, (int) MobileMoneyPayout::sole()->montant);
    }

    public function test_la_faute_du_fournisseur_laisse_la_course_due(): void
    {
        $order = $this->deliveredOrder(['delivery_fare_status' => 'a_payer']);
        $this->dispute($order);

        $this->resolve($order, ['refund_amount' => 5000, 'responsible' => 'fournisseur'])->assertSessionHas('success');

        $this->assertSame('a_payer', $order->fresh()->delivery_fare_status);
        $this->assertSame(1500, $order->fresh()->deliveryFareDue());
    }
}
