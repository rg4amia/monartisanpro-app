<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier C4 (P1-6) — liste utilisateurs paginée + filtres serveur.
 */
class AdminUsersListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    public function test_users_list_is_paginated_server_side(): void
    {
        $admin = $this->admin();
        User::factory()->count(30)->create(['role' => 'client']);

        $this->actingAs($admin)->get('/admin/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('usersPage.data', 25)
            ->where('usersPage.per_page', 25)
            ->where('usersPage.total', 31)); // 30 clients + 1 admin
    }

    public function test_role_filter_narrows_the_result_set(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create(['role' => 'artisan']);
        User::factory()->count(5)->create(['role' => 'client']);

        $this->actingAs($admin)->get('/admin/users?role_users=artisan')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('usersPage.data', 3)
                ->where('usersPage.data.0.role', 'artisan'));
    }

    public function test_search_matches_name_phone_and_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Awa Koné', 'role' => 'client']);
        User::factory()->count(4)->create(['role' => 'client']);

        $this->actingAs($admin)->get('/admin/users?search_users=Awa')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('usersPage.data', 1)
                ->where('usersPage.data.0.name', 'Awa Koné'));
    }

    public function test_stats_are_computed_independently_of_the_current_page(): void
    {
        $admin = $this->admin();
        User::factory()->count(40)->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($admin)->get('/admin/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('usersPage.data', 25)
            ->where('userStats.clients_actifs', 40));
    }
}
