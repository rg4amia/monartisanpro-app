<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\AdminActivityLog;
use App\Models\DriverCashout;
use App\Models\MobileMoneyPayout;
use App\Models\Setting;
use App\Models\User;
use App\Services\WalletService;
use App\Services\WaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Retrait des gains du livreur (Chantier 10), sur le modèle du cash-out
 * quincaillerie : demande, approbation, versement Mobile Money réel via le
 * module de versements, rejet motivé.
 */
class DriverCashoutTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = $this->makeUser('livreur', ['payment_phone' => '+2250700112233']);
        $this->admin = $this->makeUser('admin');

        app(WalletService::class)->credit($this->driver, WalletType::WALLET_MO, 20000, 'Gains de courses');
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'kyc_status' => 'actif'], $attributes));
    }

    private function requestCashout(int $amount, string $mode = 'wave'): TestResponse
    {
        return $this->actingAs($this->driver)->postJson('/api/v1/driver/cashouts', [
            'montant_brut' => $amount,
            'mode_retrait' => $mode,
        ]);
    }

    public function test_driver_sees_withdrawable_balance_with_zero_fee_by_default(): void
    {
        $this->actingAs($this->driver)
            ->getJson('/api/v1/driver/cashouts')
            ->assertOk()
            ->assertJsonPath('data.stats.available_balance', 20000)
            ->assertJsonPath('data.stats.commission_rate', 0)
            ->assertJsonPath('data.cashouts', []);
    }

    public function test_request_reserves_the_amount_without_debiting(): void
    {
        $this->requestCashout(15000)
            ->assertCreated()
            ->assertJsonPath('data.statut', 'en_attente')
            ->assertJsonPath('data.montant_net', 15000);

        $this->assertSame(20000, $this->driver->fresh()->wallet_mo, 'Rien n\'est débité avant le versement.');

        // Plafond cumulatif : la seconde demande dépasse le solde restant.
        $this->requestCashout(6000)->assertStatus(422);
        $this->requestCashout(5000)->assertCreated();
    }

    public function test_only_drivers_can_request(): void
    {
        $artisan = $this->makeUser('artisan');

        $this->actingAs($artisan)->getJson('/api/v1/driver/cashouts')->assertForbidden();
        $this->actingAs($artisan)
            ->postJson('/api/v1/driver/cashouts', ['montant_brut' => 1000, 'mode_retrait' => 'wave'])
            ->assertForbidden();
    }

    public function test_minimum_amount_and_bank_details_are_enforced(): void
    {
        $this->requestCashout(100)->assertStatus(422);
        $this->requestCashout(5000, 'virement_bancaire')->assertStatus(422);
    }

    public function test_admin_pays_a_mobile_money_cashout(): void
    {
        $this->requestCashout(15000);
        $cashout = DriverCashout::firstOrFail();

        $this->actingAs($this->admin)
            ->post("/admin/driver-cashouts/{$cashout->id}/pay")
            ->assertSessionHas('success');

        $cashout->refresh();
        $this->assertSame(DriverCashout::STATUT_COMPLETE, $cashout->statut);
        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, $cashout->payout->statut);
        $this->assertSame('+2250700112233', $cashout->payout->phone);
        $this->assertSame(5000, $this->driver->fresh()->wallet_mo);
        $this->assertTrue(AdminActivityLog::where('action', 'driver_cashout.paid')->exists());
    }

    public function test_a_failed_transfer_keeps_the_cashout_open_and_is_completed_on_retry(): void
    {
        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferToMobileMoney')->once()->andThrow(new \Exception('Service Wave indisponible'));
            $mock->shouldReceive('transferToMobileMoney')->andReturn(['id' => 'WAVE-RETRAIT-1']);
        });

        $this->requestCashout(15000);
        $cashout = DriverCashout::firstOrFail();

        $this->actingAs($this->admin)
            ->post("/admin/driver-cashouts/{$cashout->id}/pay")
            ->assertSessionHas('error');

        $cashout->refresh();
        $this->assertSame(DriverCashout::STATUT_APPROUVE, $cashout->statut);
        $this->assertSame(20000, $this->driver->fresh()->wallet_mo, 'Un virement échoué ne débite rien.');

        $this->actingAs($this->admin)
            ->post("/admin/payouts/{$cashout->payout_id}/retry")
            ->assertSessionHas('success');

        $this->assertSame(DriverCashout::STATUT_COMPLETE, $cashout->fresh()->statut);
        $this->assertSame(5000, $this->driver->fresh()->wallet_mo);
    }

    public function test_bank_transfer_is_settled_on_reference(): void
    {
        $this->actingAs($this->driver)->postJson('/api/v1/driver/cashouts', [
            'montant_brut' => 10000,
            'mode_retrait' => 'virement_bancaire',
            'bank_name' => 'SGBCI',
            'bank_account_number' => 'CI93 0000 1111',
        ])->assertCreated();
        $cashout = DriverCashout::firstOrFail();

        $this->actingAs($this->admin)->post("/admin/driver-cashouts/{$cashout->id}/pay")->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post("/admin/driver-cashouts/{$cashout->id}/pay", ['external_reference' => 'VIR-SGBCI-42'])
            ->assertSessionHas('success');

        $this->assertSame(DriverCashout::STATUT_COMPLETE, $cashout->fresh()->statut);
        $this->assertSame('VIR-SGBCI-42', $cashout->fresh()->payout->external_reference);
        $this->assertSame(10000, $this->driver->fresh()->wallet_mo);
    }

    public function test_fee_is_applied_when_configured(): void
    {
        Setting::updateOrCreate(['key' => 'commission_cashout_livreur'], ['value' => '0.02', 'type' => 'float', 'group' => 'commissions']);

        $this->requestCashout(10000)->assertJsonPath('data.montant_commission', 200)->assertJsonPath('data.montant_net', 9800);
        $cashout = DriverCashout::firstOrFail();

        $this->actingAs($this->admin)->post("/admin/driver-cashouts/{$cashout->id}/pay");

        // Le portefeuille est débité du brut ; 9 800 FCFA sont virés.
        $this->assertSame(10000, $this->driver->fresh()->wallet_mo);
        $this->assertSame(9800, $cashout->fresh()->payout->transferAmount());
    }

    public function test_rejection_requires_a_reason_and_frees_the_amount(): void
    {
        $this->requestCashout(15000);
        $cashout = DriverCashout::firstOrFail();

        $this->actingAs($this->admin)->post("/admin/driver-cashouts/{$cashout->id}/reject", ['reason' => 'x'])->assertSessionHasErrors('reason');

        $this->actingAs($this->admin)
            ->post("/admin/driver-cashouts/{$cashout->id}/reject", ['reason' => 'Numéro de bénéficiaire invalide'])
            ->assertSessionHas('success');

        $this->assertSame(DriverCashout::STATUT_REJETE, $cashout->fresh()->statut);
        $this->actingAs($this->driver)->getJson('/api/v1/driver/cashouts')->assertJsonPath('data.stats.available_balance', 20000);
        $this->assertTrue(AdminActivityLog::where('action', 'driver_cashout.rejected')->exists());
    }

    public function test_non_admin_cannot_process_cashouts(): void
    {
        $this->requestCashout(15000);
        $cashout = DriverCashout::firstOrFail();

        $this->actingAs($this->driver)->post("/admin/driver-cashouts/{$cashout->id}/pay")->assertForbidden();
        $this->assertSame(DriverCashout::STATUT_EN_ATTENTE, $cashout->fresh()->statut);
    }
}
