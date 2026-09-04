<?php

namespace Tests\Feature\Admin;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminDashboardCache;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chantier C4 (P1-7) — cache des KPI du tableau de bord admin.
 */
class AdminDashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_result_is_served_from_cache_until_flushed(): void
    {
        $service = app(AdminService::class);

        $initial = $service->dashboard()['users_total'];

        // Insertion directe : ne déclenche aucun événement Eloquent, donc pas de purge.
        DB::table('users')->insert([
            'name' => 'Hors cache',
            'phone' => '+2250700000042',
            'role' => 'client',
            'kyc_status' => 'en_attente',
            'password' => bcrypt('secret123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame($initial, $service->dashboard()['users_total'], 'La valeur doit rester celle du cache.');

        app(AdminDashboardCache::class)->flush();

        $this->assertSame($initial + 1, $service->dashboard()['users_total']);
    }

    public function test_saving_a_transaction_purges_the_dashboard_cache(): void
    {
        $service = app(AdminService::class);
        $service->dashboard();          // amorce le cache
        $service->getFinancialKpis();   // amorce le cache KPI

        Transaction::create([
            'type' => 'acompte',
            'montant' => 50000,
            'wallet_source' => 'client_wallet',
            'wallet_dest' => 'escrow_wallet',
            'provider' => 'wave',
            'statut' => 'confirme',
            'reference_externe' => 'REF-CACHE-TEST',
        ]);

        $this->assertFalse(cache()->has(AdminDashboardCache::DASHBOARD_KEY));
        $this->assertFalse(cache()->has(AdminDashboardCache::FINANCIAL_KPIS_KEY));
    }

    public function test_financial_kpis_are_cached(): void
    {
        $service = app(AdminService::class);
        User::factory()->create(['role' => 'admin']);

        $service->getFinancialKpis();

        $this->assertTrue(cache()->has(AdminDashboardCache::FINANCIAL_KPIS_KEY));
    }
}
