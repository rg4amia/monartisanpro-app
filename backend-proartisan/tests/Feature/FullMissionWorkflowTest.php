<?php

namespace Tests\Feature;

use App\Jobs\PaySupplierJob;
use App\Models\FournisseurAgree;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\SupplierProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\JCodeService;
use App\Services\NotificationService;
use App\Services\OrangeMoneyService;
use App\Services\WalletService;
use App\Services\WaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullMissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_to_completion_happy_path(): void
    {
        User::factory()->create(['role' => 'admin', 'phone' => '+2250000000000']);
        Storage::fake('public');

        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $fournisseur = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);

        $agreement = FournisseurAgree::create([
            'user_id' => $fournisseur->id,
            'nom_boutique' => 'Quincaillerie Plateau',
            'statut' => 'agree',
            'approuve_at' => now(),
        ]);
        $agreement->setPosition(5.35, -4.02);

        $product = SupplierProduct::create([
            'supplier_id' => $fournisseur->id,
            'name' => 'Ciment Premium',
            'sku' => 'CIM-650',
            'unit_price' => 32500,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $missionResponse = $this->actingAs($client)
            ->postJson('/api/v1/missions', [
                'artisan_id' => $artisan->id,
                'category' => 'Maconnerie',
                'description' => 'Je veux refaire ma dalle de cuisine avec fourniture et pose complete a Abidjan.',
                'lat' => 5.351,
                'lng' => -4.021,
                'location_address' => 'Abidjan Plateau',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_artisan_acceptance');

        $missionId = $missionResponse->json('data.id');
        $mission = Mission::findOrFail($missionId);

        // Artisan accepte la demande de devis
        $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/accept-request")
            ->assertOk();

        $devisResponse = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'lignes' => [
                    [
                        'type' => 'mat',
                        'description' => 'Ciment Premium',
                        'montant' => 65000,
                    ],
                    [
                        'type' => 'mo',
                        'description' => 'Pose et finition',
                        'montant' => 35000,
                    ],
                ],
                'jalons' => [
                    [
                        'ordre' => 1,
                        'description' => 'Preparation et coulage',
                        'montant' => 15000,
                        'date_cible' => now()->addDay()->toDateString(),
                    ],
                    [
                        'ordre' => 2,
                        'description' => 'Finition du chantier',
                        'montant' => 20000,
                        'date_cible' => now()->addDays(2)->toDateString(),
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'soumis');

        $devisId = $devisResponse->json('data.id');

        $paymentResponse = $this->actingAs($client)
            ->postJson('/api/v1/payments/initiate', [
                'mission_id' => $mission->id,
                'devis_id' => $devisId,
                'montant' => 101950,
                'provider' => 'wave',
                'phone' => $client->phone,
            ])
            ->assertOk();

        $transactionId = $paymentResponse->json('data.transaction_id');

        $this->actingAs($client)
            ->getJson("/api/v1/payments/{$transactionId}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirme');

        $this->actingAs($client)
            ->postJson("/api/v1/devis/{$devisId}/accept", [
                'transaction_id' => $transactionId,
            ])
            ->assertOk()
            ->assertJsonPath('data.missionStatus', 'funded_locked');

        $mission->refresh();
        $artisan->refresh();

        $this->assertSame('funded_locked', (string) $mission->status);
        $this->assertSame(66950, $artisan->wallet_materiaux);
        $this->assertSame(35000, $artisan->wallet_mo);
        $this->assertCount(2, $mission->jalons);

        $jcodeResponse = $this->actingAs($artisan)
            ->postJson('/api/v1/jcodes', [
                'mission_id' => $mission->id,
                'fournisseur_id' => $fournisseur->id,
                'montant' => 65000,
                'items' => [
                    [
                        'supplier_product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.montant', 65000);

        $jcodeCode = $jcodeResponse->json('data.code');
        $jcodeId = $jcodeResponse->json('data.id');

        $this->actingAs($fournisseur)
            ->postJson("/api/v1/jcodes/{$jcodeCode}/scan", [
                'lat' => 5.35,
                'lng' => -4.02,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', true);

        $jcode = JCode::findOrFail($jcodeId);
        $this->runSupplierPaymentJob($jcode);

        $jcode->refresh();
        $artisan->refresh();
        $product->refresh();

        $this->assertSame('paye', $jcode->paiement_status);
        $this->assertSame(0, $artisan->wallet_materiaux);
        $this->assertSame(0, $product->stock_quantity);

        $this->actingAs($artisan)
            ->post("/api/v1/jcodes/{$jcode->id}/photo-materiaux", [
                'photo' => UploadedFile::fake()->image('materiaux.jpg'),
                'latitude' => 5.351,
                'longitude' => -4.021,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('data.jcode_id', $jcode->id);

        $jalons = $mission->fresh()->jalons()->orderBy('ordre')->get();

        foreach ($jalons as $index => $jalon) {
            $this->actingAs($artisan)
                ->putJson("/api/v1/jalons/{$jalon->id}/submit", [
                    'photos' => [
                        [
                            'url' => "https://example.test/jalon-{$jalon->id}.jpg",
                            'lat' => 5.351 + ($index / 1000),
                            'lng' => -4.021 - ($index / 1000),
                            'taken_at' => now()->toIso8601String(),
                        ],
                    ],
                ])
                ->assertOk();

            $this->actingAs($artisan)
                ->postJson("/api/v1/jalons/{$jalon->id}/request-otp")
                ->assertOk();

            $jalon->refresh();
            $this->assertNotNull($jalon->otp_code);

            $this->actingAs($client)
                ->postJson("/api/v1/jalons/{$jalon->id}/validate-otp", [
                    'otp' => $jalon->otp_code,
                ])
                ->assertOk();
        }

        $mission->refresh();
        $artisan->refresh();

        $this->assertSame('completed', (string) $mission->status);
        $this->assertSame(0, $artisan->wallet_mo);
        $this->assertTrue($mission->jalons()->where('statut', 'paye')->count() === 2);

        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $artisan->id,
                'note' => 5,
                'commentaire' => 'Mission terminee avec succes.',
                'fiabilite' => 5,
                'integrite' => 5,
                'qualite' => 5,
                'reactivite' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.scoreProsArtisan', 10);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'type' => 'acompte',
            'montant' => 101950,
            'statut' => 'confirme',
        ]);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'type' => 'paiement_fournisseur',
            'montant' => 61750,
            'statut' => 'confirme',
        ]);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $artisan->id,
            'type' => 'liberation_jalon',
            'montant' => 15000,
            'statut' => 'confirme',
        ]);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $artisan->id,
            'type' => 'liberation_jalon',
            'montant' => 20000,
            'statut' => 'confirme',
        ]);

        $this->assertSame(10, $artisan->fresh()->score_prosartisan);
    }

    private function runSupplierPaymentJob(JCode $jcode): void
    {
        (new PaySupplierJob($jcode->id))->handle(
            app(WalletService::class),
            app(NotificationService::class),
            app(JCodeService::class),
            app(WaveService::class),
            app(OrangeMoneyService::class),
        );
    }
}
