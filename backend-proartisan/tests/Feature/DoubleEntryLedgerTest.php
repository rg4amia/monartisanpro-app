<?php

namespace Tests\Feature;

use App\Models\DoubleEntryLedgerEntry;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\User;
use App\Services\DoubleEntryLedgerService;
use App\States\Mission\InProgressState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoubleEntryLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $artisan;
    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 750,
        ]);

        $this->mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Chantier Rénovation Double-Entry Test',
            'status' => InProgressState::class,
            'montant_total' => 200000,
            'montant_materiaux' => 120000,
            'montant_mo' => 80000,
            'ratio_materiaux' => 0.60,
        ]);
    }

    public function test_escrow_funding_creates_balanced_double_entries(): void
    {
        $ledgerService = app(DoubleEntryLedgerService::class);

        $entries = $ledgerService->recordEscrowFunding(
            $this->mission,
            80000,
            120000,
            999
        );

        $this->assertCount(2, $entries);

        // Vérification de la persistance en base
        $this->assertDatabaseHas('double_entry_ledger_entries', [
            'mission_id' => $this->mission->id,
            'account_source' => DoubleEntryLedgerService::ACCOUNT_CLIENT_ESCROW,
            'account_destination' => DoubleEntryLedgerService::ACCOUNT_PLATFORM_ESCROW_MO,
            'amount' => 80000,
            'currency' => 'XOF',
        ]);

        $this->assertDatabaseHas('double_entry_ledger_entries', [
            'mission_id' => $this->mission->id,
            'account_source' => DoubleEntryLedgerService::ACCOUNT_CLIENT_ESCROW,
            'account_destination' => DoubleEntryLedgerService::ACCOUNT_PLATFORM_ESCROW_MATERIALS,
            'amount' => 120000,
            'currency' => 'XOF',
        ]);

        // Vérification des soldes
        $this->assertEquals(80000, $ledgerService->getAccountBalance(DoubleEntryLedgerService::ACCOUNT_PLATFORM_ESCROW_MO, $this->mission->id));
        $this->assertEquals(120000, $ledgerService->getAccountBalance(DoubleEntryLedgerService::ACCOUNT_PLATFORM_ESCROW_MATERIALS, $this->mission->id));
    }

    public function test_milestone_release_transfers_from_escrow_to_artisan_cashable(): void
    {
        $ledgerService = app(DoubleEntryLedgerService::class);

        // 1. Financement initial
        $ledgerService->recordEscrowFunding($this->mission, 80000, 120000);

        // 2. Libération jalon
        $jalon = Jalon::create([
            'mission_id' => $this->mission->id,
            'ordre' => 1,
            'description' => 'Jalon 1 Décapage',
            'montant' => 40000,
            'statut' => 'paye',
        ]);

        $entry = $ledgerService->recordMilestoneRelease($jalon, 40000);

        $this->assertEquals(DoubleEntryLedgerService::ACCOUNT_PLATFORM_ESCROW_MO, $entry->account_source);
        $this->assertEquals(DoubleEntryLedgerService::ACCOUNT_ARTISAN_CASHABLE, $entry->account_destination);
        $this->assertEquals(40000, $entry->amount);

        // Solde restant en séquestre MO de la mission : 80 000 - 40 000 = 40 000 FCFA
        $this->assertEquals(40000, $ledgerService->getAccountBalance(DoubleEntryLedgerService::ACCOUNT_PLATFORM_ESCROW_MO, $this->mission->id));
        $this->assertEquals(40000, $ledgerService->getAccountBalance(DoubleEntryLedgerService::ACCOUNT_ARTISAN_CASHABLE, $this->mission->id));
    }

    public function test_ledger_integrity_audit_command(): void
    {
        $ledgerService = app(DoubleEntryLedgerService::class);
        $ledgerService->recordEscrowFunding($this->mission, 50000, 50000);

        $this->artisan('ledger:verify-integrity')
            ->assertExitCode(0)
            ->expectsOutputToContain('PARFAIT (0 écart)');
    }

    public function test_admin_can_trigger_ledger_integrity_audit_endpoint_successfully(): void
    {
        config(['prosartisan.super_admins' => ['admin@prosartisan.ci']]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@prosartisan.ci',
            'kyc_status' => 'actif',
        ]);

        $ledgerService = app(DoubleEntryLedgerService::class);
        $ledgerService->recordEscrowFunding($this->mission, 50000, 50000);

        $response = $this->actingAs($admin)
            ->from('/admin/transactions')
            ->post('/admin/ledger/verify-integrity');

        $response->assertRedirect('/admin/transactions');
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Grand Livre certifié conforme et équilibré à 100%', session('success'));
    }

    public function test_admin_ledger_integrity_audit_handles_anomaly_gracefully(): void
    {
        config(['prosartisan.super_admins' => ['admin@prosartisan.ci']]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@prosartisan.ci',
            'kyc_status' => 'actif',
        ]);

        // Insérer une écriture volontairement déséquilibrée (source == destination)
        DoubleEntryLedgerEntry::create([
            'transaction_group_id' => (string) \Illuminate\Support\Str::uuid(),
            'account_source' => DoubleEntryLedgerService::ACCOUNT_CLIENT_ESCROW,
            'account_destination' => DoubleEntryLedgerService::ACCOUNT_CLIENT_ESCROW,
            'amount' => 15000,
            'currency' => 'XOF',
            'entry_type' => 'corrupted_test_entry',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from('/admin/transactions')
            ->post('/admin/ledger/verify-integrity');

        $response->assertRedirect('/admin/transactions');
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Anomalie comptable détectée', session('error'));
    }
}
