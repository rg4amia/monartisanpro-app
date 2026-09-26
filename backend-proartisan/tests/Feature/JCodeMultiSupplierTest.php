<?php

namespace Tests\Feature;

use App\Jobs\PaySupplierJob;
use App\Models\FournisseurAgree;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\User;
use App\Services\JCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Geo;
use Tests\TestCase;

class JCodeMultiSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_can_generate_multi_supplier_jcode_via_api()
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'wallet_materiaux' => 100000,
        ]);

        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier Rénovation Multi-Comptoirs',
            'status' => 'funded_locked',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);

        $response = $this->actingAs($artisan)
            ->postJson('/api/v1/jcodes', [
                'mission_id' => $mission->id,
                'fournisseur_id' => null, // Multi-comptoirs
                'items' => [
                    [
                        'name' => 'Sacs de ciment CPJ 42.5',
                        'quantity' => 10,
                        'unit_price' => 5000,
                    ],
                    [
                        'name' => 'Fers à béton 10mm',
                        'quantity' => 5,
                        'unit_price' => 3000,
                    ],
                ],
                'montant' => 65000,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.is_multi_supplier', true);
        $response->assertJsonPath('data.montant', 65000);
        $response->assertJsonPath('data.statut', 'actif');

        $this->assertDatabaseHas('jcodes', [
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'fournisseur_id' => null,
            'montant' => 65000,
            'statut' => 'actif',
        ]);
    }

    public function test_multiple_suppliers_can_partially_redeem_multi_supplier_jcode()
    {
        Queue::fake();

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'wallet_materiaux' => 100000,
        ]);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        // Fournisseur A (Zone 1: Treichville)
        $fournisseurA = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);
        $faA = FournisseurAgree::create([
            'position' => Geo::point(),
            'user_id' => $fournisseurA->id,
            'nom_boutique' => 'Quincaillerie Centrale Treichville',
            'statut' => 'agree',
        ]);
        $faA->setPosition(5.3050, -4.0050);

        // Fournisseur B (Zone 2: Koumassi)
        $fournisseurB = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);
        $faB = FournisseurAgree::create([
            'position' => Geo::point(),
            'user_id' => $fournisseurB->id,
            'nom_boutique' => 'Quincaillerie Moderne Koumassi',
            'statut' => 'agree',
        ]);
        $faB->setPosition(5.2900, -3.9500);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier Multi-Comptoirs',
            'status' => 'funded_locked',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);

        $jCodeService = app(JCodeService::class);
        $jcode = $jCodeService->generate($mission, $artisan, null, [
            [
                'name' => 'Sacs de ciment CPJ 42.5',
                'quantity' => 10,
                'unit_price' => 5000,
            ],
            [
                'name' => 'Pot de peinture blanche 20L',
                'quantity' => 2,
                'unit_price' => 7500,
            ],
        ], 65000);

        $itemCiment = $jcode->items->where('item_name', 'Sacs de ciment CPJ 42.5')->first();
        $itemPeinture = $jcode->items->where('item_name', 'Pot de peinture blanche 20L')->first();

        // 1. Fournisseur A sert 4 sacs de ciment (4 * 5000 = 20000 FCFA)
        $responseA = $this->actingAs($fournisseurA)
            ->postJson("/api/v1/jcodes/{$jcode->code}/scan", [
                'lat' => 5.3050,
                'lng' => -4.0050,
                'served_items' => [
                    [
                        'jcode_item_id' => $itemCiment->id,
                        'quantity_served' => 4,
                    ],
                ],
            ]);

        $responseA->assertStatus(200);
        $responseA->assertJsonPath('success', true);
        $responseA->assertJsonPath('data.fully_consumed', false);
        $responseA->assertJsonPath('data.montant_servi', 20000);
        $responseA->assertJsonPath('data.montant_consomme', 20000);
        $responseA->assertJsonPath('data.montant_restant', 45000);
        $responseA->assertJsonPath('data.statut', 'partiellement_utilise');

        $this->assertDatabaseHas('jcodes', [
            'id' => $jcode->id,
            'statut' => 'partiellement_utilise',
            'montant_consomme' => 20000,
        ]);

        $this->assertDatabaseHas('jcode_redemptions', [
            'jcode_id' => $jcode->id,
            'fournisseur_id' => $fournisseurA->id,
            'montant' => 20000,
        ]);

        Queue::assertPushed(PaySupplierJob::class, function ($job) use ($jcode, $fournisseurA) {
            return $job->jcodeId === $jcode->id
                && $job->fournisseurId === $fournisseurA->id
                && $job->montantServi === 20000;
        });

        // 2. Fournisseur B sert le reste : 6 sacs de ciment (30000 FCFA) + 2 pots de peinture (15000 FCFA) = 45000 FCFA
        $responseB = $this->actingAs($fournisseurB)
            ->postJson("/api/v1/jcodes/{$jcode->code}/scan", [
                'lat' => 5.2900,
                'lng' => -3.9500,
                'served_items' => [
                    [
                        'jcode_item_id' => $itemCiment->id,
                        'quantity_served' => 6,
                    ],
                    [
                        'jcode_item_id' => $itemPeinture->id,
                        'quantity_served' => 2,
                    ],
                ],
            ]);

        $responseB->assertStatus(200);
        $responseB->assertJsonPath('success', true);
        $responseB->assertJsonPath('data.fully_consumed', true);
        $responseB->assertJsonPath('data.montant_servi', 45000);
        $responseB->assertJsonPath('data.montant_consomme', 65000);
        $responseB->assertJsonPath('data.montant_restant', 0);
        $responseB->assertJsonPath('data.statut', 'utilise');

        $this->assertDatabaseHas('jcodes', [
            'id' => $jcode->id,
            'statut' => 'utilise',
            'montant_consomme' => 65000,
        ]);

        $this->assertDatabaseHas('jcode_redemptions', [
            'jcode_id' => $jcode->id,
            'fournisseur_id' => $fournisseurB->id,
            'montant' => 45000,
        ]);

        Queue::assertPushed(PaySupplierJob::class, function ($job) use ($jcode, $fournisseurB) {
            return $job->jcodeId === $jcode->id
                && $job->fournisseurId === $fournisseurB->id
                && $job->montantServi === 45000;
        });

        $this->assertEquals(2, $jcode->redemptions()->count());

        // 3. GET /api/v1/jcodes/{jcode}/redemptions
        $redemptionsResp = $this->actingAs($artisan)
            ->getJson("/api/v1/jcodes/{$jcode->code}/redemptions");

        $redemptionsResp->assertStatus(200);
        $redemptionsResp->assertJsonPath('success', true);
        $this->assertCount(2, $redemptionsResp->json('data'));
    }

    public function test_multi_supplier_scan_fails_if_gps_distance_greater_than_100m_from_scanning_supplier()
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'wallet_materiaux' => 100000]);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);

        $fa = FournisseurAgree::create([
            'position' => Geo::point(),
            'user_id' => $fournisseur->id,
            'nom_boutique' => 'Quincaillerie Treichville',
            'statut' => 'agree',
        ]);
        $fa->setPosition(5.3050, -4.0050);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Test GPS multi-fournisseur',
            'status' => 'funded_locked',
            'montant_total' => 50000,
            'montant_materiaux' => 30000,
            'montant_mo' => 20000,
            'ratio_materiaux' => 0.60,
        ]);

        $jCodeService = app(JCodeService::class);
        $jcode = $jCodeService->generate($mission, $artisan, null, [
            [
                'name' => 'Tuyaux PVC',
                'quantity' => 10,
                'unit_price' => 3000,
            ],
        ], 30000);

        // Scan from 500m away
        $response = $this->actingAs($fournisseur)
            ->postJson("/api/v1/jcodes/{$jcode->code}/scan", [
                'lat' => 5.3100, // ~550m away
                'lng' => -4.0050,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps']);
    }
}
