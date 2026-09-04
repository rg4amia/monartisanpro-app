<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier C1 — la découpe de l'ancien god method renderPage().
 *
 * Chaque page du backoffice ne doit charger que sa propre tranche de données,
 * plus les props partagées du layout (dashboard + navBadges + adminNotifications).
 */
class BackofficePagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    public function test_every_admin_page_renders_for_an_admin(): void
    {
        $admin = $this->admin();

        $pages = [
            '/admin/dashboard' => 'admin/dashboard',
            '/admin/kyc' => 'admin/kyc',
            '/admin/missions' => 'admin/missions',
            '/admin/litiges' => 'admin/litiges',
            '/admin/users' => 'admin/users',
            '/admin/transactions' => 'admin/transactions',
            '/admin/settings' => 'admin/settings',
            '/admin/roles-permissions' => 'admin/roles-permissions',
            '/admin/audit-logs' => 'admin/audit-logs',
            '/admin/evaluations' => 'admin/evaluations',
            '/admin/communications' => 'admin/communications',
            '/admin/notifications' => 'admin/notifications',
            '/admin/promo-codes' => 'admin/promo-codes',
            '/admin/llm-admin' => 'admin/llm-admin',
            '/admin/ai-dashboard' => 'admin/ai-dashboard',
            '/admin/vitrine' => 'admin/vitrine',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component($component)
                    ->has('dashboard')
                    ->has('navBadges')
                    ->has('adminNotifications'));
        }
    }

    public function test_users_page_only_loads_its_own_slice(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/users')
            ->has('usersPage.data')
            ->has('usersPage.links')
            ->has('userStats')
            ->has('topArtisans')
            // Les données des autres onglets ne doivent plus être chargées.
            ->missing('missions')
            ->missing('transactions')
            ->missing('vitrineSlides')
            ->missing('allPermissions'));
    }

    public function test_missions_page_loads_missions_and_orders_only(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/missions')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/missions')
            ->has('missionsPage.data')
            ->has('ordersPage.data')
            ->has('missionStats')
            ->has('deliveryStats')
            ->missing('users')
            ->missing('litiges'));
    }

    public function test_nav_badges_expose_lightweight_counts(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('navBadges.transactions_en_attente')
            ->has('navBadges.communications_publiees')
            ->has('navBadges.promo_codes_actifs')
            ->has('navBadges.contact_messages_nouveaux'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    }
}
