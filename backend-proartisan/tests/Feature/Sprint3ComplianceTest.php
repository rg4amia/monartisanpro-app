<?php

namespace Tests\Feature;

use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use App\Models\JuryReview;
use App\Services\OtpService;
use App\Services\ScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint3ComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_fingerprint_binding_and_score_freezing(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'phone' => '+2250102030405',
            'kyc_status' => 'actif',
            'score_nzassa' => 300,
            'device_fingerprint' => null,
            'score_frozen' => false,
        ]);

        $otpService = app(OtpService::class);
        $otpService->sendOtp($artisan->phone);
        $otp = \Illuminate\Support\Facades\DB::table('otps')->where('phone', $artisan->phone)->value('code');

        // First login binds the fingerprint
        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $artisan->phone,
            'otp' => $otp,
            'device_fingerprint' => 'device-12345',
        ]);

        $response->assertOk();
        $this->assertSame('device-12345', $artisan->fresh()->device_fingerprint);
        $this->assertFalse($artisan->fresh()->score_frozen);

        // Second login with different fingerprint alerts and freezes the score
        $otpService->sendOtp($artisan->phone);
        $otp2 = \Illuminate\Support\Facades\DB::table('otps')->where('phone', $artisan->phone)->orderByDesc('id')->value('code');

        $response2 = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $artisan->phone,
            'otp' => $otp2,
            'device_fingerprint' => 'device-suspect-999',
        ]);

        $response2->assertOk();
        $artisan->refresh();
        $this->assertSame('device-suspect-999', $artisan->device_fingerprint);
        $this->assertTrue($artisan->score_frozen);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => 'security_alert',
        ]);

        // Attempting to recalculate score shouldn't change the score since it is frozen
        $scoreService = app(ScoreService::class);
        $scoreService->recordEvent($artisan, 'success_mission', null, null, 'Attempting change', 1.0);

        $this->assertSame(300, $artisan->fresh()->score_nzassa);
    }

    public function test_jury_nzassa_assignment_voting_and_consensus(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        // Create 3 eligible jurors
        /** @var User $jure1 */
        $jure1 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_nzassa' => 900]);
        $jure1->artisanProfile()->create(['experience_years' => 5]);

        /** @var User $jure2 */
        $jure2 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_nzassa' => 950]);
        $jure2->artisanProfile()->create(['experience_years' => 8]);

        /** @var User $jure3 */
        $jure3 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_nzassa' => 850]);
        $jure3->artisanProfile()->create(['experience_years' => 3]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Toiture défaillante',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ]);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'malfaçon',
            'description' => 'Fuite persistante',
            'statut' => 'ouvert',
            'workflow_step' => 'preuves',
        ]);

        // Assign jury
        $response = $this->actingAs($admin)
            ->postJson("/api/v1/litiges/{$litige->id}/jury/assign");

        $response->assertOk();
        $this->assertSame('jury', $litige->fresh()->workflow_step);

        $juryReviews = JuryReview::where('litige_id', $litige->id)->get();
        $this->assertCount(3, $juryReviews);

        // Juror 1 votes CONFORME
        $response1 = $this->actingAs($jure1)
            ->postJson("/api/v1/litiges/{$litige->id}/jury/vote", ['verdict' => 'CONFORME']);
        $response1->assertOk();
        $this->assertSame(1500, $jure1->fresh()->wallet_mo);

        // Juror 2 votes CONFORME
        $response2 = $this->actingAs($jure2)
            ->postJson("/api/v1/litiges/{$litige->id}/jury/vote", ['verdict' => 'CONFORME']);
        $response2->assertOk();
        $this->assertSame(1500, $jure2->fresh()->wallet_mo);

        // Juror 3 votes NON_CONFORME -> triggers automatic consensus resolution (2 conforme -> artisan wins)
        $response3 = $this->actingAs($jure3)
            ->postJson("/api/v1/litiges/{$litige->id}/jury/vote", ['verdict' => 'NON_CONFORME']);
        $response3->assertOk();
        $this->assertSame(1500, $jure3->fresh()->wallet_mo);

        // Litige should be resolved in favor of the artisan
        $litige->refresh();
        $this->assertSame('resolu', $litige->statut);
        $this->assertSame('artisan', $litige->decision);
        $this->assertSame('jury_consensual_conforme', $litige->resolution_reason);
    }

    public function test_llm_mediation_endpoint(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Toiture défaillante',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ]);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'malfaçon',
            'description' => 'Fuite persistante',
            'statut' => 'ouvert',
            'workflow_step' => 'preuves',
        ]);

        $response = $this->actingAs($client)
            ->postJson("/api/v1/litiges/{$litige->id}/llm-mediation", [
                'message' => 'Je suis vraiment très en colère car le toit fuit toujours après son passage et il ne répond plus au téléphone.',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'mediation']);
    }

    public function test_kyc_restriction_and_notifications(): void
    {
        // 1. Create client, artisan, supplier and driver with 'en_attente' kyc status
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'en_attente']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'en_attente']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'en_attente']);
        $driver = User::factory()->create(['role' => 'driver', 'kyc_status' => 'en_attente']);

        assert($client instanceof \Illuminate\Contracts\Auth\Authenticatable);
        assert($artisan instanceof \Illuminate\Contracts\Auth\Authenticatable);
        assert($supplier instanceof \Illuminate\Contracts\Auth\Authenticatable);
        assert($driver instanceof \Illuminate\Contracts\Auth\Authenticatable);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Test',
            'status' => 'draft',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ]);

        $agree = \App\Models\FournisseurAgree::create([
            'user_id' => $supplier->id,
            'nom_boutique' => 'Test Boutique',
            'statut' => 'agree',
        ]);
        $agree->setPosition(5.36, -4.01);

        $product = \App\Models\SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'sku' => 'TESTPRODUCT',
            'name' => 'Test Product',
            'unit_price' => 1000,
            'stock_quantity' => 10,
        ]);

        $order = app(\App\Services\OrderService::class)->createOrder(
            $client,
            $supplier,
            [['supplier_product_id' => $product->id, 'quantity' => 1]],
            'delivery'
        );

        $order->update(['status' => 'searching_driver']);

        // 2. Client is blocked from listing artisans
        $this->actingAs($client)->getJson('/api/v1/artisans')->assertStatus(403);

        // 3. Client is blocked from submitting a mission
        $this->actingAs($client)->postJson('/api/v1/missions', [
            'artisan_id' => $artisan->id,
            'description' => 'Test mission description',
            'montant_total' => 50000,
            'montant_materiaux' => 30000,
            'montant_mo' => 20000,
            'ratio_materiaux' => 0.6,
        ])->assertStatus(403);

        // 4. Client is blocked from listing suppliers
        $this->actingAs($client)->getJson('/api/v1/fournisseurs')->assertStatus(403);

        // 5. Artisan is blocked from creating a devis
        $this->actingAs($artisan)->postJson("/api/v1/missions/{$mission->id}/devis", [
            'lignes_json' => [],
            'jalons_json' => [],
        ])->assertStatus(403);

        // 6. Supplier is blocked from managing catalog items
        $this->actingAs($supplier)->postJson('/api/v1/supplier-products', [
            'sku' => 'TESTSKU',
            'name' => 'Test product',
            'unit_price' => 5000,
            'stock_quantity' => 10,
        ])->assertStatus(403);

        // 7. Driver/Livreur is blocked from accepting a delivery course
        $this->actingAs($driver)->postJson("/api/v1/deliveries/{$order->id}/accept")->assertStatus(403);

        // 8. Test that notifications are created when document uploaded
        $file = \Illuminate\Http\UploadedFile::fake()->create('cni.jpg', 500);
        $this->actingAs($client)->postJson('/api/v1/kyc/upload-cni', [
            'file' => $file,
        ])->assertOk();

        $this->assertTrue(
            \App\Models\Notification::where('user_id', $client->id)
                ->where('type', 'kyc')
                ->where('title', 'Compte en attente de validation')
                ->exists()
        );
    }
}
