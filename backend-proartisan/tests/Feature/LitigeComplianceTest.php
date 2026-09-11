<?php

namespace Tests\Feature;

use App\Models\Litige;
use App\Models\LitigeEvidence;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LitigeComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_open_litige_and_freeze_funds(): void
    {
        [$client, $artisan, $mission] = $this->makeMission();

        $this->actingAs($client)
            ->postJson('/api/v1/litiges', [
                'mission_id' => $mission->id,
                'motif' => 'Travail inacheve',
                'description' => 'Le chantier est inacheve et les finitions sont non conformes sur plusieurs points.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'ouvert')
            ->assertJsonPath('data.workflowStep', 'preuves');

        $mission->refresh();

        $this->assertSame('disputed', (string) $mission->status);
        $this->assertTrue($mission->funds_frozen);
        $this->assertDatabaseCount('litiges', 1);
    }

    public function test_parties_can_submit_evidence_and_case_moves_to_arbitrage(): void
    {
        Storage::fake('public');

        [$client, $artisan, $mission] = $this->makeMission();

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Travail mal fait',
            'description' => 'Le rendu n est pas conforme au devis.',
            'statut' => 'ouvert',
            'workflow_step' => 'preuves',
            'funds_locked_at' => now(),
            'evidence_deadline_at' => now()->addHours(48),
            'arbitration_deadline_at' => now()->addHours(72),
        ]);

        $clientResponse = $this->actingAs($client)->post(
            "/api/v1/litiges/{$litige->id}/preuves",
            [
                'photos' => [
                    [
                        'photo' => UploadedFile::fake()->image('client-1.jpg', 100, 100),
                        'latitude' => 5.348,
                        'longitude' => -4.027,
                        'description' => 'Fuite visible',
                    ],
                    [
                        'photo' => UploadedFile::fake()->image('client-2.jpg', 200, 200),
                        'latitude' => 5.349,
                        'longitude' => -4.028,
                        'description' => 'Mauvaise finition',
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $clientResponse
            ->assertOk()
            ->assertJsonPath('data.evidenceCounts.client', 2);

        $artisanResponse = $this->actingAs($artisan)->post(
            "/api/v1/litiges/{$litige->id}/preuves",
            [
                'photos' => [
                    [
                        'photo' => UploadedFile::fake()->image('artisan-1.jpg', 300, 300),
                        'latitude' => 5.35,
                        'longitude' => -4.03,
                        'description' => 'Photo de fin de chantier',
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $artisanResponse
            ->assertOk()
            ->assertJsonPath('data.workflowStep', 'arbitrage')
            ->assertJsonPath('data.statut', 'en_cours');

        $this->assertDatabaseCount('litige_preuves', 3);
    }

    public function test_sla_auto_refunds_client_when_artisan_does_not_respond(): void
    {
        Storage::fake('public');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        [$client, $artisan, $mission] = $this->makeMission(walletMateriaux: 65000, walletMo: 35000);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Travail inacheve',
            'description' => 'Le chantier est inacheve et le client a fourni ses preuves.',
            'statut' => 'ouvert',
            'workflow_step' => 'preuves',
            'funds_locked_at' => now()->subHours(49),
            'evidence_deadline_at' => now()->subMinute(),
            'arbitration_deadline_at' => now()->addHours(23),
        ]);

        LitigeEvidence::create([
            'litige_id' => $litige->id,
            'user_id' => $client->id,
            'partie' => 'client',
            'description' => 'Preuve 1',
            'media_url' => '/storage/photos/autres/preuve-1.jpg',
            'media_path' => 'photos/autres/preuve-1.jpg',
            'latitude' => 5.348,
            'longitude' => -4.027,
            'taken_at' => now()->subHours(2),
        ]);

        LitigeEvidence::create([
            'litige_id' => $litige->id,
            'user_id' => $client->id,
            'partie' => 'client',
            'description' => 'Preuve 2',
            'media_url' => '/storage/photos/autres/preuve-2.jpg',
            'media_path' => 'photos/autres/preuve-2.jpg',
            'latitude' => 5.349,
            'longitude' => -4.028,
            'taken_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin)
            ->postJson("/api/v1/litiges/{$litige->id}/evaluate-sla")
            ->assertOk()
            ->assertJsonPath('data.statut', 'resolu')
            ->assertJsonPath('data.decision', 'client')
            ->assertJsonPath('data.resolutionReason', 'absence_preuves_artisan');

        $this->assertSame('cancelled', (string) $mission->fresh()->status);
        $this->assertFalse((bool) $mission->fresh()->funds_frozen);
        $this->assertSame(0, $artisan->fresh()->wallet_materiaux);
        $this->assertSame(0, $artisan->fresh()->wallet_mo);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $client->id,
            'type' => 'remboursement',
            'montant' => 100000,
            'statut' => 'confirme',
        ]);
    }

    public function test_admin_can_apply_mixed_decision_refunding_only_labor(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        [$client, $artisan, $mission] = $this->makeMission(walletMateriaux: 65000, walletMo: 35000);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Melange materiaux main oeuvre',
            'description' => 'Les materiaux sont conformes mais la pose est defectueuse.',
            'statut' => 'en_cours',
            'workflow_step' => 'arbitrage',
            'funds_locked_at' => now(),
            'evidence_deadline_at' => now()->subHours(2),
            'arbitration_started_at' => now()->subHour(),
            'arbitration_deadline_at' => now()->addHours(20),
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litige->id}/arbitrage", [
                'decision' => 'mixte',
            ])
            ->assertOk()
            ->assertJsonPath('data.decision', 'mixte')
            ->assertJsonPath('data.resolutionPayload.refund_mo', 35000)
            ->assertJsonPath('data.resolutionPayload.refund_materiaux', 0);

        $mission->refresh();
        $artisan->refresh();

        $this->assertSame('completed', (string) $mission->status);
        $this->assertFalse((bool) $mission->funds_frozen);
        $this->assertSame(0, $artisan->wallet_materiaux);
        $this->assertSame(0, $artisan->wallet_mo);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $client->id,
            'type' => 'remboursement',
            'montant' => 35000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $artisan->id,
            'type' => 'credit',
            'montant' => 65000,
        ]);
    }

    public function test_artisan_is_banned_after_three_lost_disputes_in_six_months(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 5,
            'wallet_materiaux' => 65000,
            'wallet_mo' => 35000,
        ]);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        for ($i = 1; $i <= 2; $i++) {
            $pastMission = Mission::create([
                'client_id' => $client->id,
                'artisan_id' => $artisan->id,
                'description' => "Mission passee {$i}",
                'status' => 'cancelled',
                'montant_total' => 10000,
                'montant_materiaux' => 5000,
                'montant_mo' => 5000,
                'ratio_materiaux' => 0.5,
            ]);

            Litige::create([
                'mission_id' => $pastMission->id,
                'declencheur_id' => $client->id,
                'type' => 'client',
                'motif' => 'Historique',
                'description' => 'Litige perdu auparavant.',
                'statut' => 'resolu',
                'workflow_step' => 'resolu',
                'decision' => 'client',
                'resolu_at' => now()->subMonths($i),
            ]);
        }

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission courante',
            'status' => 'disputed',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
            'funds_frozen' => true,
        ]);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Troisieme litige',
            'description' => 'Troisieme litige perdu par l artisan.',
            'statut' => 'en_cours',
            'workflow_step' => 'arbitrage',
            'funds_locked_at' => now(),
            'evidence_deadline_at' => now()->subHours(3),
            'arbitration_started_at' => now()->subHours(2),
            'arbitration_deadline_at' => now()->addHours(20),
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litige->id}/arbitrage", [
                'decision' => 'client',
            ])
            ->assertOk()
            ->assertJsonPath('data.sanctions.0.account_status', 'banni');

        $artisan->refresh();

        $this->assertSame('banni', $artisan->account_status);
        $this->assertSame(0, $artisan->score_prosartisan);
        $this->assertNotNull($artisan->blocked_at);
    }

    public function test_arbitrating_for_artisan_generates_disbursement_invoice(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        [$client, $artisan, $mission] = $this->makeMission(walletMateriaux: 65000, walletMo: 35000);

        $litige = Litige::create([
            'mission_id'               => $mission->id,
            'declencheur_id'           => $client->id,
            'type'                     => 'client',
            'motif'                    => 'Facture test',
            'description'              => 'Litige pour vérifier la génération de facture de décaissement.',
            'statut'                   => 'en_cours',
            'workflow_step'            => 'arbitrage',
            'funds_locked_at'          => now(),
            'evidence_deadline_at'     => now()->subHours(3),
            'arbitration_started_at'   => now()->subHour(),
            'arbitration_deadline_at'  => now()->addHours(20),
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litige->id}/arbitrage", [
                'decision' => 'artisan',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.decision', 'artisan')
            ->assertJsonPath('data.statut', 'resolu');

        $litige->refresh();

        // Verify the invoice path was stored in resolution_payload
        $this->assertNotNull($litige->resolution_payload);
        $payload = is_string($litige->resolution_payload)
            ? json_decode($litige->resolution_payload, true)
            : $litige->resolution_payload;
        $this->assertArrayHasKey('invoice_path', $payload);
        $this->assertNotNull($payload['invoice_path']);

        // Verify the PDF file exists on disk
        $this->assertFileExists($payload['invoice_path']);

        // Verify mission status is completed
        $mission->refresh();
        $this->assertSame('completed', (string) $mission->status);
        $this->assertFalse((bool) $mission->funds_frozen);

        // Clean up generated file
        if (file_exists($payload['invoice_path'])) {
            @unlink($payload['invoice_path']);
        }
    }

    public function test_admin_can_download_disbursement_invoice(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        [$client, $artisan, $mission] = $this->makeMission(walletMateriaux: 65000, walletMo: 35000);

        // Create a dummy invoice file
        $invoiceDir = storage_path('app/public/invoices');
        if (!file_exists($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        $invoicePath = $invoiceDir . "/test_invoice_{$mission->id}.pdf";
        file_put_contents($invoicePath, 'dummy PDF content');

        $litige = Litige::create([
            'mission_id'             => $mission->id,
            'declencheur_id'         => $client->id,
            'type'                   => 'client',
            'motif'                  => 'Download test',
            'description'            => 'Test de téléchargement de facture.',
            'statut'                 => 'resolu',
            'workflow_step'          => 'resolu',
            'decision'               => 'artisan',
            'resolu_at'              => now(),
            'resolution_payload'     => ['invoice_path' => $invoicePath],
        ]);

        $response = $this->actingAs($admin)
            ->get("/admin/litiges/{$litige->id}/invoice");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        // Clean up
        if (file_exists($invoicePath)) {
            @unlink($invoicePath);
        }
    }

    public function test_disbursement_invoice_generated_when_arbitration_favors_artisan(): void
    {
        // Create a real temp file to simulate the generated PDF
        $invoicesDir = storage_path('app/public/invoices');
        if (!file_exists($invoicesDir)) {
            mkdir($invoicesDir, 0755, true);
        }
        $fakePdfPath = $invoicesDir . '/test_disbursement_invoice.pdf';
        file_put_contents($fakePdfPath, '%PDF-1.4 fake content');

        // Mock PdfService to return the fake path without requiring DomPDF
        $pdfMock = $this->mock(\App\Services\PdfService::class, function ($mock) use ($fakePdfPath) {
            $mock->shouldReceive('generateDisbursementInvoice')
                ->once()
                ->andReturn($fakePdfPath);
        });

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        [$client, $artisan, $mission] = $this->makeMission(walletMateriaux: 65000, walletMo: 35000);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Travail conteste',
            'description' => 'Le client conteste mais les preuves sont en faveur de l artisan.',
            'statut' => 'en_cours',
            'workflow_step' => 'arbitrage',
            'funds_locked_at' => now(),
            'evidence_deadline_at' => now()->subHours(2),
            'arbitration_started_at' => now()->subHour(),
            'arbitration_deadline_at' => now()->addHours(20),
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litige->id}/arbitrage", [
                'decision' => 'artisan',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.decision', 'artisan')
            ->assertJsonPath('data.statut', 'resolu');

        // Mission should be completed with funds unfrozen
        $mission->refresh();
        $this->assertSame('completed', (string) $mission->status);
        $this->assertFalse((bool) $mission->funds_frozen);

        // Invoice path must be stored in resolution_payload
        $litige->refresh();
        $payload = $litige->resolution_payload;
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('invoice_path', $payload);
        $this->assertSame($fakePdfPath, $payload['invoice_path']);

        // PDF file must exist on disk
        $this->assertFileExists($payload['invoice_path']);

        // Admin can download the invoice via the web route
        $downloadResponse = $this->actingAs($admin)
            ->get("/admin/litiges/{$litige->id}/invoice");

        $downloadResponse->assertOk();
        $downloadResponse->assertDownload();

        // Cleanup
        @unlink($fakePdfPath);
    }

    /**
     * Parcours complet du module litige exclusivement via les routes HTTP réelles :
     * ouverture -> dépôt des preuves des deux parties -> bascule automatique en
     * arbitrage -> décision "gel" (visite référent, jamais couverte par les autres
     * tests) -> décision finale après visite terrain. Vérifie à chaque étape l'état
     * de la mission, le gel/dégel des fonds, les notifications et les soldes.
     */
    public function test_full_dispute_lifecycle_via_api_open_evidence_gel_then_final_resolution(): void
    {
        Storage::fake('public');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        /** @var User $referent */
        $referent = User::factory()->create(['role' => 'referent', 'kyc_status' => 'actif']);

        [$client, $artisan, $mission] = $this->makeMission(walletMateriaux: 65000, walletMo: 35000);
        $client->update(['score_prosartisan' => 50]);

        // 1. Le client ouvre le litige via l'API.
        $openResponse = $this->actingAs($client)
            ->postJson('/api/v1/litiges', [
                'mission_id' => $mission->id,
                'motif' => 'Travail non conforme',
                'description' => 'Plusieurs finitions sont non conformes au devis initial et le chantier presente des fuites.',
            ]);

        $openResponse->assertCreated()
            ->assertJsonPath('data.statut', 'ouvert')
            ->assertJsonPath('data.workflowStep', 'preuves');

        $litigeId = $openResponse->json('data.id');

        $mission->refresh();
        $this->assertSame('disputed', (string) $mission->status);
        $this->assertTrue($mission->funds_frozen);

        // 2. Le client dépose ses 2 preuves géolocalisées (seuil minimum côté client).
        $this->actingAs($client)->post(
            "/api/v1/litiges/{$litigeId}/preuves",
            [
                'photos' => [
                    ['photo' => UploadedFile::fake()->image('client-1.jpg', 100, 100), 'latitude' => 5.348, 'longitude' => -4.027, 'description' => 'Fuite visible'],
                    ['photo' => UploadedFile::fake()->image('client-2.jpg', 200, 200), 'latitude' => 5.349, 'longitude' => -4.028, 'description' => 'Finition non conforme'],
                ],
            ],
            ['Accept' => 'application/json']
        )->assertOk()->assertJsonPath('data.workflowStep', 'preuves');

        // 3. L'artisan dépose sa preuve -> bascule automatique en arbitrage.
        $evidenceResponse = $this->actingAs($artisan)->post(
            "/api/v1/litiges/{$litigeId}/preuves",
            [
                'photos' => [
                    ['photo' => UploadedFile::fake()->image('artisan-1.jpg', 300, 300), 'latitude' => 5.35, 'longitude' => -4.03, 'description' => 'Photo de fin de chantier'],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $evidenceResponse->assertOk()
            ->assertJsonPath('data.workflowStep', 'arbitrage')
            ->assertJsonPath('data.statut', 'en_cours');

        // 4. Cas ambigu : l'admin gèle le dossier et déclenche la visite terrain du référent.
        $gelResponse = $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litigeId}/arbitrage", [
                'decision' => 'gel',
                'notes' => 'Cas ambigu, visite terrain necessaire avant decision finale.',
            ]);

        $gelResponse->assertOk()
            ->assertJsonPath('data.decision', 'gel')
            ->assertJsonPath('data.statut', 'en_cours')
            ->assertJsonPath('data.workflowStep', 'visite_referent');

        $mission->refresh();
        $this->assertSame('disputed', (string) $mission->status, 'La mission doit rester gelée pendant la visite référent.');
        $this->assertTrue($mission->funds_frozen);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $referent->id,
            'type' => 'litige',
            'title' => 'Visite référent requise',
        ]);

        // 5. Après la visite terrain (simulée), l'admin rend la décision finale en faveur de l'artisan.
        $finalResponse = $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litigeId}/arbitrage", [
                'decision' => 'artisan',
                'notes' => 'Visite terrain confirmant la conformite des travaux.',
            ]);

        $finalResponse->assertOk()
            ->assertJsonPath('data.decision', 'artisan')
            ->assertJsonPath('data.statut', 'resolu')
            ->assertJsonPath('data.workflowStep', 'resolu');

        $mission->refresh();
        $artisan->refresh();
        $client->refresh();

        $this->assertSame('completed', (string) $mission->status);
        $this->assertFalse((bool) $mission->funds_frozen);

        // Le séquestre matériaux + MO est intégralement libéré vers l'artisan.
        $this->assertSame(0, $artisan->wallet_materiaux);
        $this->assertSame(0, $artisan->wallet_mo);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $artisan->id,
            'type' => 'credit',
            'montant' => 65000,
        ]);
        // La libération de la main d'œuvre suit le même circuit qu'une libération
        // de jalon classique (type 'liberation_jalon'), la part matériaux un crédit direct.
        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $artisan->id,
            'type' => 'liberation_jalon',
            'montant' => 35000,
        ]);

        // Sanction : le client déclencheur perdant voit son score légèrement pénalisé.
        $this->assertSame(49, $client->score_prosartisan);

        // Reçu de décaissement généré et téléchargeable pour l'artisan gagnant.
        $litige = Litige::findOrFail($litigeId);
        $this->assertIsArray($litige->resolution_payload);
        $this->assertNotNull($litige->resolution_payload['invoice_path']);
        $this->assertFileExists($litige->resolution_payload['invoice_path']);

        if (file_exists($litige->resolution_payload['invoice_path'])) {
            @unlink($litige->resolution_payload['invoice_path']);
        }
    }

    private function makeMission(int $walletMateriaux = 0, int $walletMo = 0): array
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'wallet_materiaux' => $walletMateriaux,
            'wallet_mo' => $walletMo,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission test litige',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
            'funds_frozen' => false,
        ]);

        return [$client, $artisan, $mission];
    }
}
