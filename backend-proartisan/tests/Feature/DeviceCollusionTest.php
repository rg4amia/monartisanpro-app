<?php

namespace Tests\Feature;

use App\Models\FraudAlert;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\User;
use App\States\Mission\InProgressState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceCollusionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $artisan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
            'phone' => '+2250700000001',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'phone' => '+2250700000002',
            'score_prosartisan' => 800,
        ]);
    }

    public function test_same_device_fingerprint_between_client_and_artisan_triggers_critical_alert(): void
    {
        $sharedFingerprint = 'device-shared-hardware-hash-999';

        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Test Mission Collusion',
            'status' => InProgressState::class,
            'montant_total' => 100000,
            'montant_materiaux' => 50000,
            'montant_mo' => 50000,
            'ratio_materiaux' => 0.50,
            'client_device_fingerprint' => $sharedFingerprint,
            'artisan_device_fingerprint' => $sharedFingerprint,
            'client_ip' => '102.164.12.5',
            'artisan_ip' => '102.164.12.5',
        ]);

        $fraudService = app(\App\Services\FraudDetectionService::class);
        $alert = $fraudService->analyzeDeviceCollusion($mission);

        $this->assertNotNull($alert);
        $this->assertEquals('collusion_same_device', $alert->type);
        $this->assertEquals('critical', $alert->severity);
        $this->assertEquals(95, $alert->risk_score);
        $this->assertEquals('payment_hold', $alert->action_taken);

        $this->assertDatabaseHas('fraud_alerts', [
            'mission_id' => $mission->id,
            'type' => 'collusion_same_device',
            'severity' => 'critical',
            'action_taken' => 'payment_hold',
        ]);
    }

    public function test_milestone_otp_validation_from_same_device_suspends_payment(): void
    {
        $sharedFingerprint = 'device-tablet-pos-12345';

        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Test Mission Collusion Milestone',
            'status' => InProgressState::class,
            'montant_total' => 100000,
            'montant_materiaux' => 50000,
            'montant_mo' => 50000,
            'ratio_materiaux' => 0.50,
            'artisan_device_fingerprint' => $sharedFingerprint,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Fondations',
            'montant' => 50000,
            'statut' => 'soumis',
            'otp_code' => '4321',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        // Simule le service OTP qui valide le code
        $otpServiceMock = \Mockery::mock(\App\Services\OtpService::class);
        $otpServiceMock->shouldReceive('verifyOtp')
            ->with($this->client->phone, '4321')
            ->andReturn(true);
        $this->app->instance(\App\Services\OtpService::class, $otpServiceMock);

        $response = $this->actingAs($this->client)
            ->withHeaders([
                'X-Device-Fingerprint' => $sharedFingerprint,
            ])
            ->postJson("/api/v1/jalons/{$jalon->id}/validate-otp", [
                'otp' => '4321',
                'lat' => 5.3400,
                'lng' => -4.0100,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $jalon->refresh();

        // Le jalon doit être mis en "valide_suspendu" et NON payé car l'alerte a déclenché un payment_hold
        $this->assertEquals('valide_suspendu', $jalon->statut);

        $this->assertDatabaseHas('fraud_alerts', [
            'mission_id' => $mission->id,
            'type' => 'collusion_same_device_validation',
            'severity' => 'critical',
            'action_taken' => 'payment_hold',
        ]);
    }

    public function test_mission_realtime_events_endpoint_returns_event_stream_data(): void
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Test Realtime Events Mission',
            'status' => InProgressState::class,
            'montant_total' => 100000,
            'montant_materiaux' => 50000,
            'montant_mo' => 50000,
            'ratio_materiaux' => 0.50,
        ]);

        $eventService = app(\App\Services\RealtimeEventService::class);
        $eventService->broadcast($mission->id, 'jalon_updated', [
            'jalon_id' => 1,
            'statut' => 'soumis',
        ]);

        $response = $this->actingAs($this->client)
            ->getJson("/api/v1/missions/{$mission->id}/events");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertNotEmpty($response->json('data'));
    }
}
