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
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

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
                'montant' => 100000,
                'provider' => 'wave',
                'phone' => $client->phone,
            ])
            ->assertOk();

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
        $this->assertSame(100000, $mission->montant_total);
        $this->assertSame(65000, $mission->montant_materiaux);
        $this->assertSame(35000, $mission->montant_mo);
        $this->assertSame(65000, $artisan->wallet_materiaux);
        $this->assertSame(35000, $artisan->wallet_mo);
        $this->assertCount(2, $mission->jalons);
    }

    public function test_payment_above_limit_blocks_mobile_money(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

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
                'montant' => 2500000,
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
                'montant' => 2500000,
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
}
