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
        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
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
        /** @var User $parrain */
        $parrain = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 300,
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
            'score_prosartisan' => 300,
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
        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $admin */
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

        // Parrain score must have dropped from 890 by 50 points -> 840
        $this->assertSame(840, $parrain->fresh()->score_prosartisan);
    }

    public function test_jalon_multiple_proofs_and_direct_acceptance(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'wallet_mo' => 100000,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Menuiserie',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 50000,
            'montant_mo' => 50000,
            'ratio_materiaux' => 0.50,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Pose des portes',
            'montant' => 25000,
            'statut' => 'en_attente',
        ]);

        // 1. Submit jalon initial proof -> transitions to 'soumis'
        $this->actingAs($artisan)
            ->putJson("/api/v1/jalons/{$jalon->id}/submit", [
                'photos' => [
                    [
                        'url' => 'http://example.com/photo1.jpg',
                        'lat' => 5.3,
                        'lng' => -4.0,
                    ]
                ]
            ])
            ->assertOk();

        $this->assertEquals('soumis', $jalon->fresh()->statut);

        // 2. Upload additional proof (mock file upload)
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('photo2.jpg');

        $responseUpload = $this->actingAs($artisan)
            ->postJson("/api/v1/jalons/{$jalon->id}/photos", [
                'photos' => [
                    [
                        'photo' => $file,
                        'latitude' => 5.301,
                        'longitude' => -4.001,
                        'description' => 'Vue de face',
                    ]
                ]
            ]);

        $responseUpload->assertOk();
        $this->assertCount(2, $jalon->fresh()->photos_json);

        // 3. Client directly accepts proofs without OTP
        $responseAccept = $this->actingAs($client)
            ->postJson("/api/v1/jalons/{$jalon->id}/accept-proofs");

        $responseAccept->assertOk();
        
        // Jalon status should now be validated ('paye' or 'valide' depending on execution flow)
        $this->assertTrue(in_array($jalon->fresh()->statut, ['valide', 'paye']));

        // 4. Try uploading photos again -> should be blocked because status is no longer en_attente or soumis
        $file3 = \Illuminate\Http\UploadedFile::fake()->image('photo3.jpg');
        $this->actingAs($artisan)
            ->postJson("/api/v1/jalons/{$jalon->id}/photos", [
                'photos' => [
                    [
                        'photo' => $file3,
                        'latitude' => 5.3,
                        'longitude' => -4.0,
                    ]
                ]
            ])
            ->assertStatus(400);
    }
}
