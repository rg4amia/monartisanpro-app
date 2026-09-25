<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\SupplierCashout;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SupplierCashoutService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCashoutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createSupplier(int $initialMateriauxBalance = 0, string $kycStatus = 'actif'): User
    {
        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => $kycStatus,
            'name' => 'Quincaillerie Centrale Abidjan',
            'phone' => '+225'.rand(1000000000, 9999999999),
        ]);

        if ($initialMateriauxBalance > 0) {
            app(WalletService::class)->credit(
                $supplier,
                WalletType::WALLET_MATERIAUX,
                $initialMateriauxBalance,
                'Vente initiale de test',
                ['type' => 'test_credit']
            );
        }

        return $supplier;
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'kyc_status' => 'actif',
        ]);
    }

    public function test_non_supplier_cannot_access_supplier_cashout_endpoints(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $this->actingAs($artisan)
            ->getJson('/api/v1/supplier/cashouts')
            ->assertForbidden();

        $this->actingAs($artisan)
            ->postJson('/api/v1/supplier/cashouts', [
                'montant_brut' => 10000,
                'mode_retrait' => 'wave',
            ])
            ->assertForbidden();
    }

    public function test_supplier_without_active_kyc_cannot_request_cashout(): void
    {
        $supplier = $this->createSupplier(50000, 'en_attente');

        $this->actingAs($supplier)
            ->postJson('/api/v1/supplier/cashouts', [
                'montant_brut' => 10000,
                'mode_retrait' => 'wave',
            ])
            ->assertForbidden();
    }

    public function test_supplier_cannot_request_amount_exceeding_available_balance(): void
    {
        $supplier = $this->createSupplier(20000);

        $this->actingAs($supplier)
            ->postJson('/api/v1/supplier/cashouts', [
                'montant_brut' => 50000,
                'mode_retrait' => 'wave',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_supplier_requests_cashout_successfully_with_commission_calculation(): void
    {
        // 100 000 FCFA dans le wallet_materiaux
        $supplier = $this->createSupplier(100000);

        $response = $this->actingAs($supplier)
            ->postJson('/api/v1/supplier/cashouts', [
                'montant_brut' => 40000,
                'mode_retrait' => 'virement_bancaire',
                'beneficiary_name' => 'Quincaillerie Centrale SARL',
                'beneficiary_phone' => '+2250701020304',
                'bank_name' => 'NSIA Banque CI',
                'bank_account_number' => 'CI0920100100234567890123',
                'notes' => 'Virement bimensuel',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.montant_brut', 40000)
            ->assertJsonPath('data.montant_commission', 1000) // 2.5% de 40 000 = 1 000 FCFA
            ->assertJsonPath('data.montant_net', 39000)
            ->assertJsonPath('data.statut', 'en_attente')
            ->assertJsonPath('data.mode_retrait', 'virement_bancaire');

        $this->assertDatabaseHas('supplier_cashouts', [
            'supplier_id' => $supplier->id,
            'montant_brut' => 40000,
            'montant_commission' => 1000,
            'montant_net' => 39000,
            'bank_name' => 'NSIA Banque CI',
            'statut' => 'en_attente',
        ]);

        // Le solde disponible est maintenant de 100 000 - 40 000 = 60 000 FCFA
        $available = app(SupplierCashoutService::class)->getAvailableBalance($supplier);
        $this->assertSame(60000, $available);
    }

    public function test_supplier_cashout_index_returns_stats_and_history(): void
    {
        $supplier = $this->createSupplier(80000);

        SupplierCashout::create([
            'reference' => 'CSH-TEST-01',
            'supplier_id' => $supplier->id,
            'beneficiary_name' => $supplier->name,
            'beneficiary_phone' => $supplier->phone,
            'montant_brut' => 20000,
            'commission_rate' => 0.025,
            'montant_commission' => 500,
            'montant_net' => 19500,
            'statut' => 'en_attente',
            'mode_retrait' => 'wave',
        ]);

        $response = $this->actingAs($supplier)
            ->getJson('/api/v1/supplier/cashouts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.wallet_materiaux', 80000)
            ->assertJsonPath('data.stats.available_balance', 60000)
            ->assertJsonPath('data.stats.pending_amount', 19500);

        $this->assertCount(1, $response->json('data.cashouts.data'));
    }

    public function test_admin_approves_and_completes_cashout_debiting_wallet_and_generating_doc(): void
    {
        $admin = $this->createAdmin();
        $supplier = $this->createSupplier(100000);

        $cashout = app(SupplierCashoutService::class)->requestCashout($supplier, [
            'montant_brut' => 50000,
            'mode_retrait' => 'wave',
            'beneficiary_name' => $supplier->name,
            'beneficiary_phone' => $supplier->phone,
        ]);

        // 1. Approbation
        $this->actingAs($admin)
            ->post("/admin/cashouts/{$cashout->id}/approve")
            ->assertRedirect();

        $this->assertSame('approuve', $cashout->fresh()->statut);

        // 2. Décaissement et complétion
        $this->actingAs($admin)
            ->post("/admin/cashouts/{$cashout->id}/complete", [
                'reference_externe' => 'WAVE-PAY-987654',
            ])
            ->assertRedirect();

        $fresh = $cashout->fresh();
        $this->assertSame('complete', $fresh->statut);
        $this->assertNotNull($fresh->processed_at);

        // Solde wallet_materiaux débité : 100 000 - 50 000 = 50 000 FCFA
        $this->assertSame(50000, $supplier->getWalletBalance(WalletType::WALLET_MATERIAUX));

        // Transaction financière officielle créée
        $this->assertDatabaseHas('transactions', [
            'user_id' => $supplier->id,
            'type' => 'paiement_fournisseur',
            'montant' => 48750, // 50 000 - 1 250 de commission
            'provider' => 'wave',
            'statut' => 'confirme',
            'reference_externe' => 'WAVE-PAY-987654',
        ]);

        // Document certifié PDF archivé
        $this->assertDatabaseHas('generated_documents', [
            'document_type' => 'recu_cashout',
            'supplier_cashout_id' => $cashout->id,
            'user_id' => $supplier->id,
            'montant' => 48750,
        ]);
    }

    public function test_admin_rejects_cashout_restoring_available_balance(): void
    {
        $admin = $this->createAdmin();
        $supplier = $this->createSupplier(50000);

        $cashout = app(SupplierCashoutService::class)->requestCashout($supplier, [
            'montant_brut' => 30000,
            'mode_retrait' => 'orange_money',
        ]);

        $this->assertSame(20000, app(SupplierCashoutService::class)->getAvailableBalance($supplier));

        $this->actingAs($admin)
            ->post("/admin/cashouts/{$cashout->id}/reject", [
                'reason' => 'Numéro Orange Money non identifiable',
            ])
            ->assertRedirect();

        $this->assertSame('rejete', $cashout->fresh()->statut);
        $this->assertStringContainsString('Numéro Orange Money non identifiable', $cashout->fresh()->notes);

        // Solde disponible rétabli à 50 000 FCFA
        $this->assertSame(50000, app(SupplierCashoutService::class)->getAvailableBalance($supplier));
    }

    public function test_batch_creation_and_reconciliation_workflow(): void
    {
        $admin = $this->createAdmin();
        $supplier = $this->createSupplier(200000);

        $c1 = app(SupplierCashoutService::class)->requestCashout($supplier, ['montant_brut' => 30000, 'mode_retrait' => 'virement_bancaire']);
        $c2 = app(SupplierCashoutService::class)->requestCashout($supplier, ['montant_brut' => 40000, 'mode_retrait' => 'virement_bancaire']);

        // Créer un lot de virement
        $this->actingAs($admin)
            ->post('/admin/cashouts/batch', [
                'cashout_ids' => [$c1->id, $c2->id],
            ])
            ->assertRedirect();

        $c1Fresh = $c1->fresh();
        $c2Fresh = $c2->fresh();
        $batchRef = $c1Fresh->batch_reference;

        $this->assertNotNull($batchRef);
        $this->assertSame($batchRef, $c2Fresh->batch_reference);
        $this->assertSame('approuve', $c1Fresh->statut);

        // Rapprochement bancaire groupé
        $this->actingAs($admin)
            ->post('/admin/cashouts/reconcile-batch', [
                'batch_reference' => $batchRef,
                'bank_tx_reference' => 'VIR-SGBCI-20260925-01',
            ])
            ->assertRedirect();

        $this->assertSame('complete', $c1->fresh()->statut);
        $this->assertSame('complete', $c2->fresh()->statut);
        $this->assertNotNull($c1->fresh()->reconciled_at);

        // Le wallet a été débité des 70 000 FCFA : 200 000 - 70 000 = 130 000 FCFA
        $this->assertSame(130000, $supplier->getWalletBalance(WalletType::WALLET_MATERIAUX));
    }

    public function test_automated_payout_j1_command(): void
    {
        $supplier = $this->createSupplier(100000);

        $cashout = app(SupplierCashoutService::class)->requestCashout($supplier, [
            'montant_brut' => 25000,
            'mode_retrait' => 'wave',
        ]);

        // Simuler une demande créée il y a 25 heures (J+1 éligible)
        $cashout->created_at = now()->subHours(25);
        $cashout->saveQuietly();

        $this->artisan('prosartisan:process-supplier-payouts-j1')
            ->expectsOutputToContain('Total demandes éligibles (> 24h & KYC actif) : 1')
            ->expectsOutputToContain('Demandes traitées et décaissées avec succès : 1')
            ->assertSuccessful();

        $this->assertSame('complete', $cashout->fresh()->statut);
        $this->assertSame(75000, $supplier->getWalletBalance(WalletType::WALLET_MATERIAUX));
    }

    public function test_supplier_receipt_download_with_idor_protection(): void
    {
        $supplier1 = $this->createSupplier(50000);
        $supplier2 = $this->createSupplier(50000);

        $cashout1 = app(SupplierCashoutService::class)->requestCashout($supplier1, [
            'montant_brut' => 20000,
            'mode_retrait' => 'wave',
        ]);

        // Compléter pour générer le reçu
        app(SupplierCashoutService::class)->completeCashout($cashout1);

        // Supplier 1 peut télécharger son reçu
        $this->actingAs($supplier1)
            ->get("/api/v1/supplier/cashouts/{$cashout1->id}/receipt")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Supplier 2 subit un 403 Forbidden (protection IDOR)
        $this->actingAs($supplier2)
            ->getJson("/api/v1/supplier/cashouts/{$cashout1->id}/receipt")
            ->assertForbidden();
    }
}
