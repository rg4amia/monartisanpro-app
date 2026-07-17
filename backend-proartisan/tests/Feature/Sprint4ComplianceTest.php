<?php

namespace Tests\Feature;

use App\Models\Jalon;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Parrainage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint4ComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_jalon_computer_vision_compliance(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Maçonnerie',
            'status' => 'funded_locked',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Construction mur de clôture',
            'montant' => 20000,
            'statut' => 'en_attente',
        ]);

        // Submit standard jalon -> succeeds
        $response = $this->actingAs($artisan)
            ->putJson("/api/v1/jalons/{$jalon->id}/submit", [
                'photos' => [
                    [
                        'url' => 'http://example.com/photo.jpg',
                        'lat' => 5.3,
                        'lng' => -4.0,
                    ]
                ]
            ]);

        $response->assertOk();

        // Submit incoherent jalon -> fails Vision check
        $jalon2 = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 2,
            'description' => 'Jalon frauduleux et incohérent',
            'montant' => 20000,
            'statut' => 'en_attente',
        ]);

        $response2 = $this->actingAs($artisan)
            ->putJson("/api/v1/jalons/{$jalon2->id}/submit", [
                'photos' => [
                    [
                        'url' => 'http://example.com/fraud.jpg',
                        'lat' => 5.3,
                        'lng' => -4.0,
                    ]
                ]
            ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors('photos');
    }

    public function test_sponsorship_registration_and_caution_penalty(): void
    {
        $parrain = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_nzassa' => 300,
        ]);

        \App\Models\ScoreLedgerEntry::create([
            'user_id' => $parrain->id,
            'event_type' => 'success_mission',
            'points' => 890,
            'credibility_factor' => 1.00,
            'description' => 'Initial high score',
        ]);
        app(\App\Services\ScoreService::class)->recalculateFromLedger($parrain);

        $filleul = User::factory()->create([
            'role' => 'artisan',
            'phone' => '+2250909090909',
            'kyc_status' => 'actif',
            'score_nzassa' => 300,
        ]);

        // Register sponsorship
        $response = $this->actingAs($parrain)
            ->postJson('/api/v1/parrainages', [
                'filleul_phone' => $filleul->phone,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('parrainages', [
            'parrain_id' => $parrain->id,
            'filleul_id' => $filleul->id,
        ]);

        // List sponsorships
        $responseList = $this->actingAs($parrain)
            ->getJson('/api/v1/parrainages');

        $responseList->assertOk()
            ->assertJsonCount(1, 'data');

        // Verify penalty cascade when filleul loses a dispute
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $admin = User::factory()->create(['role' => 'admin']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $filleul->id,
            'description' => 'Plomberie',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ]);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'malfaçon',
            'description' => 'Travaux non terminés',
            'statut' => 'ouvert',
            'workflow_step' => 'arbitrage',
        ]);

        // Arbitrate in favor of client (meaning the filleul loses)
        $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litige->id}/arbitrage", [
                'decision' => 'client',
                'notes' => 'Le client est remboursé car le filleul a abandonné le chantier.',
            ])
            ->assertOk();

        // Parrain score must have dropped from 900 by 50 points -> 850
        $this->assertSame(850, $parrain->fresh()->score_nzassa);
    }
}
