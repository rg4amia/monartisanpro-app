<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_submit_evaluation_with_breakdown_scores(): void
    {
        /** @var User $client */
        $client = User::factory()->create([
            'role' => 'client_b2b',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 300,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Réparation toiture',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);

        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $artisan->id,
                'note' => 4,
                'commentaire' => 'Travail sérieux.',
                'fiabilite' => 5,
                'integrite' => 4,
                'qualite' => 3,
                'reactivite' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.scoreProsArtisan', 87);

        $this->assertDatabaseHas('evaluations', [
            'mission_id' => $mission->id,
            'evaluateur_id' => $client->id,
            'evalue_id' => $artisan->id,
            'note' => 4,
            'fiabilite' => 5,
            'integrite' => 4,
            'qualite' => 3,
            'reactivite' => 2,
        ]);

        $this->assertSame(87, $artisan->fresh()->score_prosartisan);
    }

    public function test_artisan_reaches_maximum_score_after_ten_missions_with_three_excellence_criteria(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 0,
        ]);

        $scoreService = app(\App\Services\ScoreService::class);

        // 10 evaluations with 5 stars on 3 criteria (fiabilite 5, integrite 5, qualite 5, reactivite 5)
        for ($i = 1; $i <= 10; $i++) {
            $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
            $mission = Mission::create([
                'client_id' => $client->id,
                'artisan_id' => $artisan->id,
                'description' => "Mission #{$i}",
                'status' => 'completed',
                'montant_total' => 50000,
                'montant_materiaux' => 30000,
                'montant_mo' => 20000,
                'ratio_materiaux' => 0.60,
            ]);

            \App\Models\Evaluation::create([
                'mission_id' => $mission->id,
                'evaluateur_id' => $client->id,
                'evalue_id' => $artisan->id,
                'note' => 5,
                'fiabilite' => 5,
                'integrite' => 5,
                'qualite' => 5,
                'reactivite' => 5,
            ]);
        }

        $finalScore = $scoreService->recalculateFromLedger($artisan);
        $this->assertSame(1000, $finalScore);
        $this->assertSame(1000, $artisan->fresh()->score_prosartisan);

        $detail = $scoreService->getScoreDetail($artisan);
        $this->assertSame(10, $detail['total_evaluations']);
        $this->assertSame(100.0, $detail['maturity_percentage']);
        $this->assertTrue($detail['micro_credit_eligible']);
    }
}
