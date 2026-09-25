<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\CreditApplication;
use App\Models\Devis;
use App\Models\Evaluation;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ScoreService;
use App\Services\WalletService;
use App\States\Mission\CompletedState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo;
use Tests\TestCase;

class MicroCreditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un artisan avec un score inférieur au seuil de 700 est refusé au micro-crédit.
     */
    public function test_artisan_with_score_below_700_is_rejected_from_micro_credit(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 0,
        ]);

        $response = $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'eligible' => false,
                    'has_active_credit' => false,
                    'current_score' => 0,
                    'required_score' => 700,
                ],
            ]);

        $this->assertStringContainsString('700', $response->json('data.reason'));
    }

    /**
     * Un artisan sans KYC actif ne peut pas accéder au micro-crédit.
     */
    public function test_artisan_without_active_kyc_cannot_access_micro_credit(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'en_attente',
            'score_prosartisan' => 850,
        ]);

        $response = $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'eligible' => false,
                    'has_active_credit' => false,
                ],
            ]);

        $this->assertStringContainsString('KYC', $response->json('data.reason'));
    }

    /**
     * Un artisan éligible (score >= 700 calculé depuis le ledger) bénéficie d'un plafond dynamique :
     * 50 000 FCFA + (score - 700) * 1 500 FCFA.
     */
    public function test_eligible_artisan_has_dynamic_credit_limit_calculated_from_ledger(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 0,
        ]);

        // Création de 10 missions terminées avec évaluations 5 étoiles (score maximum 1000)
        for ($i = 1; $i <= 10; $i++) {
            $mission = Mission::create([
                'client_id' => $client->id,
                'artisan_id' => $artisan->id,
                'description' => 'Mission test travaux',
                'status' => CompletedState::class,
                'montant_total' => 50000,
                'montant_materiaux' => 20000,
                'montant_mo' => 30000,
                'ratio_materiaux' => 0.40,
                'accepted_at' => now(),
            ]);

            Evaluation::create([
                'mission_id' => $mission->id,
                'evaluateur_id' => $client->id,
                'evalue_id' => $artisan->id,
                'note' => 5,
                'fiabilite' => 5.0,
                'integrite' => 5.0,
                'qualite' => 5.0,
                'reactivite' => 5.0,
                'commentaire' => 'Excellent travail parfait',
            ]);
        }

        app(ScoreService::class)->recalculateFromLedger($artisan);
        $score = $artisan->fresh()->score_prosartisan;
        $this->assertSame(1000, $score);

        $response = $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'eligible' => true,
                    'score_prosartisan' => 1000,
                    'max_amount' => 500000, // 50 000 + (1000 - 700) * 1500 = 500 000 FCFA
                    'has_active_credit' => false,
                ],
            ]);
    }

    /**
     * Une demande de crédit pour un montant supérieur au plafond est rejetée (HTTP 422).
     */
    public function test_artisan_cannot_apply_for_amount_exceeding_max_limit(): void
    {
        $artisan = $this->createEligibleArtisanWithScore(1000); // plafond 500 000 FCFA

        $response = $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', [
                'amount' => 600000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('supérieur au maximum', $response->json('message'));
        $this->assertDatabaseCount('credit_applications', 0);
    }

    /**
     * Demande valide : crédit déboursé avec génération d'une transaction financière de type 'credit'.
     */
    public function test_artisan_applies_and_credit_is_disbursed_with_financial_transaction(): void
    {
        $artisan = $this->createEligibleArtisanWithScore(1000);
        $artisan->update(['preferred_payment_provider' => 'wave']);

        $response = $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', [
                'amount' => 150000,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 150000,
                    'status' => 'debourse',
                    'score_prosartisan_at_application' => 1000,
                ],
            ]);

        $application = CreditApplication::where('user_id', $artisan->id)->first();
        $this->assertNotNull($application);
        $this->assertSame(150000, $application->amount);
        $this->assertSame('debourse', $application->status);
        $this->assertNotNull($application->approved_at);
        $this->assertNotNull($application->disbursed_at);
        $this->assertSame(150000, $application->remaining_amount);

        // Vérification de la transaction financière
        $transaction = Transaction::where('user_id', $artisan->id)
            ->where('type', 'credit')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame(150000, $transaction->montant);
        $this->assertSame('wave', $transaction->provider instanceof \BackedEnum ? $transaction->provider->value : (string) $transaction->provider);
        $this->assertSame('confirme', $transaction->statut instanceof \BackedEnum ? $transaction->statut->value : (string) $transaction->statut);
        $this->assertSame('microfinance_partner', $transaction->wallet_source);
    }

    /**
     * Un artisan ayant un crédit actif non soldé ne peut pas en demander un nouveau.
     */
    public function test_artisan_with_active_credit_cannot_request_another_credit(): void
    {
        $artisan = $this->createEligibleArtisanWithScore(1000);

        // Premier crédit de 100 000 FCFA
        $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', ['amount' => 100000])
            ->assertStatus(201);

        // Nouvelle vérification d'éligibilité
        $eligibilityResponse = $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility');

        $eligibilityResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'eligible' => false,
                    'has_active_credit' => true,
                ],
            ]);

        $this->assertStringContainsString('déjà un micro-crédit en cours', $eligibilityResponse->json('data.reason'));

        // Tentative de nouvelle demande -> rejet 422
        $applyResponse = $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', ['amount' => 50000]);

        $applyResponse->assertStatus(422);
    }

    /**
     * L'endpoint GET /api/v1/micro-credit/current retourne le détail complet du crédit actif.
     */
    public function test_current_credit_endpoint_returns_active_credit_details(): void
    {
        $artisan = $this->createEligibleArtisanWithScore(1000);

        // Aucun crédit au début
        $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/current')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => null,
            ]);

        // Déblocage d'un crédit
        $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', ['amount' => 80000])
            ->assertStatus(201);

        $response = $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'amount' => 80000,
                    'repaid_amount' => 0,
                    'remaining_amount' => 80000,
                    'status' => 'debourse',
                    'score_prosartisan_at_application' => 1000,
                ],
            ]);
    }

    /**
     * La libération d'un jalon amortit automatiquement 20% du montant net artisan au titre du crédit.
     */
    public function test_milestone_release_automatically_amortizes_active_credit(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
            'position' => Geo::point(-4.0083, 5.3599),
        ]);

        $artisan = $this->createEligibleArtisanWithScore(1000);
        $artisan->update([
            'position' => Geo::point(-4.0083, 5.3599),
            'wallet_mo' => 100000,
            'preferred_payment_provider' => 'wave',
        ]);

        // Obtention d'un crédit de 50 000 FCFA
        $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', ['amount' => 50000])
            ->assertStatus(201);

        $credit = CreditApplication::where('user_id', $artisan->id)->first();
        $this->assertSame(50000, $credit->remaining_amount);

        // Création d'une mission active avec devis accepté
        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier test en cours',
            'status' => InProgressState::class,
            'montant_total' => 100000,
            'montant_materiaux' => 0,
            'montant_mo' => 100000,
            'ratio_materiaux' => 0.0,
            'accepted_at' => now(),
        ]);

        Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'statut' => 'accepte',
            'lignes_json' => [['type' => 'mo', 'montant' => 100000, 'description' => 'MO']],
            'jalons_json' => [['ordre' => 1, 'montant' => 100000, 'description' => 'Jalon 1']],
            'commission_service_ratio' => 0.10,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Jalon unique',
            'montant' => 100000,
            'statut' => 'valide',
            'valide_at' => now(),
        ]);

        // Approvisionnement du séquestre MO pour couvrir le jalon (Règle 23 : Event Sourcing)
        app(WalletService::class)->credit(
            $artisan,
            WalletType::WALLET_MO,
            100000,
            "Séquestre MO mission #{$mission->id}",
            [
                'mission_id' => $mission->id,
                'jalon_id' => $jalon->id,
                'type' => 'acompte',
            ]
        );

        // Libération du jalon via WalletService
        // Commission MO : 100 000 * (0.10 / 1.10) = 9 091 FCFA
        // Gain net artisan : 100 000 - 9 091 = 90 909 FCFA
        // Amortissement crédit (20%) : round(90 909 * 0.20) = 18 182 FCFA
        // Montant versé artisan : 90 909 - 18 182 = 72 727 FCFA
        app(WalletService::class)->releaseJalon($jalon);

        $credit->refresh();
        $this->assertSame(18182, $credit->repaid_amount);
        $this->assertSame(50000 - 18182, $credit->remaining_amount);

        // Une transaction de remboursement a été créée
        $repayTx = Transaction::where('user_id', $artisan->id)
            ->where('type', 'remboursement')
            ->first();

        $this->assertNotNull($repayTx);
        $this->assertSame(18182, $repayTx->montant);
        $this->assertSame('confirme', $repayTx->statut instanceof \BackedEnum ? $repayTx->statut->value : (string) $repayTx->statut);

        // Une transaction de libération nette a été créée pour l'artisan
        $liberationTx = Transaction::where('user_id', $artisan->id)
            ->where('type', 'liberation_jalon')
            ->first();

        $this->assertNotNull($liberationTx);
        $this->assertSame(90909 - 18182, $liberationTx->montant);
    }

    /**
     * Un remboursement volontaire intégral solde le crédit et libère l'éligibilité pour un nouveau crédit.
     */
    public function test_voluntary_repayment_fully_settles_credit(): void
    {
        $artisan = $this->createEligibleArtisanWithScore(1000);

        // Obtention d'un crédit de 60 000 FCFA
        $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', ['amount' => 60000])
            ->assertStatus(201);

        $credit = CreditApplication::where('user_id', $artisan->id)->first();
        $this->assertSame(60000, $credit->remaining_amount);

        // Remboursement partiel de 20 000 FCFA
        $partRepay = $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/repay', [
                'amount' => 20000,
                'provider' => 'wave',
            ]);

        $partRepay->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'repaid_amount' => 20000,
                    'total_repaid' => 20000,
                    'remaining_amount' => 40000,
                    'is_fully_repaid' => false,
                ],
            ]);

        // Remboursement du solde restant (40 000 FCFA)
        $fullRepay = $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/repay', [
                'amount' => 40000,
                'provider' => 'wave',
            ]);

        $fullRepay->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'repaid_amount' => 40000,
                    'total_repaid' => 60000,
                    'remaining_amount' => 0,
                    'is_fully_repaid' => true,
                    'status' => 'rembourse',
                ],
            ]);

        $credit->refresh();
        $this->assertSame('rembourse', $credit->status);
        $this->assertNotNull($credit->repaid_at);
        $this->assertTrue($credit->isFullyRepaid());

        // L'artisan redevient immédiatement éligible
        $eligibilityResponse = $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility');

        $eligibilityResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'eligible' => true,
                    'has_active_credit' => false,
                ],
            ]);
    }

    /**
     * Téléchargement du rapport de solvabilité PDF.
     */
    public function test_solvability_report_endpoint_downloads_pdf(): void
    {
        $artisan = $this->createEligibleArtisanWithScore(1000);

        $response = $this->actingAs($artisan)
            ->get('/api/v1/micro-credit/report');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Helper pour créer un artisan avec un score calculé précis.
     */
    private function createEligibleArtisanWithScore(int $targetScore): User
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 0,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $mission = Mission::create([
                'client_id' => $client->id,
                'artisan_id' => $artisan->id,
                'description' => 'Mission test travaux',
                'status' => CompletedState::class,
                'montant_total' => 50000,
                'montant_materiaux' => 20000,
                'montant_mo' => 30000,
                'ratio_materiaux' => 0.40,
                'accepted_at' => now(),
            ]);

            Evaluation::create([
                'mission_id' => $mission->id,
                'evaluateur_id' => $client->id,
                'evalue_id' => $artisan->id,
                'note' => 5,
                'fiabilite' => 5.0,
                'integrite' => 5.0,
                'qualite' => 5.0,
                'reactivite' => 5.0,
                'commentaire' => 'Mission terminée avec brio',
            ]);
        }

        app(ScoreService::class)->recalculateFromLedger($artisan);

        return $artisan->fresh();
    }
}
