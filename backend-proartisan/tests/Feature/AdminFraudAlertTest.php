<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\FraudAlert;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arbitrage des alertes de fraude depuis le backoffice (FraudAlertAdminService).
 *
 * Un jalon validé par OTP mais suspendu par une alerte (`valide_suspendu`)
 * sortait de JalonService::validateOtp() avant le contrôle du seuil Référent :
 * la levée du gel le payait donc directement, même au-delà de 2 000 000 FCFA
 * (Règle d'or 5).
 */
class AdminFraudAlertTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    /**
     * @return array{0: Mission, 1: Jalon, 2: FraudAlert, 3: User}
     */
    private function suspendedJalon(int $montantTotal, int $walletMo = 10000): array
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'wallet_mo' => $walletMo]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier sous alerte',
            'status' => 'in_progress',
            'montant_total' => $montantTotal,
            'montant_materiaux' => (int) round($montantTotal * 0.65),
            'montant_mo' => (int) round($montantTotal * 0.35),
            'ratio_materiaux' => 0.65,
            'referent_required' => false,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Jalon validé trop vite',
            'montant' => 10000,
            'statut' => 'valide_suspendu',
        ]);

        $alert = FraudAlert::create([
            'reference' => FraudAlert::generateReference(),
            'mission_id' => $mission->id,
            'user_id' => $artisan->id,
            'type' => 'fast_otp_validation',
            'severity' => 'high',
            'risk_score' => 80,
            'reasons_json' => [],
            'metadata_json' => [],
            'statut' => 'ouverte',
            'action_taken' => 'payment_hold',
        ]);

        return [$mission, $jalon, $alert, $artisan];
    }

    public function test_release_above_referent_threshold_waits_for_physical_validation(): void
    {
        [$mission, $jalon, $alert, $artisan] = $this->suspendedJalon(2500000);

        $this->actingAs($this->admin())
            ->post("/admin/fraud-alerts/{$alert->id}/release", ['notes' => 'Vérifié par téléphone'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('valide', $jalon->fresh()->statut);
        $this->assertTrue($mission->fresh()->referent_required);
        $this->assertEquals(10000, $artisan->fresh()->wallet_mo, 'Aucun paiement avant la visite du Référent.');
        $this->assertSame('classee', $alert->fresh()->statut);
    }

    public function test_release_below_threshold_pays_the_suspended_jalon(): void
    {
        [$mission, $jalon, $alert] = $this->suspendedJalon(100000);

        $this->actingAs($this->admin())
            ->post("/admin/fraud-alerts/{$alert->id}/release")
            ->assertSessionHasNoErrors();

        $this->assertSame('paye', $jalon->fresh()->statut);
        $this->assertFalse($mission->fresh()->referent_required);
        $this->assertTrue(AdminActivityLog::where('action', 'fraud_alert.released')->where('subject_id', $alert->id)->exists());
    }

    public function test_release_is_refused_while_the_mission_funds_are_frozen(): void
    {
        [$mission, $jalon, $alert] = $this->suspendedJalon(100000);
        $mission->update(['funds_frozen' => true]);

        $this->actingAs($this->admin())
            ->post("/admin/fraud-alerts/{$alert->id}/release")
            ->assertSessionHasErrors('fraud_alert');

        $this->assertSame('valide_suspendu', $jalon->fresh()->statut);
        $this->assertSame('ouverte', $alert->fresh()->statut);
    }

    public function test_confirm_penalises_the_author_and_is_audited(): void
    {
        [, , $alert, $artisan] = $this->suspendedJalon(100000);
        $artisan->update(['score_prosartisan' => 500]);

        $this->actingAs($this->admin())
            ->post("/admin/fraud-alerts/{$alert->id}/confirm", ['notes' => 'Collusion établie avec le client'])
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmee', $alert->fresh()->statut);
        $this->assertDatabaseHas('score_ledger_entries', ['user_id' => $artisan->id, 'event_type' => 'dispute_fraud']);
        $this->assertTrue(AdminActivityLog::where('action', 'fraud_alert.confirmed')->exists());
    }

    public function test_dismiss_and_hold_are_audited(): void
    {
        [, , $alert] = $this->suspendedJalon(100000);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/fraud-alerts/{$alert->id}/hold")->assertSessionHasNoErrors();
        $this->assertSame('en_analyse', $alert->fresh()->statut);

        $this->actingAs($admin)->post("/admin/fraud-alerts/{$alert->id}/dismiss")->assertSessionHasNoErrors();
        $this->assertSame('rejetee', $alert->fresh()->statut);

        $this->assertSame(2, AdminActivityLog::whereIn('action', ['fraud_alert.hold', 'fraud_alert.dismissed'])->count());
    }
}
