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
            'status' => 'in_progress',
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
            'status' => 'in_progress',
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

    public function test_force_release_suspends_payment_above_referent_threshold(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'wallet_mo' => 0]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Grand chantier Cocody',
            'status' => 'in_progress',
            'montant_total' => 2500000,
            'montant_materiaux' => 1625000,
            'montant_mo' => 875000,
            'ratio_materiaux' => 0.65,
            'referent_required' => false,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Jalon soumis, client injoignable',
            'montant' => 500000,
            'statut' => 'soumis',
        ]);

        app(\App\Services\JalonService::class)->forceRelease($jalon);

        // La libération automatique à 72h ne doit jamais court-circuiter le
        // seuil référent : comme validateOtp()/acceptProofs(), le jalon passe
        // à 'valide' (constaté) mais reste impayé tant que le référent n'a
        // pas validé physiquement.
        $this->assertSame('valide', $jalon->fresh()->statut);
        $this->assertEquals(0, $artisan->fresh()->wallet_mo);
        $this->assertTrue($mission->fresh()->referent_required);
    }

    public function test_force_release_pays_immediately_below_referent_threshold(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'wallet_mo' => 10000]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Petit chantier',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);

        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Jalon soumis, client injoignable',
            'montant' => 10000,
            'statut' => 'soumis',
        ]);

        app(\App\Services\JalonService::class)->forceRelease($jalon);

        // La libération débite le wallet_mo réservé (10 000) vers le paiement réel.
        $this->assertSame('paye', $jalon->fresh()->statut);
        $this->assertEquals(0, $artisan->fresh()->wallet_mo);
        $this->assertFalse($mission->fresh()->referent_required);
    }

    public function test_referent_can_list_missions_requiring_validation(): void
    {
        /** @var User $referent */
        $referent = User::factory()->create([
            'role' => 'referent',
            'kyc_status' => 'actif',
        ]);

        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        // Mission standard ne nécessitant pas de référent
        Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission normale',
            'status' => 'in_progress',
            'referent_required' => false,
            'montant_total' => 150000,
        ]);

        // Mission avec référent requis
        $urgentMission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Grand chantier Cocody',
            'status' => 'in_progress',
            'referent_required' => true,
            'montant_total' => 2500000,
            'client_latitude' => 5.35,
            'client_longitude' => -3.98,
        ]);

        // Non-référent bloqué (HTTP 403)
        $this->actingAs($client)
            ->getJson('/api/v1/referent/missions')
            ->assertStatus(403);

        // Référent autorisé voit uniquement la mission urgente
        $response = $this->actingAs($referent)
            ->getJson('/api/v1/referent/missions')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($urgentMission->id, $data[0]['id']);
    }
}
