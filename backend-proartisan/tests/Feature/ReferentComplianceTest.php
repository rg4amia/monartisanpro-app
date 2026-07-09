<?php

namespace Tests\Feature;

use App\Models\Jalon;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReferentComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_referent_validation_is_blocked_if_outside_mission_zone(): void
    {
        /** @var User $referent */
        $referent = User::factory()->create([
            'role' => 'referent',
            'kyc_status' => 'actif',
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'wallet_mo' => 10000,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission référent',
            'status' => 'en_cours',
            'referent_required' => true,
            'client_latitude' => 5.3,
            'client_longitude' => -4.0,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Jalon validé',
            'montant' => 10000,
            'statut' => 'valide',
        ]);

        $this->actingAs($referent)
            ->post('/api/v1/missions/' . $mission->id . '/referent-validate', [
                'latitude' => 5.31,
                'longitude' => -4.0,
                'notes' => 'Trop loin du chantier',
                'photos' => [
                    UploadedFile::fake()->image('site-1.jpg'),
                    UploadedFile::fake()->image('site-2.jpg'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertTrue($mission->fresh()->referent_required);
        $this->assertSame('valide', $jalon->fresh()->statut);
    }

    public function test_referent_validation_releases_waiting_jalons_when_within_zone(): void
    {
        /** @var User $referent */
        $referent = User::factory()->create([
            'role' => 'referent',
            'kyc_status' => 'actif',
        ]);

        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'wallet_mo' => 15000,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission référent',
            'status' => 'en_cours',
            'referent_required' => true,
            'client_latitude' => 5.3,
            'client_longitude' => -4.0,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Jalon validé',
            'montant' => 10000,
            'statut' => 'valide',
        ]);

        $this->actingAs($referent)
            ->post('/api/v1/missions/' . $mission->id . '/referent-validate', [
                'latitude' => 5.3,
                'longitude' => -4.0,
                'notes' => 'Présence confirmée sur site',
                'photos' => [
                    UploadedFile::fake()->image('site-1.jpg'),
                    UploadedFile::fake()->image('site-2.jpg'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.jalons_liberes', 1);

        $this->assertFalse($mission->fresh()->referent_required);
        $this->assertSame($referent->id, $mission->fresh()->referent_validated_by);
        $this->assertSame('paye', $jalon->fresh()->statut);
    }
}
