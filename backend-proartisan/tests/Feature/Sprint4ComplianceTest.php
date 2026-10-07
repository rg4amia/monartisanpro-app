<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Jalon;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use App\Services\ScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
                        'url' => '/storage/jalons/photo.jpg',
                        'lat' => 5.3,
                        'lng' => -4.0,
                    ],
                ],
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
                        'url' => '/storage/jalons/fraud.jpg',
                        'lat' => 5.3,
                        'lng' => -4.0,
                    ],
                ],
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

        // Au-delà de 800, le score exige des évaluations d'excellence : un
        // bonus du ledger seul ne franchit plus le seuil.
        $evaluateur = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        for ($i = 0; $i < 10; $i++) {
            Evaluation::create([
                'evaluateur_id' => User::factory()->create(['role' => 'client', 'kyc_status' => 'actif'])->id,
                'evalue_id' => $parrain->id,
                'note' => 5,
                'fiabilite' => 5,
                'integrite' => 5,
                'qualite' => 5,
                'reactivite' => 5,
            ]);
        }
        app(ScoreService::class)->recalculateFromLedger($parrain);

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
                'filleul_nom' => $filleul->name,
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

        // Le parrain passe de 1000 à 950 : pénalité de caution de 50 points.
        $this->assertSame(950, $parrain->fresh()->score_prosartisan);
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
                        'url' => '/storage/jalons/photo1.jpg',
                        'lat' => 5.3,
                        'lng' => -4.0,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertEquals('soumis', $jalon->fresh()->statut);

        // 2. Upload additional proof (mock file upload)
        Storage::fake('public');
        $file = UploadedFile::fake()->image('photo2.jpg');

        $responseUpload = $this->actingAs($artisan)
            ->postJson("/api/v1/jalons/{$jalon->id}/photos", [
                'photos' => [
                    [
                        'photo' => $file,
                        'latitude' => 5.301,
                        'longitude' => -4.001,
                        'description' => 'Vue de face',
                    ],
                ],
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
        $file3 = UploadedFile::fake()->image('photo3.jpg');
        $this->actingAs($artisan)
            ->postJson("/api/v1/jalons/{$jalon->id}/photos", [
                'photos' => [
                    [
                        'photo' => $file3,
                        'latitude' => 5.3,
                        'longitude' => -4.0,
                    ],
                ],
            ])
            ->assertStatus(400);
    }
}
