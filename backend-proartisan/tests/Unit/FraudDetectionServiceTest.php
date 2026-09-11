<?php

namespace Tests\Unit;

use App\Models\FraudAlert;
use App\Models\Jalon;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\User;
use App\Services\FraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FraudDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FraudDetectionService $fraudService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fraudService = app(FraudDetectionService::class);
    }

    private function createMission(array $overrides = []): Mission
    {
        $client = $overrides['client'] ?? User::factory()->create(['role' => 'client']);
        $artisan = $overrides['artisan'] ?? User::factory()->create(['role' => 'artisan']);

        return Mission::create(array_merge([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier test anti-fraude',
            'status' => 'in_progress',
            'montant_total' => 150000,
            'montant_materiaux' => 50000,
            'montant_mo' => 100000,
            'ratio_materiaux' => 0.3333,
        ], $overrides));
    }

    public function test_it_detects_fast_otp_milestone_validation_and_triggers_payment_hold(): void
    {
        $client = User::factory()->create(['role' => 'client', 'phone' => '0700000001']);
        $artisan = User::factory()->create(['role' => 'artisan', 'phone' => '0700000002']);

        $mission = $this->createMission([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Fondations terrassement',
            'montant' => 50000,
            'statut' => 'soumis',
            'updated_at' => now()->subSeconds(30), // Soumis il y a seulement 30 secondes
        ]);

        $alert = $this->fraudService->analyzeMilestoneValidation($jalon, $client);

        $this->assertNotNull($alert);
        $this->assertInstanceOf(FraudAlert::class, $alert);
        $this->assertEquals('fast_otp_validation', $alert->type);
        $this->assertGreaterThanOrEqual(75, $alert->risk_score);
        $this->assertEquals('payment_hold', $alert->action_taken);
        $this->assertEquals('ouverte', $alert->statut);
        $this->assertStringStartsWith('FRD-', $alert->reference);
    }

    public function test_it_detects_gps_site_anomaly_when_photo_is_far_from_site(): void
    {
        // Chantier situé à Abidjan Plateau (lat: 5.32, lng: -4.02)
        $mission = $this->createMission([
            'client_latitude' => 5.3200,
            'client_longitude' => -4.0200,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Coulage béton',
            'montant' => 80000,
            'statut' => 'en_attente',
        ]);

        // Photo géolocalisée à Bouaké (~280 km d'Abidjan)
        $photos = [
            [
                'url' => 'https://storage.prosartisan.net/proofs/photo1.jpg',
                'lat' => 7.6900,
                'lng' => -5.0300,
            ]
        ];

        $alert = $this->fraudService->analyzeMilestoneSubmission($jalon, $photos);

        $this->assertNotNull($alert);
        $this->assertEquals('gps_anomaly_site', $alert->type);
        $this->assertEquals('high', $alert->severity);
        $this->assertGreaterThanOrEqual(75, $alert->risk_score);
        $this->assertEquals('payment_hold', $alert->action_taken);
    }

    public function test_it_detects_duplicate_milestone_photos_across_missions(): void
    {
        $mission1 = $this->createMission();

        $jalon1 = Jalon::create([
            'mission_id' => $mission1->id,
            'ordre' => 1,
            'description' => 'Peinture salon',
            'montant' => 45000,
            'statut' => 'valide',
            'photos_json' => ['https://storage.prosartisan.net/proofs/chantier_peinture_unique.jpg'],
        ]);

        $mission2 = $this->createMission();

        $jalon2 = Jalon::create([
            'mission_id' => $mission2->id,
            'ordre' => 1,
            'description' => 'Peinture chambre',
            'montant' => 35000,
            'statut' => 'en_attente',
        ]);

        // L'artisan tente de réutiliser la même photo
        $photos = [
            ['url' => 'https://storage.prosartisan.net/proofs/chantier_peinture_unique.jpg']
        ];

        $alert = $this->fraudService->analyzeMilestoneSubmission($jalon2, $photos);

        $this->assertNotNull($alert);
        $this->assertEquals('duplicate_proof', $alert->type);
        $this->assertEquals('critical', $alert->severity);
        $this->assertEquals(90, $alert->risk_score);
        $this->assertEquals('payment_hold', $alert->action_taken);
    }

    public function test_it_detects_distant_jcode_scan_and_logs_fraud_alert(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan']);
        $fournisseur = User::factory()->create(['role' => 'fournisseur']);

        $mission = $this->createMission([
            'artisan_id' => $artisan->id,
        ]);

        $jcode = JCode::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'code' => 'PA-TEST',
            'montant' => 100000,
            'statut' => 'actif',
            'expires_at' => now()->addDays(2),
        ]);

        // Scan effectué à 350 mètres de la boutique (> 100 m)
        $alert = $this->fraudService->analyzeJCodeRedemption(
            $jcode,
            $fournisseur,
            5.3500,
            -4.0100,
            350.5
        );

        $this->assertNotNull($alert);
        $this->assertEquals('gps_anomaly_jcode', $alert->type);
        $this->assertEquals('critical', $alert->severity);
        $this->assertEquals(95, $alert->risk_score);
        $this->assertEquals('payment_hold', $alert->action_taken);
    }

    public function test_haversine_distance_calculation(): void
    {
        // Distance connue entre deux points à Abidjan (~1.1 km)
        $lat1 = 5.3200;
        $lon1 = -4.0200;
        $lat2 = 5.3300;
        $lon2 = -4.0200;

        $distance = $this->fraudService->haversineDistance($lat1, $lon1, $lat2, $lon2);

        // 1 degré de latitude ~= 111 km => 0.01 degré ~= 1110 m
        $this->assertGreaterThan(1000, $distance);
        $this->assertLessThan(1200, $distance);
    }

    public function test_admin_can_hold_release_confirm_and_dismiss_fraud_alert(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'phone' => '0700000099',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'phone' => '0700000088',
            'score_prosartisan' => 600,
        ]);

        $alert = FraudAlert::create([
            'reference' => FraudAlert::generateReference(),
            'user_id' => $artisan->id,
            'type' => 'fast_otp_validation',
            'severity' => 'high',
            'risk_score' => 85,
            'reasons_json' => ['Validation ultra-rapide suspecte'],
            'statut' => 'ouverte',
            'action_taken' => 'none',
        ]);

        // 1. Activer le gel préventif (hold)
        $response = $this->actingAs($admin)->post("/admin/fraud-alerts/{$alert->id}/hold", [
            'notes' => 'Mise en gel conservatoire pour enquête',
        ]);
        $response->assertSessionHas('success');
        $alert->refresh();
        $this->assertEquals('payment_hold', $alert->action_taken);
        $this->assertEquals('en_analyse', $alert->statut);

        // 2. Confirmer la fraude (confirm)
        $response = $this->actingAs($admin)->post("/admin/fraud-alerts/{$alert->id}/confirm", [
            'notes' => 'Collusion avérée confirmée',
        ]);
        $response->assertSessionHas('success');
        $alert->refresh();
        $this->assertEquals('confirmee', $alert->statut);
        $this->assertEquals($admin->id, $alert->resolved_by);

        // 3. Classer sans suite (dismiss)
        $response = $this->actingAs($admin)->post("/admin/fraud-alerts/{$alert->id}/dismiss", [
            'notes' => 'Clôture finale',
        ]);
        $response->assertSessionHas('success');
        $alert->refresh();
        $this->assertEquals('rejetee', $alert->statut);
        $this->assertEquals('none', $alert->action_taken);
    }
}
