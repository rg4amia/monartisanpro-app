<?php

namespace Tests\Feature;

use App\Models\FraudAlert;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JalonVisionAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_milestone_submission_saves_conformity_score_and_vision_report(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id'         => $client->id,
            'artisan_id'        => $artisan->id,
            'description'       => 'Plomberie sanitaire',
            'status'            => 'funded_locked',
            'montant_total'     => 150000,
            'montant_materiaux' => 90000,
            'montant_mo'        => 60000,
            'ratio_materiaux'   => 0.60,
        ]);

        $jalon = Jalon::create([
            'mission_id'  => $mission->id,
            'ordre'       => 1,
            'description' => 'Pose tuyauterie cuivre et raccords douche',
            'montant'     => 30000,
            'statut'      => 'en_attente',
        ]);

        $response = $this->actingAs($artisan)
            ->putJson("/api/v1/jalons/{$jalon->id}/submit", [
                'photos' => [
                    [
                        'url' => 'https://example.com/photos/plumbing_copper.jpg',
                        'lat' => 5.3484,
                        'lng' => -4.0169,
                    ]
                ]
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conformity_score', 92)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'ordre',
                    'description',
                    'conformity_score',
                    'vision_analysis' => [
                        'approved',
                        'conformity_score',
                        'detected_elements',
                        'summary',
                        'confidence',
                        'analyzed_at',
                    ],
                ]
            ]);

        $jalon->refresh();
        $this->assertSame(92, $jalon->conformity_score);
        $this->assertIsArray($jalon->vision_analysis_json);
        $this->assertTrue($jalon->vision_analysis_json['approved']);
        $this->assertSame('soumis', $jalon->statut);
    }

    public function test_fraudulent_milestone_triggers_vision_mismatch_fraud_alert(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id'         => $client->id,
            'artisan_id'        => $artisan->id,
            'description'       => 'Électricité générale',
            'status'            => 'funded_locked',
            'montant_total'     => 80000,
            'montant_materiaux' => 50000,
            'montant_mo'        => 30000,
            'ratio_materiaux'   => 0.625,
        ]);

        $jalon = Jalon::create([
            'mission_id'  => $mission->id,
            'ordre'       => 1,
            'description' => 'Jalon frauduleux et incohérent avec tableau vide',
            'montant'     => 15000,
            'statut'      => 'en_attente',
        ]);

        $response = $this->actingAs($artisan)
            ->putJson("/api/v1/jalons/{$jalon->id}/submit", [
                'photos' => [
                    [
                        'url' => 'https://example.com/photos/fake_screen.jpg',
                        'lat' => 5.3484,
                        'lng' => -4.0169,
                    ]
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('photos');

        $jalon->refresh();
        $this->assertSame(25, $jalon->conformity_score);
        $this->assertFalse($jalon->vision_analysis_json['approved']);

        // Vérification de la création de l'alerte de fraude vision_mismatch
        $this->assertDatabaseHas('fraud_alerts', [
            'mission_id' => $mission->id,
            'user_id'    => $artisan->id,
            'type'       => 'vision_mismatch',
        ]);

        $alert = FraudAlert::where('mission_id', $mission->id)
            ->where('type', 'vision_mismatch')
            ->first();

        $this->assertNotNull($alert);
        $this->assertGreaterThanOrEqual(75, $alert->risk_score);
        $this->assertContains('anomalies', array_keys($alert->metadata_json));
    }
}
