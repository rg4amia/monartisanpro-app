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

    public function test_district_matching_ignores_substring_false_positives(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Adresse contenant "Man" en sous-chaîne d'un mot sans rapport
        // (ex: "Allemagne") : ne doit PAS être comptée dans le district de Man (Tonkpi).
        $client1 = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan1 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        Mission::create([
            'client_id' => $client1->id,
            'artisan_id' => $artisan1->id,
            'category' => 'Peinture',
            'description' => 'Ravalement façade',
            'client_address' => 'Résidence Allemagne, Cocody',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 30000,
            'montant_mo' => 70000,
            'ratio_materiaux' => 0.3,
        ]);

        // Adresse réellement à Man (chef-lieu du district Tonkpi) : doit être comptée.
        $client2 = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan2 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        Mission::create([
            'client_id' => $client2->id,
            'artisan_id' => $artisan2->id,
            'category' => 'Maçonnerie',
            'description' => 'Fondation chantier',
            'client_address' => 'Man centre-ville',
            'status' => 'completed',
            'montant_total' => 200000,
            'montant_materiaux' => 80000,
            'montant_mo' => 120000,
            'ratio_materiaux' => 0.4,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?district=tonkpi');

        $response->assertOk()
            ->assertJsonPath('summary.missions.total', 1)
            ->assertJsonPath('summary.missions.total_volume_fcfa', 200000);
    }

    public function test_districts_heatmap_is_cached_and_invalidated_on_mission_change(): void
    {
        $service = app(\App\Services\Admin\AdminTerritoryService::class);

        $first = $service->getDistrictsHeatmap();

        // Un second appel sans changement sous-jacent doit servir le résultat mis en
        // cache sans ré-exécuter la batterie de requêtes par district (Règle d'Or 27).
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $second = $service->getDistrictsHeatmap();
        $queriesOnCacheHit = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertSame(0, $queriesOnCacheHit, 'Un appel avec cache chaud ne doit déclencher aucune requête SQL.');

        // La création d'une mission dans le district de Man (Tonkpi) invalide le cache
        // via l'observer déjà branché sur Mission (AdminDashboardCacheObserver) et doit
        // se refléter immédiatement, sans attendre le TTL.
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'category' => 'Plomberie',
            'description' => 'Fuite',
            'client_address' => 'Man centre-ville',
            'status' => 'completed',
            'montant_total' => 50000,
            'montant_materiaux' => 20000,
            'montant_mo' => 30000,
            'ratio_materiaux' => 0.4,
        ]);

        $third = $service->getDistrictsHeatmap();
        $this->assertSame($first['tonkpi']['missions_count'] + 1, $third['tonkpi']['missions_count']);
    }
}
