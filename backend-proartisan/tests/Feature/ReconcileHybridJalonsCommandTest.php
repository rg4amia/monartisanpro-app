<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
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
 * Régularisation des missions hybrides touchées avant le cloisonnement du
 * séquestre par mission (Règle d'Or 68) : paiements de jalon confirmés par
 * webhook mais jamais consignés, et libérations ayant puisé dans le séquestre
 * d'autres missions.
 */
class ReconcileHybridJalonsCommandTest extends TestCase
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

    private function hybridJalon(string $statut = 'soumis', int $montant = 50000): Jalon
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Chantier hybride',
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

    /** Paiement client confirmé par webhook avant le correctif : jamais consigné. */
    private function confirmedJalonPayment(Jalon $jalon): Transaction
    {
        return Transaction::create([
            'mission_id' => $jalon->mission_id,
            'user_id' => $this->client->id,
            'type' => 'acompte',
            'montant' => $jalon->montant,
            'wallet_source' => 'client_mobile_money_'.$this->client->id,
            'wallet_dest' => 'escrow_mission_'.$jalon->mission_id,
            'provider' => 'wave',
            'statut' => PaymentStatus::CONFIRME,
            'metadata' => ['mission_id' => $jalon->mission_id, 'jalon_id' => $jalon->id, 'payment_type' => 'jalon'],
        ]);
    }

    private function fundOtherMission(int $montant): Mission
    {
        $other = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Autre chantier',
            'status' => 'funded_locked',
            'payment_type' => 'total',
            'montant_total' => $montant,
            'montant_materiaux' => 0,
            'montant_mo' => $montant,
            'ratio_materiaux' => 0,
        ]);

        app(WalletService::class)->credit($this->artisan, WalletType::WALLET_MO, $montant, 'Séquestre MO', [
            'mission_id' => $other->id,
            'type' => 'escrow_mo',
        ]);

        return $other;
    }

    /** Libération d'avant le correctif : débit du wallet_mo commun sans financement. */
    private function legacyRelease(Jalon $jalon): void
    {
        app(WalletService::class)->debit($this->artisan, WalletType::WALLET_MO, $jalon->montant, 'Libération jalon (historique)', [
            'mission_id' => $jalon->mission_id,
            'jalon_id' => $jalon->id,
            'type' => 'liberation_jalon',
        ]);
        $jalon->update(['statut' => 'paye', 'paye_at' => now()]);
    }

    public function test_report_mode_lists_unfunded_payments_without_writing(): void
    {
        $jalon = $this->hybridJalon();
        $payment = $this->confirmedJalonPayment($jalon);

        $this->artisan('prosartisan:reconcile-hybrid-jalons')
            ->expectsOutputToContain("payé par la transaction #{$payment->id}")
            ->expectsOutputToContain('Jalons payés non financés : 1')
            ->assertFailed();

        $this->assertSame(0, $this->artisan->fresh()->wallet_mo);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_fix_funds_an_unfunded_pending_jalon_once(): void
    {
        $jalon = $this->hybridJalon('soumis');
        $payment = $this->confirmedJalonPayment($jalon);

        $this->artisan('prosartisan:reconcile-hybrid-jalons', ['--fix' => true])->assertSuccessful();
        $this->artisan('prosartisan:reconcile-hybrid-jalons', ['--fix' => true])->assertSuccessful();

        $this->assertSame(50000, $this->artisan->fresh()->wallet_mo);
        $this->assertSame(1, WalletTransaction::where('jalon_id', $jalon->id)->count());
        $this->assertDatabaseHas('wallet_transactions', [
            'jalon_id' => $jalon->id,
            'transaction_id' => $payment->id,
            'montant' => 50000,
        ]);

        // Le jalon régularisé se libère désormais normalement.
        app(WalletService::class)->releaseJalon($jalon->fresh());
        $this->assertSame('paye', $jalon->fresh()->statut);
    }

    public function test_fix_restores_other_missions_escrow_drained_by_an_old_release(): void
    {
        $other = $this->fundOtherMission(80000);
        $jalon = $this->hybridJalon('soumis');
        $this->confirmedJalonPayment($jalon);
        $this->legacyRelease($jalon);

        $this->assertSame(30000, $this->artisan->fresh()->wallet_mo);

        $this->artisan('prosartisan:reconcile-hybrid-jalons', ['--fix' => true])
            ->expectsOutputToContain("Financé : Jalon #{$jalon->id}")
            ->assertSuccessful();

        // Le séquestre de l'autre mission est à nouveau intégralement couvert.
        $this->assertSame(80000, $this->artisan->fresh()->wallet_mo);
        $this->assertSame(80000, app(WalletService::class)->getMissionEscrowBalance($other, WalletType::WALLET_MO));
        $this->assertSame(0, app(WalletService::class)->getMissionEscrowBalance($jalon->mission, WalletType::WALLET_MO));
    }

    public function test_release_without_any_client_payment_is_reported_as_deficit(): void
    {
        $this->fundOtherMission(80000);
        $jalon = $this->hybridJalon('soumis');
        $this->legacyRelease($jalon);

        $this->artisan('prosartisan:reconcile-hybrid-jalons', ['--fix' => true])
            ->expectsOutputToContain("Mission #{$jalon->mission_id} : déficit de 50000 FCFA")
            ->assertFailed();

        // Rien n'est inventé : aucun crédit sans paiement client réel.
        $this->assertSame(30000, $this->artisan->fresh()->wallet_mo);
    }

    public function test_double_payment_is_reported_and_funded_only_once(): void
    {
        $jalon = $this->hybridJalon();
        $this->confirmedJalonPayment($jalon);
        $second = $this->confirmedJalonPayment($jalon);

        $this->artisan('prosartisan:reconcile-hybrid-jalons', ['--fix' => true])
            ->expectsOutputToContain("la transaction #{$second->id} (50000 FCFA) est à rembourser")
            ->assertFailed();

        $this->assertSame(50000, $this->artisan->fresh()->wallet_mo);
        $this->assertSame(1, WalletTransaction::where('jalon_id', $jalon->id)->count());
    }

    public function test_pending_funding_is_not_reported_as_deficit_in_report_mode(): void
    {
        $this->fundOtherMission(80000);
        $jalon = $this->hybridJalon();
        $this->confirmedJalonPayment($jalon);
        $this->legacyRelease($jalon);

        // Le financement en attente comblera le déficit : pas de fausse alerte.
        $this->artisan('prosartisan:reconcile-hybrid-jalons')
            ->doesntExpectOutputToContain("Mission #{$jalon->mission_id} : déficit")
            ->expectsOutputToContain('Missions en déficit (arbitrage manuel) : 0')
            ->assertFailed();
    }
}
