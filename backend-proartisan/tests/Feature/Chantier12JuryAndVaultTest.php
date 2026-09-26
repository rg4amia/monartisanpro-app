<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\DoubleEntryLedgerEntry;
use App\Models\EvidenceVault;
use App\Models\JuryReview;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use App\Services\DoubleEntryLedgerService;
use App\Services\EvidenceVaultService;
use App\Services\LitigeService;
use App\States\Mission\DisputedState;
use App\States\Mission\FundedLockedState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Chantier12JuryAndVaultTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $artisan;
    private Mission $mission;
    private Litige $litige;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
            'phone' => '+2250700000001',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 850,
            'phone' => '+2250700000002',
        ]);

        $this->mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Chantier Ragréage Sol',
            'status' => DisputedState::class,
            'montant_total' => 150000,
            'montant_mo' => 100000,
            'montant_materiaux' => 50000,
            'ratio_materiaux' => 0.3333,
        ]);

        $this->litige = Litige::create([
            'mission_id' => $this->mission->id,
            'declencheur_id' => $this->client->id,
            'type' => 'malfacon',
            'description' => 'Fissures anormales constatées après ragréage.',
            'statut' => 'ouvert',
            'workflow_step' => 'instruction',
        ]);
    }

    public function test_evidence_vault_seals_and_verifies_integrity(): void
    {
        $vaultService = app(EvidenceVaultService::class);
        $file = UploadedFile::fake()->create('constat_fissure.jpg', 500, 'image/jpeg');

        $sealed = $vaultService->seal(
            $file,
            $this->client,
            [
                'evidence_type' => 'litige',
                'mission_id' => $this->mission->id,
                'litige_id' => $this->litige->id,
                'gps_lat' => 5.3456,
                'gps_lng' => -4.0123,
                'device_fingerprint' => 'FP-TEST-123456',
            ]
        );

        $this->assertNotNull($sealed->id);
        $this->assertEquals(64, strlen($sealed->sha256_hash));
        $this->assertEquals('litige', $sealed->evidence_type);
        $this->assertEquals(5.3456, $sealed->gps_lat);
        $this->assertFalse($sealed->is_tampered);

        // Vérification d'intégrité initiale : OK
        $isValid = $vaultService->verifyIntegrity($sealed);
        $this->assertTrue($isValid);
        $this->assertFalse($sealed->fresh()->is_tampered);

        // Génération du certificat d'intégrité
        $cert = $vaultService->generateCertificate($sealed);
        $this->assertEquals($sealed->sha256_hash, $cert['sha256_hash']);
        $this->assertTrue($cert['integrity_status']['is_valid']);
        $this->assertEquals('AUTHENTIC_AND_VERIFIED', $cert['seal_verification']);
    }

    public function test_evidence_vault_detects_tampering(): void
    {
        $vaultService = app(EvidenceVaultService::class);
        $file = UploadedFile::fake()->create('preuve_originale.png', 300, 'image/png');

        $sealed = $vaultService->seal(
            $file,
            $this->client,
            [
                'evidence_type' => 'litige',
                'mission_id' => $this->mission->id,
                'litige_id' => $this->litige->id,
            ]
        );

        // Simulation d'une altération malveillante du fichier sur le disque
        Storage::disk('public')->put($sealed->file_path, 'CONTENU_MODIFIE_ILLICITE');

        $isValid = $vaultService->verifyIntegrity($sealed);
        $this->assertFalse($isValid);

        $fresh = $sealed->fresh();
        $this->assertTrue($fresh->is_tampered);
        $this->assertNotNull($fresh->tampered_detected_at);
    }

    public function test_cli_vault_verify_integrity_command(): void
    {
        $vaultService = app(EvidenceVaultService::class);
        $file = UploadedFile::fake()->create('document_chantier.pdf', 200, 'application/pdf');

        $vaultService->seal(
            $file,
            $this->artisan,
            [
                'evidence_type' => 'jalon',
                'mission_id' => $this->mission->id,
            ]
        );

        $exitCode = Artisan::call('vault:verify-integrity');
        $this->assertEquals(0, $exitCode);
    }

    public function test_jury_prosartisan_assignment_assigns_three_eligible_jurors_with_5000_fcfa(): void
    {
        // Créer 4 artisans éligibles (score >= 800)
        $jurors = User::factory()->count(4)->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 820,
        ]);

        $litigeService = app(LitigeService::class);
        $litigeService->assignJury($this->litige);

        $this->assertEquals('pending_jury', $this->litige->fresh()->jury_status);
        $this->assertEquals('jury', $this->litige->fresh()->workflow_step);

        $reviews = JuryReview::where('litige_id', $this->litige->id)->get();
        $this->assertCount(3, $reviews);

        foreach ($reviews as $review) {
            $this->assertEquals(5000, $review->compensation);
            $this->assertEquals('assigned', $review->status);
            $this->assertNotNull($review->expires_at);
            $this->assertNotEquals($this->artisan->id, $review->jure_id);
        }
    }

    public function test_juror_vote_credits_wallet_and_records_double_entry_ledger(): void
    {
        $juror = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 840,
            'wallet_mo' => 10000,
        ]);

        $review = JuryReview::create([
            'litige_id' => $this->litige->id,
            'jure_id' => $juror->id,
            'compensation' => 5000,
            'status' => 'assigned',
            'assigned_at' => now(),
            'expires_at' => now()->addHours(48),
        ]);

        $litigeService = app(LitigeService::class);
        $litigeService->submitJuryVote(
            litige: $this->litige,
            jure: $juror,
            verdict: 'CONFORME',
            splitPercentage: 100,
            technicalComment: 'Ouvrage réalisé dans les règles de l\'art. Les micro-fissures sont un retrait de séchage normal.'
        );

        $freshReview = $review->fresh();
        $this->assertEquals('voted', $freshReview->status);
        $this->assertEquals('CONFORME', $freshReview->verdict);
        $this->assertTrue($freshReview->compensation_paid);

        // Wallet crédité de 5 000 FCFA
        $this->assertEquals(15000, $juror->fresh()->wallet_mo);

        // Écriture comptable équilibrée
        $ledgerEntry = DoubleEntryLedgerEntry::where('entry_type', 'juror_compensation')
            ->where('user_id', $juror->id)
            ->first();

        $this->assertNotNull($ledgerEntry);
        $this->assertEquals(5000, $ledgerEntry->amount);
        $this->assertEquals(DoubleEntryLedgerService::ACCOUNT_PLATFORM_COMMISSION, $ledgerEntry->account_source);
        $this->assertEquals(DoubleEntryLedgerService::ACCOUNT_ARTISAN_CASHABLE, $ledgerEntry->account_destination);
    }

    public function test_jury_consensus_two_thirds_resolution(): void
    {
        $jurors = User::factory()->count(3)->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 850,
        ]);

        $litigeService = app(LitigeService::class);
        $litigeService->assignJury($this->litige);

        // Juré 1 vote NON_CONFORME
        $litigeService->submitJuryVote(
            litige: $this->litige,
            jure: $jurors[0],
            verdict: 'NON_CONFORME',
            splitPercentage: 0,
            technicalComment: 'Ragréage non conforme aux DTU.'
        );

        // À 1 vote, pas encore de décision qualifiée
        $this->assertNull($this->litige->fresh()->jury_consensus);

        // Juré 2 vote NON_CONFORME (Majorité qualifiée 2/3 atteinte !)
        $litigeService->submitJuryVote(
            litige: $this->litige,
            jure: $jurors[1],
            verdict: 'NON_CONFORME',
            splitPercentage: 0,
            technicalComment: 'Épaisseur insuffisante.'
        );

        $freshLitige = $this->litige->fresh();
        $this->assertEquals('NON_CONFORME', $freshLitige->jury_consensus);
        $this->assertEquals('jury_decided', $freshLitige->jury_status);
        $this->assertEquals(0, $freshLitige->jury_recommended_split);
    }

    public function test_expire_overdue_jury_reviews_replaces_juror(): void
    {
        $juror1 = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 850,
        ]);

        $replacementCandidate = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 830,
        ]);

        // Review expirée (> 48h)
        $expiredReview = JuryReview::create([
            'litige_id' => $this->litige->id,
            'jure_id' => $juror1->id,
            'compensation' => 5000,
            'status' => 'assigned',
            'assigned_at' => now()->subHours(50),
            'expires_at' => now()->subHours(2),
        ]);

        $exitCode = Artisan::call('jury:expire-overdue');
        $this->assertEquals(0, $exitCode);

        $this->assertEquals('expired', $expiredReview->fresh()->status);

        // Un remplaçant a été assigné
        $newReview = JuryReview::where('litige_id', $this->litige->id)
            ->where('status', 'assigned')
            ->first();

        $this->assertNotNull($newReview);
        $this->assertEquals($replacementCandidate->id, $newReview->jure_id);
    }
}
