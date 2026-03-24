<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtisanNightModeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_can_enable_night_intervention_from_profile(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        ArtisanProfile::create([
            'user_id' => $artisan->id,
            'intervient_la_nuit' => false,
        ]);

        $this->actingAs($artisan)
            ->putJson("/api/v1/users/{$artisan->id}", [
                'name' => 'Artisan de nuit',
                'intervention_nuit' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Artisan de nuit')
            ->assertJsonPath('data.nightInterventionAvailable', true)
            ->assertJsonPath('data.artisanProfile.nightInterventionAvailable', true);

        $this->assertDatabaseHas('artisan_profiles', [
            'user_id' => $artisan->id,
            'intervient_la_nuit' => 1,
        ]);
    }

    public function test_client_can_filter_nearby_artisans_available_for_night_interventions(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $nightArtisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'name' => 'Artisan Nuit',
        ]);

        ArtisanProfile::create([
            'user_id' => $nightArtisan->id,
            'intervient_la_nuit' => true,
        ]);

        $dayArtisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'name' => 'Artisan Jour',
        ]);

        ArtisanProfile::create([
            'user_id' => $dayArtisan->id,
            'intervient_la_nuit' => false,
        ]);

        $this->actingAs($client)
            ->getJson('/api/v1/artisans?lat=5.3484&lng=-4.0169&intervention_nuit=1')
            ->assertOk()
            ->assertJsonPath('meta.intervention_nuit', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Artisan Nuit')
            ->assertJsonPath('data.0.nightInterventionAvailable', true);
    }
}
