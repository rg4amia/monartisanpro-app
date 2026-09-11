<?php

namespace Tests\Unit;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\GeneratedDocument;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\SupplierCashout;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GeneratedDocumentService;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GeneratedDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeneratedDocumentService $documentService;
    private $pdfServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdfServiceMock = Mockery::mock(PdfService::class);
        $this->documentService = new GeneratedDocumentService($this->pdfServiceMock);
    }

    public function test_it_creates_document_for_liberation_transaction(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $artisan = User::factory()->create(['role' => 'artisan']);

        $mission = Mission::create([
            'client_id'         => $client->id,
            'artisan_id'        => $artisan->id,
            'description'       => 'Mission de plomberie sanitaire',
            'status'            => 'in_progress',
            'montant_total'     => 150000,
            'montant_materiaux' => 90000,
            'montant_mo'        => 60000,
            'ratio_materiaux'   => 0.60,
        ]);

        $tx = Transaction::create([
            'mission_id'        => $mission->id,
            'user_id'           => $artisan->id,
            'type'              => 'liberation_jalon',
            'montant'           => 30000,
            'wallet_source'     => 'wallet_mo_sequestre',
            'wallet_dest'       => 'mobile_money_artisan',
            'provider'          => PaymentProvider::WAVE,
            'statut'            => PaymentStatus::CONFIRME,
            'reference_externe' => 'WAVE-TX-123456',
            'paid_at'           => now(),
        ]);

        $doc = $this->documentService->createOrGetForTransaction($tx);

        $this->assertInstanceOf(GeneratedDocument::class, $doc);
        $this->assertEquals('recu_liberation_jalon', $doc->document_type);
        $this->assertEquals(30000, $doc->montant);
        $this->assertEquals($tx->id, $doc->transaction_id);
        $this->assertEquals($artisan->id, $doc->user_id);
        $this->assertStringStartsWith('REC-WA-', $doc->reference);
    }

    public function test_it_creates_document_for_supplier_cashout(): void
    {
        $supplier = User::factory()->create(['role' => 'fournisseur']);
        $admin = User::factory()->create(['role' => 'admin']);

        $cashout = SupplierCashout::create([
            'reference'          => 'CSH-TEST-001',
            'supplier_id'        => $supplier->id,
            'montant_brut'       => 100000,
            'montant_commission' => 2500,
            'montant_net'        => 97500,
            'provider'           => 'wave',
            'statut'             => 'complete',
            'beneficiary_phone'  => '0707070707',
            'beneficiary_name'   => 'Quincaillerie Centrale',
            'processed_by'       => $admin->id,
            'processed_at'       => now(),
        ]);

        $doc = $this->documentService->createOrGetForCashout($cashout);

        $this->assertInstanceOf(GeneratedDocument::class, $doc);
        $this->assertEquals('recu_cashout', $doc->document_type);
        $this->assertEquals(97500, $doc->montant);
        $this->assertEquals($cashout->id, $doc->supplier_cashout_id);
        $this->assertEquals($supplier->id, $doc->user_id);
    }

    public function test_it_calculates_document_stats_accurately(): void
    {
        $user = User::factory()->create();

        GeneratedDocument::create([
            'reference'     => 'REC-MO-01',
            'document_type' => 'recu_liberation_jalon',
            'title'         => 'Libération Jalon 1',
            'user_id'       => $user->id,
            'montant'       => 50000,
        ]);

        GeneratedDocument::create([
            'reference'     => 'REC-MAT-01',
            'document_type' => 'recu_paiement_fournisseur',
            'title'         => 'Règlement Matériaux',
            'user_id'       => $user->id,
            'montant'       => 120000,
        ]);

        GeneratedDocument::create([
            'reference'     => 'CSH-01',
            'document_type' => 'recu_cashout',
            'title'         => 'Cash-out Quincaillerie',
            'user_id'       => $user->id,
            'montant'       => 80000,
        ]);

        GeneratedDocument::create([
            'reference'     => 'SOLV-01',
            'document_type' => 'rapport_solvabilite',
            'title'         => 'Rapport Solvabilité',
            'user_id'       => $user->id,
            'montant'       => 0,
        ]);

        $stats = $this->documentService->getStats();

        $this->assertEquals(4, $stats['total_documents']);
        $this->assertEquals(250000, $stats['total_montant_certifie']); // 50000 + 120000 + 80000
        $this->assertEquals(1, $stats['recus_jalons_mo']);
        $this->assertEquals(2, $stats['recus_quincaillerie']); // paiement_fournisseur + cashout
        $this->assertEquals(1, $stats['rapports_et_litiges']); // solvabilite
    }

    public function test_it_syncs_unindexed_transactions_and_cashouts(): void
    {
        $user = User::factory()->create();

        // 1 confirmed liberation transaction
        Transaction::create([
            'user_id'       => $user->id,
            'type'          => 'liberation_jalon',
            'montant'       => 45000,
            'wallet_source' => 'sequestre',
            'wallet_dest'   => 'artisan',
            'provider'      => PaymentProvider::ORANGE_MONEY,
            'statut'        => PaymentStatus::CONFIRME,
        ]);

        // 1 cashout
        SupplierCashout::create([
            'reference'          => 'CSH-TEST-002',
            'supplier_id'        => $user->id,
            'montant_brut'       => 50000,
            'montant_commission' => 1250,
            'montant_net'        => 48750,
            'provider'           => 'orange_money',
            'statut'             => 'complete',
            'beneficiary_phone'  => '0505050505',
            'beneficiary_name'   => 'Quincaillerie Test',
        ]);

        $synced = $this->documentService->syncAll();

        $this->assertGreaterThanOrEqual(2, $synced);
        $this->assertDatabaseHas('generated_documents', [
            'document_type' => 'recu_liberation_jalon',
            'montant'       => 45000,
        ]);
        $this->assertDatabaseHas('generated_documents', [
            'document_type' => 'recu_cashout',
            'montant'       => 48750,
        ]);
    }
}
