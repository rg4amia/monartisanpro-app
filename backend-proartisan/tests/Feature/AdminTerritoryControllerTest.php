<?php

namespace Tests\Feature;

use App\Models\Commune;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTerritoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_cartography(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->get('/admin/cartographie')
            ->assertForbidden();
    }

    public function test_admin_can_view_cartography_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get('/admin/cartographie');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cartography')
            ->has('territorySummary')
            ->has('districtsHeatmap')
            ->has('communesHeatmap')
            ->has('districtsList')
            ->has('communesList')
        );
    }

    public function test_admin_can_fetch_territory_stats_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $cocody = Commune::create(['name' => 'Cocody', 'slug' => 'cocody', 'city' => 'Abidjan', 'country_code' => 'CI']);
        $plateau = Commune::create(['name' => 'Plateau', 'slug' => 'plateau', 'city' => 'Abidjan', 'country_code' => 'CI']);

        // Créer un artisan à Cocody
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'commune_id' => $cocody->id,
            'kyc_status' => 'actif',
            'score_prosartisan' => 850,
        ]);

        // Créer un client au Plateau
        $client = User::factory()->create([
            'role' => 'client',
            'commune_id' => $plateau->id,
            'kyc_status' => 'actif',
        ]);

        // Créer une mission complétée à Cocody
        Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'category' => 'Électricité',
            'description' => 'Rénovation tableau Cocody Angré',
            'client_address' => 'Cocody Angré 8ème tranche',
            'status' => 'completed',
            'montant_total' => 150000,
            'montant_materiaux' => 50000,
            'montant_mo' => 100000,
            'ratio_materiaux' => 0.3333,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?commune=cocody');

        $response->assertOk()
            ->assertJsonPath('summary.zone.type', 'commune')
            ->assertJsonPath('summary.actors.artisans', 1)
            ->assertJsonPath('summary.missions.total', 1)
            ->assertJsonPath('summary.missions.completed', 1)
            ->assertJsonPath('summary.missions.realization_rate', 100)
            ->assertJsonPath('summary.missions.total_volume_fcfa', 150000);
    }
}
