<?php

namespace Tests\Feature;

use App\Models\Jalon;
use App\Models\Mission;
use App\Models\MissionStateTransition;
use App\Models\User;
use App\States\Mission\CancelledState;
use App\States\Mission\CompletedState;
use App\States\Mission\DisputedState;
use App\States\Mission\DraftState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use App\States\Mission\PendingApprovalState;
use App\States\Mission\PendingFundingState;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Chantier13MissionFsmGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $artisan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
            'phone' => '+2250701000001',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 850,
            'phone' => '+2250701000002',
        ]);
    }

    public function test_to_completed_guard_rejects_if_unpaid_jalons_exist(): void
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Rénovation Plomberie',
            'status' => InProgressState::class,
            'montant_total' => 200000,
            'montant_mo' => 120000,
            'montant_materiaux' => 80000,
            'ratio_materiaux' => 0.40,
        ]);

        // Jalon 1 validé
        Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Démolition et tuyauterie',
            'montant' => 60000,
            'statut' => 'paye',
        ]);

        // Jalon 2 encore en attente !
        Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 2,
            'description' => 'Pose sanitaires',
            'montant' => 60000,
            'statut' => 'en_attente',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('reste(nt) non validé(s) ou impayé(s)');

        $mission->status->transitionTo(CompletedState::class);
    }

    public function test_to_completed_guard_enforces_referent_validation_above_two_million_fcfa(): void
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Gros Œuvre et Charpente Villa',
            'status' => InProgressState::class,
            'montant_total' => 3500000, // > 2 000 000 FCFA
            'montant_mo' => 2000000,
            'montant_materiaux' => 150000,
            'ratio_materiaux' => 0.4285,
            'referent_validated_at' => null, // Pas encore validé par le référent
        ]);

        // Tous les jalons sont payés
        Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Fondations et élévation',
            'montant' => 2000000,
            'statut' => 'paye',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('validation physique préalable du Référent de zone (Règle d\'or n° 5)');

        $mission->status->transitionTo(CompletedState::class);
    }

    public function test_to_completed_succeeds_when_all_guards_are_satisfied(): void
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Chantier Villa Plateau',
            'status' => InProgressState::class,
            'montant_total' => 2500000,
            'montant_mo' => 1500000,
            'montant_materiaux' => 1000000,
            'ratio_materiaux' => 0.40,
            'referent_validated_at' => now(), // Visite Référent effectuée !
        ]);

        Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Clôture jalon unique',
            'montant' => 1500000,
            'statut' => 'paye',
        ]);

        $mission->status->transitionTo(CompletedState::class);

        $this->assertInstanceOf(CompletedState::class, $mission->fresh()->status);
    }

    public function test_to_disputed_freezes_funds_and_rejects_if_already_completed(): void
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Peinture intérieure',
            'status' => InProgressState::class,
            'montant_total' => 100000,
            'montant_mo' => 60000,
            'montant_materiaux' => 40000,
            'ratio_materiaux' => 0.40,
            'funds_frozen' => false,
        ]);

        // Transition vers litige
        $mission->status->transitionTo(DisputedState::class);

        $fresh = $mission->fresh();
        $this->assertInstanceOf(DisputedState::class, $fresh->status);
        $this->assertTrue($fresh->funds_frozen);
        $this->assertTrue($fresh->isFundsFrozen());

        // Impossible de contester une mission déjà complétée
        $completedMission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Mission terminée',
            'status' => CompletedState::class,
            'montant_total' => 50000,
            'montant_mo' => 30000,
            'montant_materiaux' => 20000,
            'ratio_materiaux' => 0.40,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Impossible de placer en litige une mission déjà clôturée.');

        $completedMission->status->transitionTo(DisputedState::class);
    }

    public function test_to_in_progress_guard_rejects_if_funds_are_frozen(): void
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Menuiserie Alu',
            'status' => FundedLockedState::class,
            'montant_total' => 300000,
            'montant_mo' => 180000,
            'montant_materiaux' => 120000,
            'ratio_materiaux' => 0.40,
            'funds_frozen' => true, // Fonds gelés !
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('les fonds sont actuellement gelés');

        $mission->status->transitionTo(InProgressState::class);
    }

    public function test_state_transitions_are_automatically_recorded_in_audit_trail(): void
    {
        $this->actingAs($this->client);

        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Electricité générale',
            'status' => DraftState::class,
            'montant_total' => 100000,
            'montant_mo' => 70000,
            'montant_materiaux' => 30000,
            'ratio_materiaux' => 0.30,
        ]);

        // Transition 1 : Draft -> PendingFunding
        $mission->status->transitionTo(PendingFundingState::class);

        // Transition 2 : PendingFunding -> FundedLocked
        $mission->status->transitionTo(FundedLockedState::class);

        $transitions = MissionStateTransition::where('mission_id', $mission->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $transitions);

        $this->assertEquals('draft', $transitions[0]->from_state);
        $this->assertEquals('pending_funding', $transitions[0]->to_state);
        $this->assertEquals($this->client->id, $transitions[0]->user_id);

        $this->assertEquals('pending_funding', $transitions[1]->from_state);
        $this->assertEquals('funded_locked', $transitions[1]->to_state);
    }

    public function test_mission_state_history_api_endpoint(): void
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Installation Climatisation',
            'status' => DraftState::class,
            'montant_total' => 150000,
            'montant_mo' => 90000,
            'montant_materiaux' => 60000,
            'ratio_materiaux' => 0.40,
        ]);

        // Exécuter une transition
        $this->actingAs($this->client);
        $mission->status->transitionTo(PendingFundingState::class);

        // Interroger l'endpoint d'historique
        $response = $this->actingAs($this->client)->getJson("/api/v1/missions/{$mission->id}/state-history");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.mission_id', $mission->id);
        $response->assertJsonPath('data.current_state', 'pending_funding');
        $this->assertCount(1, $response->json('data.transitions'));

        // Un utilisateur non relié est rejeté
        $stranger = User::factory()->create(['role' => 'client']);
        $forbiddenResponse = $this->actingAs($stranger)->getJson("/api/v1/missions/{$mission->id}/state-history");
        $forbiddenResponse->assertStatus(403);
    }
}
