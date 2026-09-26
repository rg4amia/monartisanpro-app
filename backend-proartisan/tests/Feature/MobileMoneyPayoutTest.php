<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\AdminActivityLog;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\MobileMoneyPayout;
use App\Models\User;
use App\Services\MobileMoneyPayoutService;
use App\Services\WalletService;
use App\Services\WaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Virement Mobile Money échoué vers l'artisan (Chantier 10).
 *
 * Auparavant, `releaseJalon` débitait le portefeuille puis tentait le
 * virement : un échec laissait la transaction « en attente » pour toujours,
 * sans relance, et les fonds ne figuraient plus nulle part.
 */
class MobileMoneyPayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $artisan;

    private User $admin;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'wallet_mo' => 100000]);
        $this->admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Rénovation salle de bain',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);
    }

    private function jalon(int $montant = 10000): Jalon
    {
        return Jalon::create([
            'mission_id' => $this->mission->id,
            'ordre' => 1,
            'description' => 'Étape 1',
            'montant' => $montant,
            'statut' => 'valide',
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
            $mock->shouldReceive('transferToMobileMoney')->andReturn(['id' => 'WAVE-OK-1']);
        });
    }

    private function failedPayout(): MobileMoneyPayout
    {
        $this->waveFails();
        app(WalletService::class)->releaseJalon($this->jalon());

        return MobileMoneyPayout::firstOrFail();
    }

    public function test_a_failed_transfer_does_not_debit_the_paid_amount(): void
    {
        $payout = $this->failedPayout();
        $net = $payout->montant;

        $this->assertSame(MobileMoneyPayout::STATUT_ECHOUE, $payout->statut);
        $this->assertStringContainsString('Numéro Wave inconnu', $payout->last_error);
        $this->assertNotNull($payout->next_retry_at, 'Une relance automatique doit être programmée.');

        // Seules la commission et les retenues sont débitées : le montant
        // destiné à l'artisan reste sur son portefeuille.
        $this->assertSame(100000 - (10000 - $net), $this->artisan->fresh()->wallet_mo);
        $this->assertSame('echoue', $payout->transaction->statut->value);
        $this->assertSame('paye', Jalon::first()->statut);

        $actions = $payout->events()->pluck('action')->all();
        $this->assertSame(['tentative', 'echec'], $actions);
    }

    public function test_an_unpaid_payout_is_reserved_in_the_mission_escrow(): void
    {
        $payout = $this->failedPayout();

        $this->assertSame(
            $payout->montant,
            app(MobileMoneyPayoutService::class)->reservedForMission($this->mission->id, WalletType::WALLET_MO)
        );
    }

    public function test_admin_retry_pays_debits_and_records_history(): void
    {
        $payout = $this->failedPayout();
        $balanceBefore = $this->artisan->fresh()->wallet_mo;

        $this->waveSucceeds();

        $this->actingAs($this->admin)
            ->post("/admin/payouts/{$payout->id}/retry")
            ->assertRedirect()
            ->assertSessionHas('success');

        $payout->refresh();
        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, $payout->statut);
        $this->assertSame('WAVE-OK-1', $payout->external_reference);
        $this->assertSame($balanceBefore - $payout->montant, $this->artisan->fresh()->wallet_mo);
        $this->assertSame('confirme', $payout->transaction->fresh()->statut->value);
        $this->assertSame(['tentative', 'echec', 'relance_manuelle', 'succes'], $payout->events()->pluck('action')->all());
        $this->assertSame($this->admin->id, $payout->events()->where('action', 'relance_manuelle')->value('actor_id'));
        $this->assertTrue(AdminActivityLog::where('action', 'payout.retried')->exists());
    }

    public function test_a_paid_payout_cannot_be_paid_twice(): void
    {
        $payout = $this->failedPayout();
        $this->waveSucceeds();
        app(MobileMoneyPayoutService::class)->retryByAdmin($payout, $this->admin);
        $balance = $this->artisan->fresh()->wallet_mo;

        $this->actingAs($this->admin)
            ->post("/admin/payouts/{$payout->id}/retry")
            ->assertSessionHas('error');

        $this->assertSame($balance, $this->artisan->fresh()->wallet_mo);
    }

    public function test_automatic_retry_only_runs_when_due(): void
    {
        $payout = $this->failedPayout();
        $this->waveSucceeds();

        $this->artisan('prosartisan:retry-failed-payouts')->assertSuccessful();
        $this->assertSame(MobileMoneyPayout::STATUT_ECHOUE, $payout->fresh()->statut, 'La relance ne doit pas partir avant son échéance.');

        $this->travel(16)->minutes();
        $this->artisan('prosartisan:retry-failed-payouts')->assertSuccessful();

        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, $payout->fresh()->statut);
        $this->assertContains('relance_automatique', $payout->events()->pluck('action')->all());
    }

    public function test_automatic_retries_stop_after_the_maximum(): void
    {
        $payout = $this->failedPayout();

        for ($i = 1; $i < MobileMoneyPayoutService::MAX_AUTOMATIC_ATTEMPTS; $i++) {
            $this->travel(5)->hours();
            $this->artisan('prosartisan:retry-failed-payouts');
        }

        $payout->refresh();
        $this->assertSame(MobileMoneyPayoutService::MAX_AUTOMATIC_ATTEMPTS, $payout->attempts);
        $this->assertNull($payout->next_retry_at, 'Au-delà du maximum, seule une relance humaine reprend.');
    }

    public function test_manual_payment_requires_a_reference_and_debits_the_wallet(): void
    {
        $payout = $this->failedPayout();
        $balance = $this->artisan->fresh()->wallet_mo;

        $this->actingAs($this->admin)
            ->post("/admin/payouts/{$payout->id}/mark-paid", [])
            ->assertSessionHasErrors('external_reference');

        $this->actingAs($this->admin)
            ->post("/admin/payouts/{$payout->id}/mark-paid", ['external_reference' => 'VIR-BICICI-778', 'note' => 'Payé par virement'])
            ->assertSessionHas('success');

        $payout->refresh();
        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, $payout->statut);
        $this->assertSame('VIR-BICICI-778', $payout->external_reference);
        $this->assertSame($balance - $payout->montant, $this->artisan->fresh()->wallet_mo);
        $this->assertSame('versement_manuel', $payout->events()->pluck('action')->last());
        $this->assertTrue(AdminActivityLog::where('action', 'payout.paid_manually')->exists());
    }

    public function test_an_artisan_payout_cannot_be_cancelled(): void
    {
        $payout = $this->failedPayout();

        $this->expectException(\InvalidArgumentException::class);
        app(MobileMoneyPayoutService::class)->cancel($payout, $this->admin, 'Test');
    }

    public function test_beneficiary_can_list_and_retry_only_their_own_payouts(): void
    {
        $payout = $this->failedPayout();
        $intruder = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        // Opérateur rétabli avant toute requête : le routeur conserve
        // l'instance du contrôleur d'une requête à l'autre dans un même test.
        $this->waveSucceeds();

        $this->actingAs($intruder)->postJson("/api/v1/payouts/{$payout->id}/retry")->assertForbidden();
        $this->actingAs($intruder)->getJson('/api/v1/payouts')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->artisan)
            ->getJson('/api/v1/payouts')
            ->assertOk()
            ->assertJsonPath('data.0.statut', 'echoue')
            ->assertJsonPath('data.0.statut_label', 'Virement échoué')
            ->assertJsonPath('data.0.can_retry', true)
            ->assertJsonCount(2, 'data.0.events');

        // Relance immédiate refusée : délai minimal entre deux tentatives.
        $this->actingAs($this->artisan)->postJson("/api/v1/payouts/{$payout->id}/retry")->assertStatus(422);

        $this->travel(2)->minutes();

        $this->actingAs($this->artisan)
            ->postJson("/api/v1/payouts/{$payout->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.statut', 'verse');
    }

    public function test_retry_uses_the_updated_payment_phone(): void
    {
        $payout = $this->failedPayout();
        $this->artisan->update(['payment_phone' => '+2250701020304']);

        $this->waveSucceeds();
        app(MobileMoneyPayoutService::class)->retryByAdmin($payout, $this->admin);

        $payout->refresh();
        $this->assertSame('+2250701020304', $payout->phone);
        $this->assertContains('changement_numero', $payout->events()->pluck('action')->all());
    }

    public function test_history_is_append_only(): void
    {
        $payout = $this->failedPayout();
        $event = $payout->events()->first();

        $this->assertFalse($event->update(['message' => 'falsifié']));
        $this->assertFalse($event->delete());
        $this->assertNull($event->fresh()->message);
    }
}
