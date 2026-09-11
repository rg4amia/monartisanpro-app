<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\GeneratedDocument;
use App\Models\Mission;
use App\Models\SupplierCashout;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role'       => 'admin',
            'kyc_status' => 'actif',
        ]);
    }

    public function test_admin_can_list_documents_via_json_endpoint(): void
    {
        GeneratedDocument::create([
            'reference'     => 'REC-TEST-001',
            'document_type' => 'recu_liberation_jalon',
            'title'         => 'Reçu de test',
            'montant'       => 25000,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/documents');

        $response->assertOk()
            ->assertJsonStructure([
                'documents' => ['data'],
                'stats'     => [
                    'total_documents',
                    'total_montant_certifie',
                    'recus_jalons_mo',
                    'recus_quincaillerie',
                    'rapports_et_litiges',
                ],
            ]);

        $this->assertEquals('REC-TEST-001', $response->json('documents.data.0.reference'));
    }

    public function test_admin_can_trigger_documents_sync(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/documents/sync');

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_admin_can_download_document_pdf(): void
    {
        // Mock PdfService so we don't need real disk PDF writing in test
        $tempPdf = tempnam(sys_get_temp_dir(), 'test_pdf_') . '.pdf';
        file_put_contents($tempPdf, '%PDF-1.4 test content');

        $doc = GeneratedDocument::create([
            'reference'     => 'REC-DOWNLOAD-01',
            'document_type' => 'recu_liberation_jalon',
            'title'         => 'Reçu Téléchargeable',
            'montant'       => 50000,
            'file_path'     => $tempPdf,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/documents/{$doc->id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        if (file_exists($tempPdf)) {
            @unlink($tempPdf);
        }
    }
}
