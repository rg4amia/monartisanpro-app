<?php

namespace Tests\Feature;

use App\Models\JCode;
use App\Models\Mission;
use App\Models\User;
use App\Models\FournisseurAgree;
use App\Services\GeoService;
use App\Services\JCodeService;
use App\Services\WalletService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\PaySupplierJob;
use Tests\TestCase;

class JCodeComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_jcode_scan_schedules_j_plus_1_payment()
    {
        Queue::fake();

        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'wallet_materiaux' => 100000]);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        
        // Setup fournisseur agree
        FournisseurAgree::create([
            'user_id' => $fournisseur->id,
            'nom_boutique' => 'Test Shop',
            'position' => '5.3,-4.0', // Simple string for sqlite tests
            'statut' => 'agree'
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Test Mission',
            'status' => 'financee',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65
        ]);

        $jCodeService = app(JCodeService::class);
        $jcode = $jCodeService->generate($mission, $artisan, 50000);

        // Act - Scan at same position
        $jCodeService->scan($jcode, $fournisseur, 5.3, -4.0);

        // Assert
        $this->assertEquals('utilise', $jcode->fresh()->statut);
        $this->assertEquals('programme', $jcode->fresh()->paiement_status);
        
        Queue::assertPushed(PaySupplierJob::class);
    }

    public function test_jcode_scan_fails_if_gps_distance_too_large()
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'wallet_materiaux' => 100000]);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        
        FournisseurAgree::create([
            'user_id' => $fournisseur->id,
            'nom_boutique' => 'Test Shop',
            'position' => '5.3,-4.0', // Simple string for sqlite tests
            'statut' => 'agree'
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Test Mission',
            'status' => 'financee',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65
        ]);

        $jCodeService = app(JCodeService::class);
        $jcode = $jCodeService->generate($mission, $artisan, 50000);

        // Act & Assert - Scan far away (1 degree lat difference is ~111km)
        try {
            $jCodeService->scan($jcode, $fournisseur, 6.3, -4.0);
            $this->fail('ValidationException was expected but not thrown.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('gps', $e->errors());
        }
    }
}
