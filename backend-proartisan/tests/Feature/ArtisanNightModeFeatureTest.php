<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo;
use Tests\TestCase;

class ArtisanNightModeFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_can_select_a_whole_sector_without_a_specific_trade(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $sector = Sector::create(['name' => 'Plomberie']);
        $trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Plombier sanitaire']);

        ArtisanProfile::create([
            'user_id' => $artisan->id,
            'intervient_la_nuit' => false,
            'sector_id' => $sector->id,
            'trade_id' => $trade->id,
        ]);

        // L'artisan repasse sur « tout le secteur » : trade_id doit être effacé.
        $this->actingAs($artisan)
            ->putJson("/api/v1/users/{$artisan->id}", [
                'sector_id' => $sector->id,
                'trade_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.artisanProfile.sectorId', $sector->id)
            ->assertJsonPath('data.artisanProfile.tradeId', null);

        $this->assertDatabaseHas('artisan_profiles', [
            'user_id' => $artisan->id,
            'sector_id' => $sector->id,
            'trade_id' => null,
        ]);
    }

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
            // À ~100 m du point de recherche : sous MariaDB, ST_Distance_Sphere
            // écarte à raison un artisan sans position (SQLite l'ignorait).
            'position' => Geo::point(5.3490, -4.0170),
        ]);

        ArtisanProfile::create([
            'user_id' => $nightArtisan->id,
            'intervient_la_nuit' => true,
        ]);

        $dayArtisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'name' => 'Artisan Jour',
            // À ~100 m du point de recherche : sous MariaDB, ST_Distance_Sphere
            // écarte à raison un artisan sans position (SQLite l'ignorait).
            'position' => Geo::point(5.3480, -4.0160),
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

    public function test_client_falls_back_to_wider_radius_when_no_nearby_artisans(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Requiert une base de données MySQL avec support géospatial.');
        }

        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $distantArtisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'name' => 'Artisan Distant',
            // Position éloignée de Abidjan Cocody (~12 km)
            'position' => Geo::point(5.4500, -4.0200),
        ]);

        ArtisanProfile::create([
            'user_id' => $distantArtisan->id,
            'intervient_la_nuit' => false,
        ]);

        $this->actingAs($client)
            ->getJson('/api/v1/artisans?lat=5.3484&lng=-4.0169&radius=2000')
            ->assertOk()
            ->assertJsonPath('meta.is_fallback', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Artisan Distant');
    }
}
