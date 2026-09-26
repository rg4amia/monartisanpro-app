<?php

namespace Tests\Feature;

use App\Models\Devis;
use App\Models\FournisseurAgree;
use App\Models\JuryReview;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\OrderService;
use App\Services\OtpService;
use App\Services\ScoreService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\Geo;
use Tests\Support\Scores;
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
            'score_prosartisan' => 300,
            'device_fingerprint' => null,
            'score_frozen' => false,
        ]);

        $otpService = app(OtpService::class);
        $otpService->sendOtp($artisan->phone);
        $otp = DB::table('otps')->where('phone', $artisan->phone)->value('code');

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
        $otp2 = DB::table('otps')->where('phone', $artisan->phone)->orderByDesc('id')->value('code');

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

        $this->assertSame(300, $artisan->fresh()->score_prosartisan);
    }

    public function test_banned_artisan_cannot_evade_ban_by_registering_new_account_on_same_device(): void
    {
        // Artisan précédemment banni, avec son empreinte d'appareil déjà connue.
        User::factory()->create([
            'role' => 'artisan',
            'phone' => '+2250100000001',
            'kyc_status' => 'rejete',
            'account_status' => 'banni',
            'device_fingerprint' => 'device-fraudster-001',
        ]);

        // Nouveau numéro de téléphone, nouvel artisan, mais même appareil physique.
        $newArtisan = User::factory()->create([
            'role' => 'artisan',
            'phone' => '+2250100000002',
            'kyc_status' => 'en_attente',
            'account_status' => 'actif',
            'device_fingerprint' => null,
        ]);

        $otpService = app(OtpService::class);
        $otpService->sendOtp($newArtisan->phone);
        $otp = DB::table('otps')->where('phone', $newArtisan->phone)->value('code');

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $newArtisan->phone,
            'otp' => $otp,
            'device_fingerprint' => 'device-fraudster-001',
        ]);

        $response->assertStatus(422);

        $newArtisan->refresh();
        $this->assertSame('banni', $newArtisan->account_status);
        $this->assertNotNull($newArtisan->blocked_at);
    }

    public function test_jury_prosartisan_assignment_voting_and_consensus(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        /** @var User $artisan */
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        // Create 3 eligible jurors
        /** @var User $jure1 */
        $jure1 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_prosartisan' => 900]);
        $jure1->artisanProfile()->create(['experience_years' => 5]);

        /** @var User $jure2 */
        $jure2 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_prosartisan' => 950]);
        $jure2->artisanProfile()->create(['experience_years' => 8]);

        /** @var User $jure3 */
        $jure3 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_prosartisan' => 850]);
        $jure3->artisanProfile()->create(['experience_years' => 3]);
        Scores::backWithLedger($jure1, $jure2, $jure3);

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
        $this->assertSame(5000, $jure1->fresh()->wallet_mo);

        // Juror 2 votes CONFORME
        $response2 = $this->actingAs($jure2)
            ->postJson("/api/v1/litiges/{$litige->id}/jury/vote", ['verdict' => 'CONFORME']);
        $response2->assertOk();
        $this->assertSame(5000, $jure2->fresh()->wallet_mo);

        // Juror 3 votes NON_CONFORME -> triggers automatic consensus resolution (2 conforme -> artisan wins)
        $response3 = $this->actingAs($jure3)
            ->postJson("/api/v1/litiges/{$litige->id}/jury/vote", ['verdict' => 'NON_CONFORME']);
        $response3->assertOk();
        $this->assertSame(5000, $jure3->fresh()->wallet_mo);

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
        $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'en_attente']);

        assert($client instanceof Authenticatable);
        assert($artisan instanceof Authenticatable);
        assert($supplier instanceof Authenticatable);
        assert($driver instanceof Authenticatable);

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

        $agree = FournisseurAgree::create([
            'position' => Geo::point(),
            'user_id' => $supplier->id,
            'nom_boutique' => 'Test Boutique',
            'statut' => 'agree',
        ]);
        $agree->setPosition(5.36, -4.01);

        $product = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'sku' => 'TESTPRODUCT',
            'name' => 'Test Product',
            'unit_price' => 1000,
            'stock_quantity' => 10,
        ]);

        $order = app(OrderService::class)->createOrder(
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

        // 7bis. Sans KYC actif, aucune route de mouvement de fonds n'est accessible,
        // quel que soit l'état des données métier sous-jacentes (le middleware
        // kyc.verified intercepte avant même la validation de la requête).
        $this->actingAs($client)->postJson('/api/v1/orders', [
            'supplier_id' => $supplier->id,
            'items' => [['supplier_product_id' => $product->id, 'quantity' => 1]],
            'delivery_mode' => 'pickup',
        ])->assertStatus(403);

        $this->actingAs($artisan)->postJson('/api/v1/micro-credit/apply', [
            'amount' => 50000,
        ])->assertStatus(403);

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'lignes_json' => [],
            'jalons_json' => [],
            'statut' => 'soumis',
        ]);

        $this->actingAs($client)->postJson("/api/v1/devis/{$devis->id}/accept")->assertStatus(403);
        $this->actingAs($client)->postJson("/api/v1/devis/{$devis->id}/refuse")->assertStatus(403);
        $this->actingAs($client)->postJson('/api/v1/payments/initiate', [
            'devis_id' => $devis->id,
        ])->assertStatus(403);

        // 8. Test that notifications are created when document uploaded
        $file = UploadedFile::fake()->create('cni.jpg', 500);
        $this->actingAs($client)->postJson('/api/v1/kyc/upload-cni', [
            'file' => $file,
        ])->assertOk();

        $this->assertTrue(
            Notification::where('user_id', $client->id)
                ->where('type', 'kyc')
                ->where('title', 'Compte en attente de validation')
                ->exists()
        );
    }
}
