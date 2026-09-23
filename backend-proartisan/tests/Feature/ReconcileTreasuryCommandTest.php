<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\WalletType;
use App\Models\Mission;
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
}
