<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\WalletType;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'audit de trésorerie annonçait « 100 % intègre » sans rien contrôler :
 * filtre sur les statuts français historiques (aucune mission retenue, Règle
 * d'Or 27) et requête sur des colonnes inexistantes du ledger (erreur SQL dès
 * qu'un portefeuille a un solde).
 */
class ReconcileTreasuryCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
    }

    private function mission(string $status, int $montantTotal, string $paymentType = 'total'): Mission
    {
        return Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Chantier',
            'status' => $status,
            'payment_type' => $paymentType,
            'montant_total' => $montantTotal,
            'montant_materiaux' => 0,
            'montant_mo' => $montantTotal,
            'ratio_materiaux' => 0,
        ]);
    }

    private function deposit(Mission $mission, int $montant, array $metadata = []): Transaction
    {
        return Transaction::create([
            'mission_id' => $mission->id,
            'user_id' => $this->client->id,
            'type' => 'acompte',
            'montant' => $montant,
            'wallet_source' => 'client_mobile_money_'.$this->client->id,
            'wallet_dest' => 'escrow_mission_'.$mission->id,
            'provider' => 'wave',
            'statut' => PaymentStatus::CONFIRME,
            'metadata' => $metadata,
        ]);
    }

    public function test_funded_mission_with_deposit_gap_is_reported_with_french_status_label(): void
    {
        $mission = $this->mission('funded_locked', 100000);
        $this->deposit($mission, 60000);

        $this->artisan('prosartisan:reconcile-treasury')
            ->expectsOutputToContain("Écart mission #{$mission->id} (Financée (séquestre bloqué), paiement intégral)")
            ->assertFailed();
    }

    public function test_promo_discount_is_not_reported_as_gap(): void
    {
        $mission = $this->mission('in_progress', 100000);
        $this->deposit($mission, 90000, ['discount_amount' => 10000]);

        $this->artisan('prosartisan:reconcile-treasury')->assertSuccessful();
    }

    public function test_hybrid_mission_in_progress_may_be_partially_paid_but_never_overpaid(): void
    {
        $partial = $this->mission('in_progress', 150000, 'hybrid');
        $this->deposit($partial, 100000);

        $this->artisan('prosartisan:reconcile-treasury')->assertSuccessful();

        $this->deposit($partial, 100000);

        $this->artisan('prosartisan:reconcile-treasury')
            ->expectsOutputToContain("Écart mission #{$partial->id} (En cours, hybride)")
            ->assertFailed();
    }

    public function test_wallet_balances_are_audited_against_the_ledger_without_sql_error(): void
    {
        app(WalletService::class)->credit($this->artisan, WalletType::WALLET_MO, 50000, 'Séquestre', ['type' => 'escrow_mo']);

        $this->artisan('prosartisan:reconcile-treasury')->assertSuccessful();

        // Affectation directe de la colonne, contournant le ledger (Règle d'Or 9).
        $this->artisan->forceFill(['wallet_mo' => 70000])->saveQuietly();

        $this->artisan('prosartisan:reconcile-treasury')
            ->expectsOutputToContain("Utilisateur #{$this->artisan->id} : solde main d'œuvre stocké 70000 FCFA ≠ ledger 50000 FCFA")
            ->assertFailed();
    }

    public function test_coherent_treasury_passes(): void
    {
        $mission = $this->mission('completed', 100000);
        $this->deposit($mission, 100000);
        app(WalletService::class)->credit($this->artisan, WalletType::WALLET_MATERIAUX, 30000, 'Séquestre', ['type' => 'escrow_materiaux']);

        $this->artisan('prosartisan:reconcile-treasury')
            ->expectsOutputToContain('Trésorerie intègre')
            ->assertSuccessful();
    }

    /**
     * Commande livrée « à la Yango » : le panier est payé à la commande, la
     * course au moment de la livraison, par un second paiement
     * (`paiement_livraison`) vers le même séquestre. `revealDeliveryFare`
     * ajoute la course au `total_amount`.
     */
    private function deliveredOrder(int $checkout, int $fare, string $fareStatus, ?string $groupId = null): Order
    {
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);

        return Order::create([
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'order_group_id' => $groupId,
            'status' => 'delivered',
            'delivery_mode' => 'delivery',
            'subtotal' => $checkout - 600,
            'delivery_cost' => $fare,
            'platform_fee' => 600,
            'total_amount' => $checkout + $fare,
            'delivery_fare_status' => $fareStatus,
            'delivery_fare_prepaid' => 0,
            'pickup_code' => 'RET-0001',
            'reception_code' => 'REC-0001',
        ]);
    }

    private function orderPayment(string $type, int $montant, string $walletDest): Transaction
    {
        return Transaction::create([
            'user_id' => $this->client->id,
            'type' => $type,
            'montant' => $montant,
            'wallet_source' => 'client_mobile_money_'.$this->client->id,
            'wallet_dest' => $walletDest,
            'provider' => 'wave',
            'statut' => PaymentStatus::CONFIRME,
        ]);
    }

    public function test_delivery_fare_paid_after_delivery_is_not_reported_as_gap(): void
    {
        $order = $this->deliveredOrder(20600, 2165, 'paye');
        $this->orderPayment('acompte', 20600, 'escrow_order_'.$order->id);
        $this->orderPayment('paiement_livraison', 2165, 'escrow_order_'.$order->id);

        $this->artisan('prosartisan:reconcile-treasury')
            ->doesntExpectOutputToContain('Écart commande')
            ->assertSuccessful();
    }

    public function test_delivery_fare_not_yet_paid_is_not_reported_as_gap(): void
    {
        $order = $this->deliveredOrder(20600, 2165, 'a_payer');
        $this->orderPayment('acompte', 20600, 'escrow_order_'.$order->id);

        $this->artisan('prosartisan:reconcile-treasury')
            ->doesntExpectOutputToContain('Écart commande')
            ->assertSuccessful();
    }

    public function test_grouped_orders_count_each_fare_paid_to_its_own_escrow(): void
    {
        $first = $this->deliveredOrder(20600, 2165, 'paye', 'GRP-1');
        $this->deliveredOrder(10600, 1500, 'a_payer', 'GRP-1');
        $this->orderPayment('acompte', 31200, 'escrow_group_GRP-1');
        $this->orderPayment('paiement_livraison', 2165, 'escrow_order_'.$first->id);

        $this->artisan('prosartisan:reconcile-treasury')
            ->doesntExpectOutputToContain('Écart groupe')
            ->assertSuccessful();
    }

    public function test_an_underpaid_order_is_still_reported(): void
    {
        $order = $this->deliveredOrder(20600, 2165, 'paye');
        $this->orderPayment('acompte', 18000, 'escrow_order_'.$order->id);
        $this->orderPayment('paiement_livraison', 2165, 'escrow_order_'.$order->id);

        $this->artisan('prosartisan:reconcile-treasury')
            ->expectsOutputToContain("Écart commande #{$order->id} (Livrée) : attendu 22765 FCFA, déposé 20165 FCFA")
            ->assertFailed();
    }
}
