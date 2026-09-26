<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\Mission;
use App\Models\MobileMoneyPayout;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MobileMoneyPayoutService;
use App\Services\WalletService;
use App\Services\WaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Remboursement du client après litige (reliquat du Chantier 10).
 *
 * Auparavant, `refundClientFromDispute` débitait les portefeuilles de
 * l'artisan puis tentait le virement : un échec était seulement journalisé,
 * la transaction restait « en attente » pour toujours et les fonds ne
 * figuraient plus nulle part — ni chez l'artisan, ni chez le client.
 */
class ClientRefundPayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    private User $admin;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'wallet_materiaux' => 65000,
            'wallet_mo' => 35000,
        ]);
        $this->admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $this->mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Rénovation salle de bain',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);
    }

    private function waveFails(string $message = 'Numéro Wave inconnu'): void
    {
        $this->mock(WaveService::class, function (MockInterface $mock) use ($message) {
            $mock->shouldReceive('transferToMobileMoney')->andThrow(new \Exception($message));
        });
    }

    private function waveSucceeds(): void
    {
        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferToMobileMoney')->andReturn(['id' => 'WAVE-REFUND-1']);
        });
    }

    private function failedRefund(): MobileMoneyPayout
    {
        $this->waveFails();
        app(WalletService::class)->refundClientFromDispute($this->mission, 65000, 35000);

        return MobileMoneyPayout::firstOrFail();
    }

    public function test_a_failed_refund_leaves_the_funds_on_the_artisan_wallets(): void
    {
        $payout = $this->failedRefund();

        $this->assertSame(MobileMoneyPayout::STATUT_ECHOUE, $payout->statut);
        $this->assertSame(MobileMoneyPayout::CONTEXT_REMBOURSEMENT_CLIENT, $payout->context);
        $this->assertSame($this->client->id, $payout->user_id, 'Le client est le bénéficiaire du virement.');
        $this->assertSame(100000, $payout->montant);
        $this->assertNotNull($payout->next_retry_at);

        // Rien n'est débité tant que le client n'a rien reçu.
        $this->artisan->refresh();
        $this->assertSame(65000, $this->artisan->wallet_materiaux);
        $this->assertSame(35000, $this->artisan->wallet_mo);

        $transaction = Transaction::where('type', 'remboursement')->sole();
        $this->assertSame($this->client->id, $transaction->user_id);
        $this->assertSame(100000, (int) $transaction->montant);
        $this->assertSame('echoue', $transaction->statut->value);
    }

    public function test_a_pending_refund_is_reserved_in_the_mission_escrow(): void
    {
        $this->failedRefund();

        $payouts = app(MobileMoneyPayoutService::class);
        $this->assertSame(65000, $payouts->reservedForMission($this->mission->id, WalletType::WALLET_MATERIAUX));
        $this->assertSame(35000, $payouts->reservedForMission($this->mission->id, WalletType::WALLET_MO));

        // Le séquestre de la mission est vide : les fonds dus au client ne
        // peuvent plus être libérés vers l'artisan.
        $wallet = app(WalletService::class);
        $this->assertSame(0, $wallet->getMissionEscrowBalance($this->mission, WalletType::WALLET_MATERIAUX));
        $this->assertSame(0, $wallet->getMissionEscrowBalance($this->mission, WalletType::WALLET_MO));
    }

    public function test_a_successful_retry_debits_both_artisan_wallets_once(): void
    {
        $payout = $this->failedRefund();

        $this->waveSucceeds();
        $payout = app(MobileMoneyPayoutService::class)->retryByAdmin($payout, $this->admin);

        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, $payout->statut);
        $this->assertSame('WAVE-REFUND-1', $payout->external_reference);

        $this->artisan->refresh();
        $this->assertSame(0, $this->artisan->wallet_materiaux);
        $this->assertSame(0, $this->artisan->wallet_mo);
        $this->assertSame(0, $this->client->fresh()->wallet_materiaux + $this->client->fresh()->wallet_mo, 'Le client est payé par virement, pas sur un portefeuille.');

        $this->assertSame('confirme', $payout->transaction->fresh()->statut->value);
        $this->assertSame(0, app(MobileMoneyPayoutService::class)->reservedForMission($this->mission->id, WalletType::WALLET_MO));

        // Une seconde relance ne débite rien de plus.
        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, app(MobileMoneyPayoutService::class)->attemptNow($payout)->statut);
        $this->assertSame(0, $this->artisan->fresh()->wallet_mo);
    }

    public function test_an_immediate_success_refunds_in_a_single_transfer(): void
    {
        $this->waveSucceeds();

        $transaction = app(WalletService::class)->refundClientFromDispute($this->mission, 65000, 35000);

        $this->assertSame('confirme', $transaction->statut->value);
        $this->assertSame(100000, (int) $transaction->montant);
        $this->assertSame(1, MobileMoneyPayout::count());

        $this->artisan->refresh();
        $this->assertSame(0, $this->artisan->wallet_materiaux);
        $this->assertSame(0, $this->artisan->wallet_mo);
    }

    public function test_the_client_can_retry_their_own_refund_but_not_someone_elses(): void
    {
        $payout = $this->failedRefund();
        $payout->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();
        $this->waveSucceeds();

        $this->actingAs($this->artisan)
            ->postJson("/api/v1/payouts/{$payout->id}/retry")
            ->assertForbidden();

        $this->actingAs($this->client)
            ->postJson("/api/v1/payouts/{$payout->id}/retry")
            ->assertOk();

        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, $payout->fresh()->statut);
        $this->assertSame(0, $this->artisan->fresh()->wallet_materiaux);
    }

    public function test_a_refund_owed_to_the_client_cannot_be_cancelled(): void
    {
        $payout = $this->failedRefund();

        $this->expectException(\InvalidArgumentException::class);
        app(MobileMoneyPayoutService::class)->cancel($payout, $this->admin, 'Erreur de saisie');
    }
}
