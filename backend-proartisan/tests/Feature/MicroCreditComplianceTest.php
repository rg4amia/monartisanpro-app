<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroCreditComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_can_check_eligibility_and_apply_for_micro_credit(): void
    {
        // Échelle 0–1000 : seuil d'éligibilité = 700 (config prosartisan.score_prosartisan.credit_threshold).
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 800,
        ]);

        // Base 50 000 + (800 - 700) × 1 500 = 200 000 FCFA.
        $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.max_amount', 200000)
            ->assertJsonPath('data.score_prosartisan', 800);

        $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', [
                'amount' => 180000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 180000)
            ->assertJsonPath('data.status', 'approuve');

        $this->assertDatabaseHas('credit_applications', [
            'user_id' => $artisan->id,
            'amount' => 180000,
            'status' => 'approuve',
            'score_prosartisan_at_application' => 800,
        ]);
    }

    public function test_artisan_below_threshold_is_not_eligible_for_micro_credit(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 80,
        ]);

        $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.required_score', 700);

        $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', [
                'amount' => 90000,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('credit_applications', [
            'user_id' => $artisan->id,
        ]);
    }
}
