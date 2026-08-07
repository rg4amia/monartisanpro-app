<?php

namespace Tests\Feature;

use App\Models\Devis;
use App\Models\Mission;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevisGestionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_cannot_submit_multiple_devis_until_previous_is_refused(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'description' => 'Test mission description long enough',
            'status' => 'draft',
        ]);

        // Submit first devis
        $response1 = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 50000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $response1->assertCreated();

        // Submit second devis - should fail (422) because the previous devis is active ('soumis')
        $response2 = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre 2', 'montant' => 60000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 60000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $response2->assertStatus(422);
        $response2->assertJsonPath('success', false);
        $response2->assertJsonFragment([
            'message' => 'Vous avez déjà soumis un devis pour cette mission. Vous devez attendre que le client le refuse ou l\'accepte.'
        ]);

        // Refuse the first devis
        $devisId = $response1->json('data.id');
        $devis = Devis::findOrFail($devisId);
        $devis->update(['statut' => 'refuse']);

        // Now submitting should succeed
        $response3 = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre 2', 'montant' => 60000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 60000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $response3->assertCreated();
    }

    public function test_mission_cannot_be_processed_when_devis_is_pending(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $artisan2 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'description' => 'Test mission description long enough',
            'status' => 'draft',
        ]);

        // Submit devis by artisan 1
        $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 50000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ])
            ->assertCreated();

        // 1. Try to submit devis by artisan 2 - should fail (422) because mission has pending devis
        $response2 = $this->actingAs($artisan2)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 50000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $response2->assertStatus(422);
        $response2->assertJsonPath('success', false);
        $response2->assertJsonFragment([
            'message' => "Cette mission a déjà un devis en cours d'examen par le client."
        ]);

        // 2. Try to update status of the mission - should fail (422)
        $response3 = $this->actingAs($client)
            ->putJson("/api/v1/missions/{$mission->id}/status", [
                'status' => 'in_progress',
            ]);
        $response3->assertStatus(422);

        // 3. Verify mention exists in resource representation
        $responseShow = $this->actingAs($client)
            ->getJson("/api/v1/missions/{$mission->id}");
        $responseShow->assertOk();
        $responseShow->assertJsonPath('data.mention', 'En attente de validation du devis');
    }

    public function test_payment_simulation_flow(): void
    {
        config(['app.env' => 'local']);

        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'description' => 'Rénovation cuisine',
            'status' => 'draft',
        ]);

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'statut' => 'soumis',
            'lignes_json' => [
                ['type' => 'mo', 'description' => 'Pose', 'montant' => 35000],
            ],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Finition', 'montant' => 35000, 'date_cible' => now()->addDays(5)->toDateString()],
            ],
        ]);

        // Initiate payment
        $response = $this->actingAs($client)
            ->postJson('/api/v1/payments/initiate', [
                'mission_id' => $mission->id,
                'devis_id' => $devis->id,
                'montant' => 35000,
                'provider' => 'wave',
                'phone' => '+2250102030405',
            ]);
        $response->assertOk();
        $transactionId = $response->json('data.transaction_id');

        // Check payment status - should be 'pending' in local simulator until confirmed
        $responseStatus1 = $this->actingAs($client)
            ->getJson("/api/v1/payments/{$transactionId}/status");
        $responseStatus1->assertOk();
        $responseStatus1->assertJsonPath('data.status', 'en_attente');

        // Open simulator page
        $this->get(route('payment.mock.pay', ['transaction_id' => $transactionId]))
            ->assertOk()
            ->assertSee('Simulation de Paiement');

        // Post confirmation
        $this->post(route('payment.mock.validate'), [
            'transaction_id' => $transactionId,
            'action' => 'confirm',
        ])->assertRedirect(route('home'));

        // Check payment status again - should be confirmed
        $responseStatus2 = $this->actingAs($client)
            ->getJson("/api/v1/payments/{$transactionId}/status");
        $responseStatus2->assertOk();
        $responseStatus2->assertJsonPath('data.status', 'confirme');
    }

    public function test_materials_required_forces_supplier_product(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'description' => 'Installation climatisation',
            'status' => 'draft',
        ]);

        // Submit without supplier product when materials_required is true (default) -> should fail
        $response = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => true,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 50000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Le devis doit contenir au moins un article d'un fournisseur agréé car l'acquisition de matériel est requise."
        ]);

        // Create supplier and product
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $product = \App\Models\SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'sku' => 'PROD-XYZ',
            'name' => 'Câble électrique',
            'unit_price' => 15000,
            'stock_quantity' => 100,
        ]);

        // Submit with supplier product -> should succeed
        $responseSucceed = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => true,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                    ['type' => 'mat', 'description' => 'Câble', 'montant' => 15000, 'source' => 'catalog', 'supplier_product_id' => $product->id, 'quantity' => 1],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 65000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $responseSucceed->assertCreated();
    }

    public function test_materials_not_required_requires_intervention_type_and_conditional_labor(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'description' => 'Petit mur',
            'status' => 'draft',
        ]);

        // 1. Submit without intervention_type_id when materials_required is false -> should fail
        $response = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 50000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Veuillez indiquer le type d'intervention pour ce devis sans matériel."
        ]);

        // Create an intervention type that does not require labor (e.g. Consulting/Inspection)
        $consultingType = \App\Models\InterventionType::create([
            'name' => 'Diagnostic Gratuit',
            'requires_labor' => false,
        ]);

        // 2. Submit with diagnostic type and no labor -> should succeed
        $responseNoLabor = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'intervention_type_id' => $consultingType->id,
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 1000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $responseNoLabor->assertCreated();
    }

    public function test_artisan_stock_usage_restricted_to_night_mode(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'description' => 'Soudure de nuit',
            'status' => 'draft',
        ]);

        // Declare artisan stock
        $stockItem = \App\Models\ArtisanStock::create([
            'artisan_id' => $artisan->id,
            'description' => 'Baguettes de soudure',
            'quantity' => 10,
            'unit_cost' => 2000,
            'condition' => 'neuf',
        ]);

        // 1. Try to use stock during the day -> should fail
        \Illuminate\Support\Carbon::setTestNow('2026-08-07 10:00:00'); // 10h AM (Daytime)
        $responseDay = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Soudure', 'montant' => 30000],
                    ['type' => 'mat', 'description' => 'Baguettes', 'montant' => 2000, 'source' => 'artisan_stock', 'artisan_stock_id' => $stockItem->id, 'quantity' => 2],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 34000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $responseDay->assertStatus(422);
        $responseDay->assertJsonFragment([
            'message' => "L'utilisation du stock de matériel de l'artisan est strictement réservée au mode nuit (18h-06h)."
        ]);

        // 2. Use stock during the night -> should succeed, inject condition, and apply specific commission
        \Illuminate\Support\Carbon::setTestNow('2026-08-07 22:00:00'); // 10h PM (Night time)
        $responseNight = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'materials_required' => false,
                'intervention_type_id' => 1,
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Soudure', 'montant' => 30000],
                    ['type' => 'mat', 'description' => 'Baguettes', 'montant' => 2000, 'source' => 'artisan_stock', 'artisan_stock_id' => $stockItem->id, 'quantity' => 2],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 34000, 'date_cible' => now()->addDays(5)->toDateString()],
                ],
            ]);
        $responseNight->assertCreated();
        $responseNight->assertJsonPath('data.lignesJson.1.condition', 'neuf');

        // Test commission calculation:
        // Raw MO = 30000, raw artisan stock mat = 2000 (quantity=2, wait: 'montant' is quantity * unitPrice so it's 2000 raw total or quantity * unit_price.
        // Wait, in normalizeLigne: unit_price was not supplied, so unitPrice is round(montant/quantity) = 1000.
        // The total raw mat in devis is 2000.
        // commission_artisan_stock = 0.05. 2000 * 1.05 = 2100.
        // Let's assert the serialized montantMateriaux is 2100.
        $responseNight->assertJsonPath('data.montantMateriaux', 2100);

        \Illuminate\Support\Carbon::setTestNow(); // Reset time
    }
}
