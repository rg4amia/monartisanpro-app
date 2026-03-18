<?php

namespace Tests\Feature;

use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use App\Services\WalletService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LitigeComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_arbitrate_litige_favoring_client()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $artisan = User::factory()->create(['role' => 'artisan', 'wallet_materiaux' => 65000, 'wallet_mo' => 35000]);
        $client = User::factory()->create(['role' => 'client']);
        
        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Test Mission',
            'status' => 'litige',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65
        ]);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'description' => 'Bad work',
            'statut' => 'ouvert'
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litige->id}/arbitrage", [
                'decision' => 'client',
                'notes' => 'Full refund'
            ])
            ->assertStatus(200);

        $this->assertEquals('resolu', $litige->fresh()->statut);
        $this->assertEquals('client', $litige->fresh()->decision);
        $this->assertEquals(0, $artisan->fresh()->wallet_materiaux);
        $this->assertEquals(0, $artisan->fresh()->wallet_mo);
        $this->assertEquals('annulee', $mission->fresh()->status);
    }
}
