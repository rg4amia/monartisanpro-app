<?php

namespace Tests\Feature;

use App\Models\Litige;
use App\Models\LitigeEvidence;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LitigeComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_open_litige_and_freeze_funds(): void
    {
        [$client, $artisan, $mission] = $this->makeMission();

        $this->actingAs($client)
            ->postJson('/api/v1/litiges', [
                'mission_id' => $mission->id,
                'motif' => 'Travail inacheve',
                'description' => 'Le chantier est inacheve et les finitions sont non conformes sur plusieurs points.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'ouvert')
            ->assertJsonPath('data.workflowStep', 'preuves');

        $mission->refresh();

        $this->assertSame('litige', (string) $mission->status);
        $this->assertTrue($mission->funds_frozen);
        $this->assertDatabaseCount('litiges', 1);
    }

    public function test_parties_can_submit_evidence_and_case_moves_to_arbitrage(): void
    {
        Storage::fake('public');

        [$client, $artisan, $mission] = $this->makeMission();

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Travail mal fait',
            'description' => 'Le rendu n est pas conforme au devis.',
            'statut' => 'ouvert',
            'workflow_step' => 'preuves',
            'funds_locked_at' => now(),
            'evidence_deadline_at' => now()->addHours(48),
            'arbitration_deadline_at' => now()->addHours(72),
        ]);

        $clientResponse = $this->actingAs($client)->post(
            "/api/v1/litiges/{$litige->id}/preuves",
            [
                'photos' => [
                    [
                        'photo' => UploadedFile::fake()->image('client-1.jpg', 100, 100),
                        'latitude' => 5.348,
                        'longitude' => -4.027,
                        'description' => 'Fuite visible',
                    ],
                    [
                        'photo' => UploadedFile::fake()->image('client-2.jpg', 200, 200),
                        'latitude' => 5.349,
                        'longitude' => -4.028,
                        'description' => 'Mauvaise finition',
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $clientResponse
            ->assertOk()
            ->assertJsonPath('data.evidenceCounts.client', 2);

        $artisanResponse = $this->actingAs($artisan)->post(
            "/api/v1/litiges/{$litige->id}/preuves",
            [
                'photos' => [
                    [
                        'photo' => UploadedFile::fake()->image('artisan-1.jpg', 300, 300),
                        'latitude' => 5.35,
                        'longitude' => -4.03,
                        'description' => 'Photo de fin de chantier',
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );

        $artisanResponse
            ->assertOk()
            ->assertJsonPath('data.workflowStep', 'arbitrage')
            ->assertJsonPath('data.statut', 'en_cours');

        $this->assertDatabaseCount('litige_preuves', 3);
    }

    public function test_sla_auto_refunds_client_when_artisan_does_not_respond(): void
    {
        Storage::fake('public');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        [$client, $artisan, $mission] = $this->makeMission(walletMateriaux: 65000, walletMo: 35000);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Travail inacheve',
            'description' => 'Le chantier est inacheve et le client a fourni ses preuves.',
            'statut' => 'ouvert',
            'workflow_step' => 'preuves',
            'funds_locked_at' => now()->subHours(49),
            'evidence_deadline_at' => now()->subMinute(),
            'arbitration_deadline_at' => now()->addHours(23),
        ]);

        LitigeEvidence::create([
            'litige_id' => $litige->id,
            'user_id' => $client->id,
            'partie' => 'client',
            'description' => 'Preuve 1',
            'media_url' => '/storage/photos/autres/preuve-1.jpg',
            'media_path' => 'photos/autres/preuve-1.jpg',
            'latitude' => 5.348,
            'longitude' => -4.027,
            'taken_at' => now()->subHours(2),
        ]);

        LitigeEvidence::create([
            'litige_id' => $litige->id,
            'user_id' => $client->id,
            'partie' => 'client',
            'description' => 'Preuve 2',
            'media_url' => '/storage/photos/autres/preuve-2.jpg',
            'media_path' => 'photos/autres/preuve-2.jpg',
            'latitude' => 5.349,
            'longitude' => -4.028,
            'taken_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin)
            ->postJson("/api/v1/litiges/{$litige->id}/evaluate-sla")
            ->assertOk()
            ->assertJsonPath('data.statut', 'resolu')
            ->assertJsonPath('data.decision', 'client')
            ->assertJsonPath('data.resolutionReason', 'absence_preuves_artisan');

        $this->assertSame('annulee', (string) $mission->fresh()->status);
        $this->assertFalse((bool) $mission->fresh()->funds_frozen);
        $this->assertSame(0, $artisan->fresh()->wallet_materiaux);
        $this->assertSame(0, $artisan->fresh()->wallet_mo);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $client->id,
            'type' => 'remboursement',
            'montant' => 100000,
            'statut' => 'confirme',
        ]);
    }

    public function test_admin_can_apply_mixed_decision_refunding_only_labor(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        [$client, $artisan, $mission] = $this->makeMission(walletMateriaux: 65000, walletMo: 35000);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Melange materiaux main oeuvre',
            'description' => 'Les materiaux sont conformes mais la pose est defectueuse.',
            'statut' => 'en_cours',
            'workflow_step' => 'arbitrage',
            'funds_locked_at' => now(),
            'evidence_deadline_at' => now()->subHours(2),
            'arbitration_started_at' => now()->subHour(),
            'arbitration_deadline_at' => now()->addHours(20),
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litige->id}/arbitrage", [
                'decision' => 'mixte',
            ])
            ->assertOk()
            ->assertJsonPath('data.decision', 'mixte')
            ->assertJsonPath('data.resolutionPayload.refund_mo', 35000)
            ->assertJsonPath('data.resolutionPayload.refund_materiaux', 0);

        $mission->refresh();
        $artisan->refresh();

        $this->assertSame('terminee', (string) $mission->status);
        $this->assertFalse((bool) $mission->funds_frozen);
        $this->assertSame(0, $artisan->wallet_materiaux);
        $this->assertSame(0, $artisan->wallet_mo);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $client->id,
            'type' => 'remboursement',
            'montant' => 35000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $artisan->id,
            'type' => 'credit',
            'montant' => 65000,
        ]);
    }

    public function test_artisan_is_banned_after_three_lost_disputes_in_six_months(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_nzassa' => 5,
            'wallet_materiaux' => 65000,
            'wallet_mo' => 35000,
        ]);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        for ($i = 1; $i <= 2; $i++) {
            $pastMission = Mission::create([
                'client_id' => $client->id,
                'artisan_id' => $artisan->id,
                'description' => "Mission passee {$i}",
                'status' => 'annulee',
                'montant_total' => 10000,
                'montant_materiaux' => 5000,
                'montant_mo' => 5000,
                'ratio_materiaux' => 0.5,
            ]);

            Litige::create([
                'mission_id' => $pastMission->id,
                'declencheur_id' => $client->id,
                'type' => 'client',
                'motif' => 'Historique',
                'description' => 'Litige perdu auparavant.',
                'statut' => 'resolu',
                'workflow_step' => 'resolu',
                'decision' => 'client',
                'resolu_at' => now()->subMonths($i),
            ]);
        }

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission courante',
            'status' => 'litige',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
            'funds_frozen' => true,
        ]);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'Troisieme litige',
            'description' => 'Troisieme litige perdu par l artisan.',
            'statut' => 'en_cours',
            'workflow_step' => 'arbitrage',
            'funds_locked_at' => now(),
            'evidence_deadline_at' => now()->subHours(3),
            'arbitration_started_at' => now()->subHours(2),
            'arbitration_deadline_at' => now()->addHours(20),
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/litiges/{$litige->id}/arbitrage", [
                'decision' => 'client',
            ])
            ->assertOk()
            ->assertJsonPath('data.sanctions.0.account_status', 'banni');

        $artisan->refresh();

        $this->assertSame('banni', $artisan->account_status);
        $this->assertSame(0, $artisan->score_nzassa);
        $this->assertNotNull($artisan->blocked_at);
    }

    private function makeMission(int $walletMateriaux = 0, int $walletMo = 0): array
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'wallet_materiaux' => $walletMateriaux,
            'wallet_mo' => $walletMo,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Mission test litige',
            'status' => 'en_cours',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
            'funds_frozen' => false,
        ]);

        return [$client, $artisan, $mission];
    }
}
