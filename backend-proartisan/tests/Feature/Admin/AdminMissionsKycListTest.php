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

    public function test_mission_status_filter_and_refusal_stats(): void
    {
        $admin = $this->admin();
        // 1 mission active
        $this->mission(['status' => 'in_progress']);
        // 1 mission avec refus de l'artisan
        $this->mission([
            'status' => 'draft',
            'artisan_rejected_at' => now(),
        ]);
        // 1 mission en attente classique (sans refus)
        $this->mission([
            'status' => 'draft',
            'artisan_rejected_at' => null,
        ]);

        // Filtrer uniquement les missions refusées
        $this->actingAs($admin)->get('/admin/missions?status_mission=refusee')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('missionsPage.data', 1)
                ->where('missionStats.refusees', 1)
                ->where('missionStats.en_cours', 1)
                ->where('missionStats.en_attente', 1));

        // Filtrer les missions en attente classique (exclut les refusées)
        $this->actingAs($admin)->get('/admin/missions?status_mission=en_attente')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('missionsPage.data', 1));
    }

    public function test_orders_are_associated_with_mission_via_foreign_key(): void
    {
        $admin = $this->admin();
        $client = User::factory()->create(['role' => 'client']);
        $supplier = User::factory()->create(['role' => 'fournisseur']);
        $mission = $this->mission(['client_id' => $client->id]);

        $linkedOrder = \App\Models\Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'mission_id' => $mission->id,
            'status' => 'paid',
            'delivery_mode' => 'delivery',
            'subtotal' => 40000,
            'delivery_cost' => 5000,
            'platform_fee' => 1000,
            'total_amount' => 46000,
            'pickup_code' => 'LIVREUR-4821',
            'reception_code' => 'RECEPTION-7390',
            'vehicle_class' => 'moto',
        ]);
        $unrelatedOrder = \App\Models\Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'mission_id' => null,
            'status' => 'paid',
            'delivery_mode' => 'delivery',
            'subtotal' => 40000,
            'delivery_cost' => 5000,
            'platform_fee' => 1000,
            'total_amount' => 46000,
            'pickup_code' => 'LIVREUR-4822',
            'reception_code' => 'RECEPTION-7391',
            'vehicle_class' => 'moto',
        ]);

        $this->actingAs($admin)->get('/admin/missions')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('missionsPage.data.0.orders', 1)
                ->where('missionsPage.data.0.orders.0.id', $linkedOrder->id));
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
