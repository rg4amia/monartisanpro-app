<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtisanMatchingTiersTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);
    }

    private function createActiveArtisan(string $name, float $lat, float $lng, int $score = 100): User
    {
        $artisan = User::factory()->create([
            'name' => $name,
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => $score,
        ]);
        $artisan->artisanProfile()->create([
            'experience_years' => 4,
            'intervient_la_nuit' => false,
        ]);
        $artisan->setPosition($lat, $lng);

        return $artisan;
    }

    public function test_artisan_matching_selects_immediate_tier_when_artisan_within_2km(): void
    {
        // Artisan à ~300 m (< 2 km)
        $this->createActiveArtisan('Artisan Immédiat', 5.3510, -4.0169, 500);

        // Artisan à ~3 km (> 2 km, <= 5 km)
        $this->createActiveArtisan('Artisan Local', 5.3750, -4.0169, 700);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/artisans?lat=5.3484&lng=-4.0169')
            ->assertOk();

        $response->assertJsonPath('meta.radius', 2000)
            ->assertJsonPath('meta.tier_id', 'immediate')
            ->assertJsonPath('meta.is_fallback', false)
            ->assertJsonPath('meta.is_extended_search', false)
            ->assertJsonPath('meta.total', 1);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Artisan Immédiat', $names);
        $this->assertNotContains('Artisan Local', $names);
    }

    public function test_artisan_matching_falls_back_to_local_tier_when_none_immediate(): void
    {
        // Aucun artisan à < 2 km
        // Artisan à ~3.5 km (palier local <= 5 km)
        $this->createActiveArtisan('Artisan Local', 5.3800, -4.0169, 600);

        // Artisan à ~8 km (palier ville <= 15 km)
        $this->createActiveArtisan('Artisan Ville', 5.4200, -4.0169, 800);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/artisans?lat=5.3484&lng=-4.0169')
            ->assertOk();

        $response->assertJsonPath('meta.radius', 5000)
            ->assertJsonPath('meta.tier_id', 'local')
            ->assertJsonPath('meta.is_fallback', true)
            ->assertJsonPath('meta.is_extended_search', true)
            ->assertJsonPath('meta.total', 1);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Artisan Local', $names);
        $this->assertNotContains('Artisan Ville', $names);
    }

    public function test_artisan_matching_falls_back_to_city_tier_when_none_local(): void
    {
        // Aucun artisan < 5 km
        // Artisan à ~8 km (palier grand Abidjan <= 15 km)
        $this->createActiveArtisan('Artisan Ville', 5.4200, -4.0169, 800);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/artisans?lat=5.3484&lng=-4.0169')
            ->assertOk();

        $response->assertJsonPath('meta.radius', 15000)
            ->assertJsonPath('meta.tier_id', 'city')
            ->assertJsonPath('meta.is_fallback', true)
            ->assertJsonPath('meta.is_extended_search', true)
            ->assertJsonPath('meta.total', 1);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Artisan Ville', $names);
    }

    public function test_artisan_matching_custom_radius_overrides_adaptive_tiers(): void
    {
        // Artisan à ~3.5 km
        $this->createActiveArtisan('Artisan 3km', 5.3800, -4.0169, 600);

        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/artisans?lat=5.3484&lng=-4.0169&radius=4000')
            ->assertOk();

        $response->assertJsonPath('meta.radius', 4000)
            ->assertJsonPath('meta.tier_id', 'custom')
            ->assertJsonPath('meta.is_fallback', true)
            ->assertJsonPath('meta.is_extended_search', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_artisan_matching_returns_empty_when_no_artisans_found(): void
    {
        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/artisans?lat=5.3484&lng=-4.0169')
            ->assertOk();

        $response->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
    }
}
