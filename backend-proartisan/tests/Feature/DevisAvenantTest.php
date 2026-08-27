<?php

namespace Tests\Feature;

use App\Models\Devis;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevisAvenantTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $artisan;
    private User $unassignedArtisan;
    private User $supplier;
    private SupplierProduct $product;
    private Mission $mission;
    private Devis $initialDevis;
    private Transaction $initialPayment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
            'payment_phone' => '+2250102030405',
            'preferred_payment_provider' => 'wave',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'payment_phone' => '+2250506070809',
            'preferred_payment_provider' => 'wave',
        ]);

        $this->unassignedArtisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'payment_phone' => '+2250908070605',
            'preferred_payment_provider' => 'wave',
        ]);

        $this->supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);

        // Seed a supplier product to avoid validation.exists error
        $this->product = SupplierProduct::create([
            'id' => 1,
            'supplier_id' => $this->supplier->id,
            'name' => 'Ciment sac',
            'sku' => 'CIM-123',
            'unit_price' => 6000,
            'stock_quantity' => 100,
        ]);

        // 1. Create a mission
        $this->mission = Mission::create([
            'client_id' => $this->client->id,
            'description' => 'Chantier test pour avenants de devis',
            'status' => 'draft',
        ]);

        // 2. Submit initial devis
        $this->initialDevis = Devis::create([
            'mission_id' => $this->mission->id,
            'artisan_id' => $this->artisan->id,
            'materials_required' => false,
            'intervention_type_id' => 1,
            'commission_service_ratio' => 0.10,
            'lignes_json' => [
                ['type' => 'mo', 'description' => 'Main d\'oeuvre initiale', 'montant' => 100000],
            ],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Jalon initial 1', 'montant' => 50000, 'date_cible' => now()->addDays(5)->toDateString()],
                ['ordre' => 2, 'description' => 'Jalon initial 2', 'montant' => 50000, 'date_cible' => now()->addDays(10)->toDateString()],
            ],
            'statut' => 'soumis',
            'is_avenant' => false,
        ]);

        // 3. Confirm payment transaction
        $this->initialPayment = Transaction::create([
            'mission_id' => $this->mission->id,
            'user_id' => $this->client->id,
            'type' => 'acompte',
            'montant' => $this->initialDevis->montant_total,
            'wallet_source' => 'client_mobile_money',
            'wallet_dest' => 'escrow_mission_' . $this->mission->id,
            'provider' => 'wave',
            'statut' => 'confirme',
            'reference_externe' => 'TXN-INITIAL-123',
            'metadata' => ['devis_id' => $this->initialDevis->id],
        ]);

        // 4. Accept initial devis -> Mission funded
        app(\App\Services\DevisService::class)->accept($this->initialDevis, $this->initialPayment);
        
        $this->mission->refresh();
        // Transition to in_progress to simulate ongoing work
        $this->mission->status->transitionTo(\App\States\Mission\InProgressState::class);
        $this->mission->refresh();
    }

    public function test_artisan_assigned_can_create_avenant_on_active_mission(): void
    {
        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis", [
                'is_avenant' => true,
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre additionnelle', 'montant' => 30000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon avenant 1', 'montant' => 30000, 'date_cible' => now()->addDays(15)->toDateString()],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.isAvenant', true);
        $response->assertJsonPath('data.parentDevisId', $this->initialDevis->id);
        $response->assertJsonPath('data.statut', 'soumis');
    }

    public function test_cannot_create_avenant_if_no_initial_devis_accepted(): void
    {
        // Create another mission with no devis
        $otherMission = Mission::create([
            'client_id' => $this->client->id,
            'description' => 'Mission sans devis accepté',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$otherMission->id}/devis", [
                'is_avenant' => true,
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Travaux supp', 'montant' => 10000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon supp', 'montant' => 10000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Impossible de créer un avenant sans devis initial accepté.'
        ]);
    }

    public function test_cannot_create_avenant_if_artisan_is_not_assigned_to_mission(): void
    {
        $response = $this->actingAs($this->unassignedArtisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis", [
                'is_avenant' => true,
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Travaux supp', 'montant' => 10000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon supp', 'montant' => 10000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Seul l'artisan assigné à cette mission peut créer un avenant."
        ]);
    }

    public function test_cannot_create_multiple_pending_avenants_at_the_same_time(): void
    {
        // First avenant submission -> Success
        $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis", [
                'is_avenant' => true,
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Travaux supp 1', 'montant' => 10000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon supp 1', 'montant' => 10000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ])->assertCreated();

        // Second avenant submission -> Fails
        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis", [
                'is_avenant' => true,
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Travaux supp 2', 'montant' => 20000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon supp 2', 'montant' => 20000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Un avenant est déjà en cours d'examen pour cette mission."
        ]);
    }

    public function test_accepting_and_funding_avenant_updates_escrow_and_appends_jalons(): void
    {
        // 1. Submit an avenant with materials and labor
        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis", [
                'is_avenant' => true,
                'materials_required' => true,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'MO Avenant', 'montant' => 20000],
                    [
                        'type' => 'mat',
                        'description' => 'Ciment supplémentaire',
                        'montant' => 30000,
                        'source' => 'catalog',
                        'quantity' => 5,
                        'unit_price' => 6000,
                        'supplier_product_id' => $this->product->id
                    ],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon Avenant 1', 'montant' => 20000, 'date_cible' => now()->addDays(15)->toDateString()],
                ],
            ]);
        $response->assertCreated();
        $avenantId = $response->json('data.id');
        $avenant = Devis::findOrFail($avenantId);

        // Record initial wallet balances
        $initialWalletMo = $this->artisan->fresh()->wallet_mo;
        $initialWalletMat = $this->artisan->fresh()->wallet_materiaux;

        // Record initial mission totals
        $initialMissionTotal = $this->mission->montant_total;
        $initialMissionMat = $this->mission->montant_materiaux;
        $initialMissionMo = $this->mission->montant_mo;

        // 2. Create the payment transaction for the avenant
        $payment = Transaction::create([
            'mission_id' => $this->mission->id,
            'user_id' => $this->client->id,
            'type' => 'acompte',
            'montant' => $avenant->montant_total,
            'wallet_source' => 'client_mobile_money',
            'wallet_dest' => 'escrow_mission_' . $this->mission->id,
            'provider' => 'wave',
            'statut' => 'confirme',
            'reference_externe' => 'TXN-AVENANT-123',
            'metadata' => ['devis_id' => $avenant->id],
        ]);

        // 3. Accept the avenant devis
        $acceptResponse = $this->actingAs($this->client)
            ->postJson("/api/v1/devis/{$avenant->id}/accept", [
                'transaction_id' => $payment->id,
            ]);

        $acceptResponse->assertOk();
        $acceptResponse->assertJsonPath('success', true);
        $acceptResponse->assertJsonPath('message', 'Avenant accepté et séquestre mis à jour.');

        // 4. Verify balances incremented correctly
        $artisanFresh = $this->artisan->fresh();
        $this->assertEquals($initialWalletMo + $avenant->montant_mo, $artisanFresh->wallet_mo);
        $this->assertEquals($initialWalletMat + $avenant->montant_materiaux, $artisanFresh->wallet_materiaux);

        // 5. Verify mission total amounts incremented correctly
        $missionFresh = $this->mission->fresh();
        $this->assertEquals($initialMissionTotal + $avenant->montant_total, $missionFresh->montant_total);
        $this->assertEquals($initialMissionMat + $avenant->montant_materiaux, $missionFresh->montant_materiaux);
        $this->assertEquals($initialMissionMo + $avenant->montant_mo, $missionFresh->montant_mo);

        // 6. Verify mission status remains in_progress
        $this->assertEquals('in_progress', (string) $missionFresh->status);

        // 7. Verify new jalon created with correct ordre (max existing ordre was 2, so new jalon should be ordre 3)
        $jalons = Jalon::where('mission_id', $this->mission->id)->orderBy('ordre')->get();
        $this->assertCount(3, $jalons);
        
        $lastJalon = $jalons->last();
        $this->assertEquals(3, $lastJalon->ordre);
        $this->assertEquals('Jalon Avenant 1', $lastJalon->description);
        $this->assertEquals('en_attente', $lastJalon->statut);
    }
}
