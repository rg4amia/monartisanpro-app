<?php

namespace Tests\Feature;

use App\Models\DoubleEntryLedgerEntry;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\ScoreLedgerEntry;
use App\Models\User;
use App\Services\DoubleEntryLedgerService;
use App\Services\ScoreService;
use App\States\Mission\DisputedState;
use App\States\Mission\InProgressState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Chantier9ComplianceTest extends TestCase
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
            'phone' => '+2250700000101',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'phone' => '+2250700000102',
            'score_prosartisan' => 780,
        ]);

        ScoreLedgerEntry::create([
            'user_id' => $this->artisan->id,
            'event_type' => 'evaluation',
            'points' => 780,
            'credibility_factor' => 1.0,
            'description' => 'Score de test initial',
        ]);

        $this->mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Chantier Plomberie Complète Cocody',
            'status' => InProgressState::class,
            'montant_total' => 300000,
            'montant_materiaux' => 180000,
            'montant_mo' => 120000,
            'ratio_materiaux' => 0.60,
        ]);
    }

    /**
     * Lot 9A : Grand Livre en Partie Double
     */
    public function test_lot9a_double_entry_ledger_escrow_and_integrity(): void
    {
        $ledger = app(DoubleEntryLedgerService::class);

        $entries = $ledger->recordEscrowFunding($this->mission, 120000, 180000);
        $this->assertCount(2, $entries);

        $this->assertEquals(120000, $ledger->getAccountBalance(DoubleEntryLedgerService::ACCOUNT_PLATFORM_ESCROW_MO, $this->mission->id));
        $this->assertEquals(180000, $ledger->getAccountBalance(DoubleEntryLedgerService::ACCOUNT_PLATFORM_ESCROW_MATERIALS, $this->mission->id));

        $this->artisan('ledger:verify-integrity')
            ->assertExitCode(0)
            ->expectsOutputToContain('100% conforme et équilibré');
    }

    /**
     * Lot 9B : Télé-Expertise Litiges par Vision IA
     */
    public function test_lot9b_dispute_tele_expertise_analysis(): void
    {
        $litige = Litige::create([
            'mission_id' => $this->mission->id,
            'declencheur_id' => $this->client->id,
            'type' => 'client',
            'motif' => 'Malfaçon et fuite persistante',
            'description' => 'Les raccords fuient abondamment et la tuyauterie est mal scellée.',
            'statut' => 'ouvert',
            'workflow_step' => 'preuves',
        ]);

        $response = $this->actingAs($this->client)
            ->postJson("/api/v1/litiges/{$litige->id}/tele-expertise");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('estimated_completion_rate', $data);
        $this->assertArrayHasKey('defect_severity', $data);
        $this->assertArrayHasKey('recommended_artisan_percentage', $data);
        $this->assertArrayHasKey('recommended_client_percentage', $data);
        $this->assertEquals(100, $data['recommended_artisan_percentage'] + $data['recommended_client_percentage']);

        $litige->refresh();
        $this->assertNotNull($litige->resolution_payload['tele_expertise_analysis'] ?? null);
    }

    /**
     * Lot 9C : Passeport de Solvabilité & Garantie Chantier Sérénité
     */
    public function test_lot9c_solvency_passport_and_insurance_quote(): void
    {
        // 1. Consultation du passeport de solvabilité par l'artisan
        $response = $this->actingAs($this->artisan)
            ->getJson("/api/v1/solvency-passports/{$this->artisan->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $token = $response->json('data.certification.token');
        $this->assertNotEmpty($token);

        // 2. Vérification publique du passeport via jeton cryptographique HMAC
        $verifyResponse = $this->getJson("/api/v1/solvency-passports/verify?token=".urlencode($token));
        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJson([
            'success' => true,
            'data' => [
                'artisan' => [
                    'id' => $this->artisan->id,
                ],
                'banking' => [
                    'credit_eligible' => true,
                ],
            ],
        ]);

        // 3. Devis Micro-Assurance « Garantie Chantier Sérénité »
        $insuranceResponse = $this->getJson("/api/v1/solvency-passports/insurance-quote?amount=200000");
        $insuranceResponse->assertStatus(200);
        $insuranceResponse->assertJson([
            'success' => true,
            'data' => [
                'option_name' => 'Garantie Chantier Sérénité',
                'rate_percent' => 1.5,
                'premium_fcfa' => 3000,
                'coverage_fcfa' => 200000,
            ],
        ]);
    }
}
