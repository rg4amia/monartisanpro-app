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
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 80,
        ]);

        $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.max_amount', 100000)
            ->assertJsonPath('data.score_prosartisan', 80);

        $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', [
                'amount' => 90000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 90000)
            ->assertJsonPath('data.status', 'approuve');

        $this->assertDatabaseHas('credit_applications', [
            'user_id' => $artisan->id,
            'amount' => 90000,
            'status' => 'approuve',
            'score_prosartisan_at_application' => 80,
        ]);
    }
}
