<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreZeroDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_registration_defaults_strictly_to_zero_score(): void
    {
        $user = User::create([
            'phone' => '+2250102030405',
            'role' => 'client',
            'kyc_status' => 'en_attente',
        ]);

        $this->assertSame(0, $user->score_prosartisan);
        $this->assertSame(0, $user->fresh()->score_prosartisan);
    }

    public function test_user_first_or_create_defaults_strictly_to_zero_score(): void
    {
        $user = User::firstOrCreate(
            ['phone' => '+2250102030499'],
            ['kyc_status' => 'en_attente']
        );

        $this->assertSame(0, $user->score_prosartisan);
        $this->assertSame(0, $user->fresh()->score_prosartisan);
    }

    public function test_artisan_without_evaluations_or_ledger_entries_recalculates_to_zero(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'score_prosartisan' => 300, // force simulation d'un ancien score orphelin
        ]);

        $scoreService = app(ScoreService::class);
        $calculatedScore = $scoreService->recalculateFromLedger($artisan);

        $this->assertSame(0, $calculatedScore);
        $this->assertSame(0, $artisan->fresh()->score_prosartisan);
    }
}
