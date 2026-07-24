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
            ->assertJsonPath('data.scoreProsArtisan', 17);

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

        $this->assertSame(17, $artisan->fresh()->score_prosartisan);
    }
}
