<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Mission;
use App\Models\ScoreLedgerEntry;
use App\Models\User;
use App\Services\ScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticScoreTest extends TestCase
{
    use RefreshDatabase;

    private ScoreService $scoreService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoreService = app(ScoreService::class);
    }

    public function test_fournisseur_gps_fraud_decreases_score()
    {
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'score_nzassa' => 10]);

        $this->scoreService->recordGpsFraudAttempt($fournisseur, null, 'PA-XXXX');

        $fournisseur->refresh();
        $this->assertEquals(0, $fournisseur->score_nzassa); // 10 - 50 = -40, bounded to MIN_SCORE = 0
        
        $entry = ScoreLedgerEntry::where('user_id', $fournisseur->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals('fraude_gps_tentative', $entry->event_type);
        $this->assertEquals(-50, $entry->points);
    }

    public function test_fournisseur_jcode_success_increases_score()
    {
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'score_nzassa' => 10]);
        $client = User::factory()->create(['role' => 'client']);
        $artisan = User::factory()->create(['role' => 'artisan']);
        
        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Test mission',
            'montant_total' => 50000,
            'montant_materiaux' => 30000,
            'montant_mo' => 20000,
            'ratio_materiaux' => 0.6,
        ]);

        $this->scoreService->recordJCodeSuccess($fournisseur, $mission->id, 'PA-ABCD');

        $fournisseur->refresh();
        $this->assertEquals(15, $fournisseur->score_nzassa); // 10 + 5
        
        $entry = ScoreLedgerEntry::where('user_id', $fournisseur->id)->first();
        $this->assertEquals('jcode_scan_success', $entry->event_type);
        $this->assertEquals(5, $entry->points);
    }

    public function test_livreur_evaluation_triggers_logistic_recalculation()
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan']);
        $livreur = User::factory()->create(['role' => 'livreur', 'score_nzassa' => 10]);
        
        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Test mission',
            'montant_total' => 50000,
            'montant_materiaux' => 30000,
            'montant_mo' => 20000,
            'ratio_materiaux' => 0.6,
        ]);

        $evaluation = Evaluation::create([
            'mission_id' => $mission->id,
            'evaluateur_id' => $client->id,
            'evalue_id' => $livreur->id,
            'note' => 5,
            'fiabilite' => 5, // 50%
            'integrite' => 5, 
            'qualite' => 5,   // 30%
            'reactivite' => 5, // 20%
            'commentaire' => 'Top livreur',
        ]);

        $this->scoreService->recalculateLogistic($livreur);

        $livreur->refresh();
        
        $entry = ScoreLedgerEntry::where('user_id', $livreur->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals('success_mission', $entry->event_type);
        $this->assertEquals(5, $entry->points);
    }
}
