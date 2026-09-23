<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mode hybride : la main d'œuvre n'est pas consignée à l'acceptation du devis,
 * chaque jalon est payé puis financé individuellement. Le séquestre MO vit dans
 * le `wallet_mo` de l'artisan, commun à toutes ses missions : un jalon doit
 * donc être financé par SON paiement avant toute libération, sans quoi il se
 * règle avec l'argent consigné pour une autre mission.
 */
class HybridJalonFundingTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.wave.webhook_secret' => 'test-wave-secret']);

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
    }

    private function hybridJalon(int $montant = 50000, string $statut = 'soumis'): Jalon
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Rénovation hybride',
            'status' => 'in_progress',
            'payment_type' => 'hybrid',
            'montant_total' => 150000,
            'montant_materiaux' => 100000,
            'montant_mo' => $montant,
            'ratio_materiaux' => 0.6667,
        ]);

        return Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Pose',
            'montant' => $montant,
            'statut' => $statut,
        ]);
    }

    /** Séquestre MO d'une autre mission, régulièrement financé. */
    private function fundOtherMissionEscrow(int $montant): Mission
    {
        $other = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Autre chantier financé',
            'status' => 'funded_locked',
            'payment_type' => 'total',
            'montant_total' => $montant,
            'montant_materiaux' => 0,
            'montant_mo' => $montant,
            'ratio_materiaux' => 0,
        ]);

        app(WalletService::class)->credit($this->artisan, WalletType::WALLET_MO, $montant, 'Séquestre MO autre mission', [
            'mission_id' => $other->id,
            'type' => 'escrow_mo',
        ]);

        return $other;
    }

    private function payJalonWithWave(Jalon $jalon): Transaction
    {
        $response = $this->actingAs($this->client)
            ->postJson("/api/v1/payments/jalons/{$jalon->id}/pay", [
                'provider' => 'wave',
                'phone' => $this->client->phone,
            ])
            ->assertOk();

        return Transaction::findOrFail($response->json('data.transaction_id'));
    }

    private function postSignedWaveWebhook(Transaction $transaction): void
    {
        $payload = json_encode([
            'id' => $transaction->wave_checkout_id,
            'status' => 'completed',
            'payment_id' => 'WAVE-PAY-1',
        ]);

        $this->call('POST', '/api/v1/webhooks/wave', [], [], [], [
            'HTTP_X-Wave-Signature' => hash_hmac('sha256', $payload, 'test-wave-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();
    }

    public function test_unfunded_hybrid_jalon_cannot_be_released_with_another_missions_escrow(): void
    {
        $other = $this->fundOtherMissionEscrow(80000);
        $jalon = $this->hybridJalon(50000);

        try {
            app(WalletService::class)->releaseJalon($jalon);
            $this->fail('Un jalon hybride jamais payé a été libéré.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('financé', $e->getMessage());
        }

        $this->assertSame(80000, $this->artisan->fresh()->wallet_mo);
        $this->assertSame(80000, app(WalletService::class)->getMissionEscrowBalance($other, WalletType::WALLET_MO));
        $this->assertSame('soumis', $jalon->fresh()->statut);
        $this->assertDatabaseMissing('transactions', ['type' => 'liberation_jalon']);
    }

    public function test_hybrid_labor_escrow_never_falls_back_on_other_missions_balance(): void
    {
        // Le solde de séquestre d'une mission sert aussi à l'arbitrage des
        // litiges (refundClient / payArtisan) : pour une mission hybride sans
        // jalon financé, il vaut 0 — jamais le solde global de l'artisan.
        $this->fundOtherMissionEscrow(80000);
        $jalon = $this->hybridJalon(50000);

        $this->assertSame(0, app(WalletService::class)->getMissionEscrowBalance($jalon->mission, WalletType::WALLET_MO));
    }

    public function test_wave_webhook_funds_the_paid_hybrid_jalon(): void
    {
        $jalon = $this->hybridJalon(50000);
        $transaction = $this->payJalonWithWave($jalon);

        $this->postSignedWaveWebhook($transaction);

        $this->assertSame(50000, $this->artisan->fresh()->wallet_mo);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->artisan->id,
            'jalon_id' => $jalon->id,
            'transaction_id' => $transaction->id,
            'wallet_type' => WalletType::WALLET_MO->value,
            'montant' => 50000,
        ]);

        // Financé, le jalon se libère normalement.
        app(WalletService::class)->releaseJalon($jalon->fresh());
        $this->assertSame('paye', $jalon->fresh()->statut);
        $this->assertSame(0, $this->artisan->fresh()->wallet_mo);
    }

    public function test_replayed_webhook_and_status_poll_fund_the_jalon_only_once(): void
    {
        $jalon = $this->hybridJalon(50000);
        $transaction = $this->payJalonWithWave($jalon);

        $this->postSignedWaveWebhook($transaction);
        $this->postSignedWaveWebhook($transaction);
        $this->actingAs($this->client)
            ->getJson("/api/v1/payments/{$transaction->id}/status")
            ->assertOk();

        $this->assertSame(50000, $this->artisan->fresh()->wallet_mo);
        $this->assertSame(1, WalletTransaction::where('jalon_id', $jalon->id)->count());
    }

    public function test_already_funded_jalon_cannot_be_paid_a_second_time(): void
    {
        $jalon = $this->hybridJalon(50000);
        $this->postSignedWaveWebhook($this->payJalonWithWave($jalon));

        $this->actingAs($this->client)
            ->postJson("/api/v1/payments/jalons/{$jalon->id}/pay", [
                'provider' => 'orange_money',
                'phone' => $this->client->phone,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(1, Transaction::where('mission_id', $jalon->mission_id)->where('type', 'acompte')->count());
    }
}
