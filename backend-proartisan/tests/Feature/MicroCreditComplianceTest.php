<?php

namespace Tests\Feature;

use App\Models\ScoreLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicroCreditComplianceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Donne à l'artisan un score réellement adossé au ledger (Règle d'or 15 :
     * aucun point sans écriture) — une colonne `score_prosartisan` renseignée
     * sans ledger est ramenée à 0 au recalcul.
     */
    private function artisanWithLedgerScore(int $points): User
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => $points,
        ]);

        ScoreLedgerEntry::create([
            'user_id' => $artisan->id,
            'event_type' => 'evaluation',
            'points' => $points,
            'credibility_factor' => 1.0,
            'description' => 'Score de test',
        ]);

        return $artisan;
    }

    public function test_artisan_can_check_eligibility_and_apply_for_micro_credit(): void
    {
        // Échelle 0–1000 : seuil d'éligibilité = 700 (config prosartisan.score_prosartisan.credit_threshold).
        $artisan = $this->artisanWithLedgerScore(800);

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
            ->assertJsonPath('data.status', 'debourse');

        $this->assertDatabaseHas('credit_applications', [
            'user_id' => $artisan->id,
            'amount' => 180000,
            'status' => 'debourse',
            'score_prosartisan_at_application' => 800,
        ]);
    }

    public function test_stale_stored_score_without_ledger_does_not_grant_micro_credit(): void
    {
        // Colonne à 800 mais aucune évaluation ni écriture de ledger : le score réel
        // est 0. L'éligibilité jugée sur la colonne accordait auparavant 50 000 FCFA.
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 800,
        ]);

        $this->actingAs($artisan)
            ->getJson('/api/v1/micro-credit/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.current_score', 0);

        $this->actingAs($artisan)
            ->postJson('/api/v1/micro-credit/apply', [
                'amount' => 50000,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('credit_applications', [
            'user_id' => $artisan->id,
        ]);
    }

    public function test_artisan_below_threshold_is_not_eligible_for_micro_credit(): void
    {
        $artisan = $this->artisanWithLedgerScore(80);

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
