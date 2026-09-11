<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le mobile affiche des onglets de statut en français ("À deviser",
 * "Financées", "En cours", "Terminées", "Litiges") qui se traduisent en
 * paramètre `?status=` sur GET /api/v1/missions. MissionController::index()
 * traduit ensuite ce mot-clé français en un ou plusieurs états techniques
 * du FSM (ex: 'en_cours' -> ['in_progress', 'pending_approval'] côté
 * artisan), avec un mapping différent selon le rôle. Cette logique de
 * bascule d'onglet par statut n'était couverte par aucun test.
 */
class MissionStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeMission(User $client, User $artisan, string $status): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => "Mission au statut {$status}",
            'status' => $status,
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);
    }

    public function test_artisan_status_filters_map_to_correct_technical_states(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $draft = $this->makeMission($client, $artisan, 'draft');
        $pendingFunding = $this->makeMission($client, $artisan, 'pending_funding');
        $pendingAcceptance = $this->makeMission($client, $artisan, 'pending_artisan_acceptance');
        $fundedLocked = $this->makeMission($client, $artisan, 'funded_locked');
        $inProgress = $this->makeMission($client, $artisan, 'in_progress');
        $pendingApproval = $this->makeMission($client, $artisan, 'pending_approval');
        $completed = $this->makeMission($client, $artisan, 'completed');
        $disputed = $this->makeMission($client, $artisan, 'disputed');
        $cancelled = $this->makeMission($client, $artisan, 'cancelled');

        // 'en_attente' -> À deviser : draft + pending_funding + pending_artisan_acceptance
        $this->assertMissionIdsForFilter(
            $artisan,
            'en_attente',
            [$draft->id, $pendingFunding->id, $pendingAcceptance->id]
        );

        // 'financee' -> Financées : funded_locked uniquement
        $this->assertMissionIdsForFilter($artisan, 'financee', [$fundedLocked->id]);

        // 'en_cours' -> En cours : in_progress + pending_approval
        $this->assertMissionIdsForFilter(
            $artisan,
            'en_cours',
            [$inProgress->id, $pendingApproval->id]
        );

        // 'terminee' -> Terminées : completed uniquement
        $this->assertMissionIdsForFilter($artisan, 'terminee', [$completed->id]);

        // 'litige' -> Litiges : disputed uniquement
        $this->assertMissionIdsForFilter($artisan, 'litige', [$disputed->id]);

        // 'annulee' : cancelled uniquement
        $this->assertMissionIdsForFilter($artisan, 'annulee', [$cancelled->id]);

        // Sans filtre : les 9 missions de l'artisan, aucune de plus.
        $this->assertMissionIdsForFilter($artisan, null, [
            $draft->id, $pendingFunding->id, $pendingAcceptance->id,
            $fundedLocked->id, $inProgress->id, $pendingApproval->id,
            $completed->id, $disputed->id, $cancelled->id,
        ]);
    }

    public function test_client_status_filters_group_pre_completion_states_under_en_cours(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $draft = $this->makeMission($client, $artisan, 'draft');
        $pendingFunding = $this->makeMission($client, $artisan, 'pending_funding');
        $fundedLocked = $this->makeMission($client, $artisan, 'funded_locked');
        $inProgress = $this->makeMission($client, $artisan, 'in_progress');
        $completed = $this->makeMission($client, $artisan, 'completed');
        $disputed = $this->makeMission($client, $artisan, 'disputed');
        $cancelled = $this->makeMission($client, $artisan, 'cancelled');

        // Côté client, 'en_cours' regroupe TOUT ce qui précède la clôture
        // (contrairement à l'artisan qui distingue en_attente/financee/en_cours).
        $this->assertMissionIdsForFilter(
            $client,
            'en_cours',
            [$draft->id, $pendingFunding->id, $fundedLocked->id, $inProgress->id]
        );

        $this->assertMissionIdsForFilter($client, 'terminee', [$completed->id]);
        $this->assertMissionIdsForFilter($client, 'litige', [$disputed->id]);
        $this->assertMissionIdsForFilter($client, 'annulee', [$cancelled->id]);
    }

    public function test_status_filter_never_leaks_missions_from_other_users(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $otherArtisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $otherClient = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $mine = $this->makeMission($client, $artisan, 'in_progress');
        $this->makeMission($otherClient, $otherArtisan, 'in_progress');

        $this->assertMissionIdsForFilter($artisan, 'en_cours', [$mine->id]);
    }

    /**
     * @param  list<int>  $expectedIds
     */
    private function assertMissionIdsForFilter(User $actingAs, ?string $status, array $expectedIds): void
    {
        $query = $status !== null ? "?status={$status}" : '';

        $response = $this->actingAs($actingAs)->getJson("/api/v1/missions{$query}");

        $response->assertOk();

        $actualIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $expected = collect($expectedIds)->sort()->values()->all();

        $this->assertSame(
            $expected,
            $actualIds,
            "Filtre '{$status}' pour le rôle {$actingAs->role} : missions retournées inattendues."
        );
    }
}
