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
            'status' => 'en_cours',
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
            'status' => 'en_cours',
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
}
