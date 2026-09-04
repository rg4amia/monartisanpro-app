<?php

namespace Tests\Feature\Admin;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier C4 (P1-6) — dernier lot : listes « missions » et « KYC » serveur.
 */
class AdminMissionsKycListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    private function mission(array $overrides = []): Mission
    {
        return Mission::create(array_merge([
            'client_id' => User::factory()->create(['role' => 'client'])->id,
            'artisan_id' => User::factory()->create(['role' => 'artisan'])->id,
            'description' => 'Mission de test',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ], $overrides));
    }

    public function test_missions_are_paginated_with_distinct_page_params_and_stats(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 30; $i++) {
            $this->mission();
        }
        $this->mission(['status' => 'disputed']);

        $this->actingAs($admin)->get('/admin/missions')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/missions')
            ->has('missionsPage.data', 25)
            ->where('missionsPage.total', 31)
            ->where('missionStats.en_cours', 30)
            ->where('missionStats.en_litige', 1)
            ->has('deliveryStats'));

        // Le param de page des missions ne doit pas être `page` (collision avec les commandes).
        $this->actingAs($admin)->get('/admin/missions?mission_page=2')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('missionsPage.current_page', 2)
                ->has('missionsPage.data', 6));
    }

    public function test_mission_search_filters_server_side(): void
    {
        $admin = $this->admin();
        $this->mission(['description' => 'Réfection toiture villa Cocody']);
        $this->mission(['description' => 'Peinture appartement Plateau']);

        $this->actingAs($admin)->get('/admin/missions?search_mission=Cocody')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('missionsPage.data', 1));
    }

    public function test_kyc_queue_is_paginated_and_searchable(): void
    {
        $admin = $this->admin();
        User::factory()->count(30)->create(['kyc_status' => 'en_attente', 'role' => 'client']);
        User::factory()->create(['kyc_status' => 'en_attente', 'role' => 'artisan', 'name' => 'Yao Le Plombier']);
        User::factory()->count(2)->create(['kyc_status' => 'rejete', 'role' => 'client']);

        $this->actingAs($admin)->get('/admin/kyc')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/kyc')
            ->has('kycUsersPage.data', 25)
            ->where('kycUsersPage.total', 31)
            ->where('kycStats.pending', 31)
            ->where('kycStats.artisans_pending', 1)
            ->where('kycStats.rejected', 2)
            ->has('kycStats.registration_trend', 15));

        $this->actingAs($admin)->get('/admin/kyc?search_kyc=Yao')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('kycUsersPage.data', 1)
                ->where('kycUsersPage.data.0.name', 'Yao Le Plombier'));
    }
}
