<?php

namespace Tests\Feature;

use App\Models\Devis;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevisPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_must_confirm_payment_before_financing_a_devis(): void
    {
        /** @var \App\Models\User $client */
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        /** @var \App\Models\User $artisan */
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => null,
            'description' => 'Rénovation cuisine',
            'status' => 'draft',
        ]);

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'statut' => 'soumis',
            'commission_service_ratio' => 0.00,
            'lignes_json' => [
                ['type' => 'mat', 'description' => 'Carrelage', 'montant' => 65000],
                ['type' => 'mo', 'description' => 'Pose', 'montant' => 35000],
            ],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Préparation', 'montant' => 50000, 'date_cible' => '2026-03-25'],
                ['ordre' => 2, 'description' => 'Finition', 'montant' => 50000, 'date_cible' => '2026-03-27'],
            ],
        ]);

        $paymentResponse = $this->actingAs($client)
            ->postJson('/api/v1/payments/initiate', [
                'mission_id' => $mission->id,
                'devis_id' => $devis->id,
                'montant' => 101950,
                'provider' => 'wave',
                'phone' => $client->phone,
            ]);
        $paymentResponse->assertOk();

        $transactionId = $paymentResponse->json('data.transaction_id');

        $this->actingAs($client)
            ->getJson("/api/v1/payments/{$transactionId}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirme');

        $transaction = Transaction::findOrFail($transactionId);

        $this->assertTrue($transaction->statut->isSuccessful());
        $this->assertSame('draft', (string) $mission->fresh()->status);
        $this->assertSame('soumis', $devis->fresh()->statut);
        $this->assertSame(0, $artisan->fresh()->wallet_materiaux);
        $this->assertSame(0, $artisan->fresh()->wallet_mo);

        $this->actingAs($client)
            ->postJson("/api/v1/devis/{$devis->id}/accept", [
                'transaction_id' => $transactionId,
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'accepte')
            ->assertJsonPath('data.missionStatus', 'funded_locked');

        $mission->refresh();
        $devis->refresh();
        $artisan->refresh();

        $this->assertSame('accepte', $devis->statut);
        $this->assertSame('funded_locked', (string) $mission->status);
        $this->assertSame($artisan->id, $mission->artisan_id);
        $this->assertSame(101950, $mission->montant_total);
        $this->assertSame(66950, $mission->montant_materiaux);
        $this->assertSame(35000, $mission->montant_mo);
        $this->assertSame(66950, $artisan->wallet_materiaux);
        $this->assertSame(35000, $artisan->wallet_mo);
        $this->assertCount(2, $mission->jalons);
    }

    public function test_payment_above_limit_blocks_mobile_money(): void
    {
        /** @var \App\Models\User $client */
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        /** @var \App\Models\User $artisan */
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => null,
            'description' => 'Gros Oeuvre Villa',
            'status' => 'draft',
        ]);

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'statut' => 'soumis',
            'commission_service_ratio' => 0.00,
            'lignes_json' => [
                ['type' => 'mat', 'description' => 'Béton', 'montant' => 1500000],
                ['type' => 'mo', 'description' => 'Main d\'oeuvre', 'montant' => 1000000],
            ],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Total', 'montant' => 2500000, 'date_cible' => '2026-03-25'],
            ],
        ]);

        // Attempt Wave payment -> should fail
        $this->actingAs($client)
            ->postJson('/api/v1/payments/initiate', [
                'mission_id' => $mission->id,
                'devis_id' => $devis->id,
                'montant' => 2545000,
                'provider' => 'wave',
                'phone' => $client->phone,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Attempt Bank Transfer -> should succeed and return instructions
        $response = $this->actingAs($client)
            ->postJson('/api/v1/payments/initiate', [
                'mission_id' => $mission->id,
                'devis_id' => $devis->id,
                'montant' => 2545000,
                'provider' => 'virement_bancaire',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'transaction_id',
                    'devis_id',
                    'provider',
                    'virement_instructions' => [
                        'bank_name',
                        'account_name',
                        'iban',
                        'reference',
                    ],
                ],
            ]);
    }

    public function test_devis_refusal_notifies_artisan(): void
    {
        /** @var \App\Models\User $client */
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        /** @var \App\Models\User $artisan */
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => null,
            'description' => 'Rénovation toiture',
            'status' => 'draft',
        ]);

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'statut' => 'soumis',
            'commission_service_ratio' => 0.00,
            'lignes_json' => [
                ['type' => 'mo', 'description' => 'Pose de tuiles', 'montant' => 80000],
            ],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Finition', 'montant' => 80000, 'date_cible' => '2026-03-25'],
            ],
        ]);

        $this->actingAs($client)
            ->postJson("/api/v1/devis/{$devis->id}/refuse")
            ->assertOk();

        $this->assertSame('refuse', $devis->fresh()->statut);

        // Verify notification exists in database
        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => 'devis',
            'title' => 'Devis refusé',
        ]);
    }

    public function test_duplicate_payment_initiation_reuses_existing_transaction(): void
    {
        /** @var \App\Models\User $client */
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        /** @var \App\Models\User $artisan */
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => null,
            'description' => 'Test double paiement',
            'status' => 'draft',
        ]);

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'statut' => 'soumis',
            'commission_service_ratio' => 0.00,
            'lignes_json' => [
                ['type' => 'mo', 'description' => 'Tâche unique', 'montant' => 50000],
            ],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Etape 1', 'montant' => 50000, 'date_cible' => '2026-03-25'],
            ],
        ]);

        // First payment initiation
        $response1 = $this->actingAs($client)
            ->postJson('/api/v1/payments/initiate', [
                'mission_id' => $mission->id,
                'devis_id' => $devis->id,
                'montant' => $devis->montant_total,
                'provider' => 'wave',
                'phone' => $client->phone,
            ]);
        $response1->assertOk();
        $txId1 = $response1->json('data.transaction_id');

        // Second payment initiation (identical)
        $response2 = $this->actingAs($client)
            ->postJson('/api/v1/payments/initiate', [
                'mission_id' => $mission->id,
                'devis_id' => $devis->id,
                'montant' => $devis->montant_total,
                'provider' => 'wave',
                'phone' => $client->phone,
            ]);
        $response2->assertOk();
        $txId2 = $response2->json('data.transaction_id');

        // Assert they are the same transaction
        $this->assertSame($txId1, $txId2);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_devis_processing_validation_rules_and_restrictions(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $otherUser = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => null,
            'description' => 'Test validations',
            'status' => 'draft',
        ]);

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'statut' => 'soumis',
            'commission_service_ratio' => 0.00,
            'lignes_json' => [
                ['type' => 'mo', 'description' => 'Tâche', 'montant' => 50000],
            ],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Etape 1', 'montant' => 50000, 'date_cible' => '2026-03-25'],
            ],
        ]);

        // 1. Another user tries to accept or refuse -> should fail with 403
        $this->actingAs($otherUser)
            ->postJson("/api/v1/devis/{$devis->id}/accept", ['transaction_id' => 1])
            ->assertStatus(403);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/devis/{$devis->id}/refuse")
            ->assertStatus(403);

        // 2. Accept with a pending (unconfirmed) transaction -> should fail with 422
        $txPending = Transaction::create([
            'mission_id' => $mission->id,
            'user_id' => $client->id,
            'type' => 'acompte',
            'montant' => 50000,
            'wallet_source' => 'client_wallet',
            'wallet_dest' => 'escrow_wallet',
            'provider' => 'wave',
            'statut' => 'en_attente',
        ]);
        $txPending->metadata = ['devis_id' => $devis->id];
        $txPending->save();

        $this->actingAs($client)
            ->postJson("/api/v1/devis/{$devis->id}/accept", ['transaction_id' => $txPending->id])
            ->assertStatus(422);

        // 3. Accept with a confirmed transaction but matching a different devis -> should fail with 422
        $txWrongDevis = Transaction::create([
            'mission_id' => $mission->id,
            'user_id' => $client->id,
            'type' => 'acompte',
            'montant' => 50000,
            'wallet_source' => 'client_wallet',
            'wallet_dest' => 'escrow_wallet',
            'provider' => 'wave',
            'statut' => 'confirme',
        ]);
        $txWrongDevis->metadata = ['devis_id' => 9999]; // different devis
        $txWrongDevis->save();

        $this->actingAs($client)
            ->postJson("/api/v1/devis/{$devis->id}/accept", ['transaction_id' => $txWrongDevis->id])
            ->assertStatus(422);

        // 4. Refuse the devis
        $this->actingAs($client)
            ->postJson("/api/v1/devis/{$devis->id}/refuse")
            ->assertOk();

        // 5. Try to accept a refused devis -> should fail with 422
        $txConfirmed = Transaction::create([
            'mission_id' => $mission->id,
            'user_id' => $client->id,
            'type' => 'acompte',
            'montant' => 50000,
            'wallet_source' => 'client_wallet',
            'wallet_dest' => 'escrow_wallet',
            'provider' => 'wave',
            'statut' => 'confirme',
        ]);
        $txConfirmed->metadata = ['devis_id' => $devis->id];
        $txConfirmed->save();

        $this->actingAs($client)
            ->postJson("/api/v1/devis/{$devis->id}/accept", ['transaction_id' => $txConfirmed->id])
            ->assertStatus(422);

        // 6. Try to refuse an already refused devis -> should fail with 422
        $this->actingAs($client)
            ->postJson("/api/v1/devis/{$devis->id}/refuse")
            ->assertStatus(422);
    }
}
