<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionStatusAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_mark_an_unpaid_mission_as_funded(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission non payée',
            'status' => 'pending_funding',
        ]);

        $this->actingAs($client)
            ->putJson("/api/v1/missions/{$mission->id}/status", [
                'status' => 'funded_locked',
            ])
            ->assertForbidden();

        $this->assertSame('pending_funding', (string) $mission->fresh()->status);
    }

    public function test_artisan_cannot_complete_a_mission_awaiting_client_approval(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission à valider',
            'status' => 'pending_approval',
        ]);

        $this->actingAs($artisan)
            ->putJson("/api/v1/missions/{$mission->id}/status", [
                'status' => 'completed',
            ])
            ->assertForbidden();

        $this->assertSame('pending_approval', (string) $mission->fresh()->status);
    }
}
