<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionRejectionAndReassignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_rejection_removes_mission_from_artisan_and_flags_it_for_client(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $newArtisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier de carrelage terrasse',
            'status' => 'pending_artisan_acceptance',
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0,
        ]);

        // 1. L'artisan voit initialement la mission dans ses demandes en attente
        $artisanMissions = $this->actingAs($artisan)
            ->getJson('/api/v1/missions?status=en_attente')
            ->assertOk()
            ->json('data');

        $this->assertTrue(collect($artisanMissions)->contains('id', $mission->id));

        // 2. L'artisan refuse la demande de devis
        $rejectResponse = $this->actingAs($artisan)
            ->postJson("/api/v1/missions/{$mission->id}/reject-request")
            ->assertOk();

        $this->assertNull($rejectResponse->json('data.artisanId'));
        $this->assertTrue($rejectResponse->json('data.artisanRejected'));
        $this->assertNotNull($rejectResponse->json('data.artisanRejectedAt'));

        $mission->refresh();
        $this->assertNull($mission->artisan_id);
        $this->assertNotNull($mission->artisan_rejected_at);
        $this->assertSame('draft', (string) $mission->status);

        // 3. La mission a disparu des missions de l'artisan (ni en_attente, ni en_cours, ni sans filtre)
        $artisanListAfter = $this->actingAs($artisan)
            ->getJson('/api/v1/missions')
            ->assertOk()
            ->json('data');
        $this->assertFalse(collect($artisanListAfter)->contains('id', $mission->id));

        $artisanEnAttenteAfter = $this->actingAs($artisan)
            ->getJson('/api/v1/missions?status=en_attente')
            ->assertOk()
            ->json('data');
        $this->assertFalse(collect($artisanEnAttenteAfter)->contains('id', $mission->id));

        // 4. Le client voit la mission avec les drapeaux de refus
        $clientMissions = $this->actingAs($client)
            ->getJson('/api/v1/missions')
            ->assertOk()
            ->json('data');

        $clientMissionData = collect($clientMissions)->firstWhere('id', $mission->id);
        $this->assertNotNull($clientMissionData);
        $this->assertTrue($clientMissionData['artisanRejected']);
        $this->assertFalse($clientMissionData['hasArtisan']);

        // 5. Le client filtre spécifiquement sur 'refusee'
        $refusedMissions = $this->actingAs($client)
            ->getJson('/api/v1/missions?status=refusee')
            ->assertOk()
            ->json('data');

        $this->assertTrue(collect($refusedMissions)->contains('id', $mission->id));

        // 6. Le client réassigne un nouvel artisan
        $assignResponse = $this->actingAs($client)
            ->postJson("/api/v1/missions/{$mission->id}/assign-artisan", [
                'artisan_id' => $newArtisan->id,
            ])
            ->assertOk();

        $this->assertSame($newArtisan->id, $assignResponse->json('data.artisanId'));
        $this->assertFalse($assignResponse->json('data.artisanRejected'));
        $this->assertTrue($assignResponse->json('data.hasArtisan'));
        $this->assertSame('pending_artisan_acceptance', $assignResponse->json('data.status'));

        $mission->refresh();
        $this->assertSame($newArtisan->id, $mission->artisan_id);
        $this->assertNull($mission->artisan_rejected_at);
        $this->assertSame('pending_artisan_acceptance', (string) $mission->status);

        // 7. Le nouvel artisan voit maintenant la mission dans ses demandes en attente
        $newArtisanMissions = $this->actingAs($newArtisan)
            ->getJson('/api/v1/missions?status=en_attente')
            ->assertOk()
            ->json('data');
        $this->assertTrue(collect($newArtisanMissions)->contains('id', $mission->id));
    }

    public function test_cannot_reassign_artisan_if_not_mission_owner(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $otherClient = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'description' => 'Test',
            'status' => 'draft',
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0,
        ]);

        $this->actingAs($otherClient)
            ->postJson("/api/v1/missions/{$mission->id}/assign-artisan", [
                'artisan_id' => $artisan->id,
            ])
            ->assertForbidden();
    }
}
