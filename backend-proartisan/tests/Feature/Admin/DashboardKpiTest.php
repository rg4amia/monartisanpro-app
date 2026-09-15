<?php

namespace Tests\Feature\Admin;

use App\Models\Faq;
use App\Models\User;
use App\Models\WhatsappClickLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Le tableau de bord expose des KPI légers sur les nouveaux modules
 * WhatsApp (clics) et FAQ (couverture par rôle) sans requête supplémentaire
 * dédiée à ces onglets.
 */
class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_whatsapp_and_faq_kpis(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        WhatsappClickLog::create(['page' => '/', 'source' => 'floating_button']);
        WhatsappClickLog::create(['page' => '/contact', 'source' => 'floating_button']);

        Faq::create(['question' => 'Q1', 'reponse' => 'R1', 'roles' => ['client'], 'actif' => true]);
        Faq::create(['question' => 'Q2', 'reponse' => 'R2', 'roles' => ['artisan', 'livreur'], 'actif' => true]);
        Faq::create(['question' => 'Q3', 'reponse' => 'R3', 'roles' => ['client'], 'actif' => false]);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/dashboard')
                ->where('whatsappClickStats.total', 2)
                ->where('whatsappClickStats.today', 2)
                ->where('faqStats.total', 3)
                ->where('faqStats.actives', 2)
                ->where('faqStats.roles_covered', 3));
    }
}
