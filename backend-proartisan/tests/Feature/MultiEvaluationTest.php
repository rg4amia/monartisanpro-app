<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use App\Models\JCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_evaluate_artisan_driver_and_supplier_separately_on_completed_mission(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);

        $driver = User::factory()->create([
            'role' => 'livreur',
            'kyc_status' => 'actif',
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier complété',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);

        // Associer le fournisseur à la mission via un JCode
        JCode::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'fournisseur_id' => $supplier->id,
            'code' => 'PA-TEST',
            'montant' => 10000,
            'statut' => 'utilise',
            'expires_at' => now()->addDays(2),
        ]);

        // 1. Évaluation de l'artisan
        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $artisan->id,
                'note' => 5,
                'commentaire' => 'Artisan exceptionnel.',
                'fiabilite' => 5,
                'integrite' => 5,
                'qualite' => 5,
                'reactivite' => 5,
            ])
            ->assertStatus(201);

        // 2. Évaluation du livreur
        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $driver->id,
                'note' => 4,
                'commentaire' => 'Livraison rapide.',
                'fiabilite' => 4,
                'integrite' => 4,
                'qualite' => 4,
                'reactivite' => 4,
            ])
            ->assertStatus(201);

        // 3. Évaluation du fournisseur
        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $supplier->id,
                'note' => 5,
                'commentaire' => 'Matériaux de qualité.',
                'fiabilite' => 5,
                'integrite' => 5,
                'qualite' => 5,
                'reactivite' => 5,
            ])
            ->assertStatus(201);

        // Vérifier les 3 évaluations dans la base de données
        $this->assertDatabaseCount('evaluations', 3);
    }

    public function test_client_cannot_evaluate_same_actor_twice_on_same_mission(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier complété',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);

        // Première évaluation
        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $artisan->id,
                'note' => 5,
                'commentaire' => 'Premier avis.',
            ])
            ->assertStatus(201);

        // Deuxième évaluation (doit échouer)
        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $artisan->id,
                'note' => 4,
                'commentaire' => 'Deuxième avis.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Vous avez déjà évalué cette personne pour cette mission.');
    }

    public function test_client_cannot_evaluate_unassociated_supplier(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier complété',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);

        // Le fournisseur n'est pas associé (pas de JCode scanné/consommé par lui)
        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $supplier->id,
                'note' => 5,
                'commentaire' => 'Fournisseur non associé.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'L\'utilisateur évalué n\'est pas associé à cette mission.');
    }
}
