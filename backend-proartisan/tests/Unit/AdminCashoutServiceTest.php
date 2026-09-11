<?php

namespace Tests\Unit;

use App\Models\FournisseurAgree;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Setting;
use App\Models\SupplierCashout;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCashoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminService $adminService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminService = app(AdminService::class);
        app(\App\Services\Admin\AdminDashboardCache::class)->flush();
    }

    public function test_it_generates_valid_cashout_reference(): void
    {
        $ref = SupplierCashout::generateReference();
        $this->assertStringStartsWith('CSH-', $ref);
        $this->assertMatchesRegularExpression('/^CSH-\d{8}-[A-Z0-9]{4}$/', $ref);
    }

    public function test_it_calculates_cashout_commission_correctly(): void
    {
        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'phone' => '+2250700000010',
        ]);

        $montantBrut = 100000; // 100 000 FCFA
        $commissionRate = 0.0250; // 2.5%
        $montantCommission = (int) round($montantBrut * $commissionRate); // 2 500 FCFA
        $montantNet = $montantBrut - $montantCommission; // 97 500 FCFA

        $cashout = SupplierCashout::create([
            'reference' => SupplierCashout::generateReference(),
            'supplier_id' => $supplier->id,
            'beneficiary_name' => 'Kouassi Michel',
            'beneficiary_phone' => '0701020304',
            'montant_brut' => $montantBrut,
            'commission_rate' => $commissionRate,
            'montant_commission' => $montantCommission,
            'montant_net' => $montantNet,
            'statut' => 'complete',
            'mode_retrait' => 'especes_guichet',
        ]);

        $this->assertEquals(100000, $cashout->montant_brut);
        $this->assertEquals(2500, $cashout->montant_commission);
        $this->assertEquals(97500, $cashout->montant_net);
        $this->assertEquals('complete', $cashout->statut);
    }

    public function test_financial_kpis_include_cashouts_and_frozen_disputes(): void
    {
        // 1. Quincaillerie avec un cashout
        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'phone' => '+2250700000011',
            'name' => 'Quincaillerie Moderne Cocody',
        ]);

        SupplierCashout::create([
            'reference' => SupplierCashout::generateReference(),
            'supplier_id' => $supplier->id,
            'beneficiary_name' => 'Bamba Seydou',
            'beneficiary_phone' => '0501020304',
            'montant_brut' => 200000,
            'commission_rate' => 0.025,
            'montant_commission' => 5000,
            'montant_net' => 195000,
            'statut' => 'complete',
            'mode_retrait' => 'especes_guichet',
        ]);

        // 2. Mission en litige (séquestre gelé)
        $client = User::factory()->create(['role' => 'client', 'phone' => '+2250700000012']);
        $artisan = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700000013']);

        $missionLitige = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Réparation fuite urgente',
            'status' => 'disputed',
            'montant_total' => 350000,
            'montant_materiaux' => 200000,
            'montant_mo' => 150000,
            'ratio_materiaux' => 0.5714,
        ]);

        app(\App\Services\Admin\AdminDashboardCache::class)->flush();
        $kpis = $this->adminService->getFinancialKpis();

        $this->assertArrayHasKey('cashout_kpis', $kpis);
        $this->assertEquals(200000, $kpis['cashout_kpis']['total_cashouts_brut']);
        $this->assertEquals(195000, $kpis['cashout_kpis']['total_cashouts_net']);
        $this->assertEquals(5000, $kpis['cashout_kpis']['total_commissions_quincailleries']);

        $this->assertArrayHasKey('sequestre_bloque_litiges', $kpis['solde_general']);
        $this->assertEquals(350000, $kpis['solde_general']['sequestre_bloque_litiges']);
        $this->assertEquals(1, $kpis['solde_general']['missions_bloquees_litiges_count']);
    }

    public function test_admin_can_approve_complete_and_reject_cashouts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '+2250700000099']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250700000098']);

        $cashout = SupplierCashout::create([
            'reference' => SupplierCashout::generateReference(),
            'supplier_id' => $supplier->id,
            'beneficiary_name' => 'Koffi Paul',
            'beneficiary_phone' => '0701020304',
            'montant_brut' => 50000,
            'commission_rate' => 0.025,
            'montant_commission' => 1250,
            'montant_net' => 48750,
            'statut' => 'en_attente',
            'mode_retrait' => 'especes_guichet',
        ]);

        $this->actingAs($admin);

        // 1. Approve
        $responseApprove = $this->post("/admin/cashouts/{$cashout->id}/approve");
        $responseApprove->assertSessionHas('success');
        $this->assertEquals('approuve', $cashout->fresh()->statut);

        // 2. Complete
        $responseComplete = $this->post("/admin/cashouts/{$cashout->id}/complete");
        $responseComplete->assertSessionHas('success');
        $this->assertEquals('complete', $cashout->fresh()->statut);

        // 3. Reject a new cashout
        $cashout2 = SupplierCashout::create([
            'reference' => SupplierCashout::generateReference(),
            'supplier_id' => $supplier->id,
            'beneficiary_name' => 'Koffi Paul',
            'beneficiary_phone' => '0701020304',
            'montant_brut' => 10000,
            'commission_rate' => 0.025,
            'montant_commission' => 250,
            'montant_net' => 9750,
            'statut' => 'en_attente',
            'mode_retrait' => 'wave',
        ]);

        $responseReject = $this->post("/admin/cashouts/{$cashout2->id}/reject", ['reason' => 'Pièce non valide']);
        $responseReject->assertSessionHas('success');
        $this->assertEquals('rejete', $cashout2->fresh()->statut);
    }
}
