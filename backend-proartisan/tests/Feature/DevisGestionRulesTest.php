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
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 50000, 'date_cible' => '2026-08-01'],
                ],
            ]);
        $response1->assertCreated();

        // Submit second devis - should fail (422) because the previous devis is active ('soumis')
        $response2 = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre 2', 'montant' => 60000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 60000, 'date_cible' => '2026-08-01'],
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
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre 2', 'montant' => 60000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 60000, 'date_cible' => '2026-08-01'],
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
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 50000, 'date_cible' => '2026-08-01'],
                ],
            ])
            ->assertCreated();

        // 1. Try to submit devis by artisan 2 - should fail (422) because mission has pending devis
        $response2 = $this->actingAs($artisan2)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'lignes' => [
                    ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 50000],
                ],
                'jalons' => [
                    ['ordre' => 1, 'description' => 'Jalon 1', 'montant' => 50000, 'date_cible' => '2026-08-01'],
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
                ['ordre' => 1, 'description' => 'Finition', 'montant' => 35000, 'date_cible' => '2026-08-01'],
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
}
