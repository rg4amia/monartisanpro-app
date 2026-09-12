<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le module d'évaluation expose quatre endpoints, mais seul `store` était
 * couvert. Ces tests couvrent les trois autres, qui pilotent l'écran de
 * notation côté mobile : la liste des acteurs à évaluer pour une mission et
 * pour une commande, et l'historique des avis donnés/reçus.
 */
class EvaluationStatusEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function completedMission(User $client, User $artisan): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier terminé',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);
    }

    public function test_mission_actors_lists_artisan_and_suppliers_linked_by_jcode(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $mission = $this->completedMission($client, $artisan);

        JCode::create([
            'mission_id' => $mission->id,
            'artisan_id' => $artisan->id,
            'fournisseur_id' => $supplier->id,
            'code' => 'PA-EV01',
            'montant' => 10000,
            'statut' => 'utilise',
            'expires_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($client)
            ->getJson("/api/v1/missions/{$mission->id}/evaluations-status");

        $response->assertOk();
        $response->assertJsonPath('data.is_completed', true);

        $roles = collect($response->json('data.actors'))->pluck('role')->all();
        $this->assertContains('artisan', $roles);
        $this->assertContains('fournisseur', $roles);

        // Rien n'a encore été noté.
        foreach ($response->json('data.actors') as $actor) {
            $this->assertFalse($actor['is_evaluated']);
            $this->assertNull($actor['evaluation']);
        }
    }

    public function test_mission_actors_reflects_an_evaluation_already_submitted(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $mission = $this->completedMission($client, $artisan);

        Evaluation::create([
            'mission_id' => $mission->id,
            'evaluateur_id' => $client->id,
            'evalue_id' => $artisan->id,
            'note' => 5,
            'commentaire' => 'Travail impeccable',
        ]);

        $response = $this->actingAs($client)
            ->getJson("/api/v1/missions/{$mission->id}/evaluations-status");

        $response->assertOk();

        $artisanActor = collect($response->json('data.actors'))
            ->firstWhere('role', 'artisan');

        $this->assertTrue($artisanActor['is_evaluated']);
        $this->assertSame(5, $artisanActor['evaluation']['note']);
        $this->assertSame('Travail impeccable', $artisanActor['evaluation']['commentaire']);
    }

    public function test_mission_actors_hides_evaluations_left_by_someone_else(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $mission = $this->completedMission($client, $artisan);

        // L'artisan note le client : cela ne doit pas faire croire au client
        // qu'il a déjà noté l'artisan.
        Evaluation::create([
            'mission_id' => $mission->id,
            'evaluateur_id' => $artisan->id,
            'evalue_id' => $client->id,
            'note' => 4,
        ]);

        $response = $this->actingAs($client)
            ->getJson("/api/v1/missions/{$mission->id}/evaluations-status");

        $artisanActor = collect($response->json('data.actors'))
            ->firstWhere('role', 'artisan');

        $this->assertFalse($artisanActor['is_evaluated']);
    }

    public function test_mission_actors_is_forbidden_to_an_unrelated_user(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $intruder = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $mission = $this->completedMission($client, $artisan);

        $this->actingAs($intruder)
            ->getJson("/api/v1/missions/{$mission->id}/evaluations-status")
            ->assertForbidden();
    }

    public function test_mission_actors_is_not_polluted_by_unrelated_delivered_orders(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $mission = $this->completedMission($client, $artisan);

        // Commande livrée sans aucun rapport avec la mission (autre client,
        // autre fournisseur). L'ancien code la ramenait via un whereHas dont
        // la fermeture était vide.
        $otherClient = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $otherSupplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $otherDriver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        Order::create([
            'client_id' => $otherClient->id,
            'supplier_id' => $otherSupplier->id,
            'driver_id' => $otherDriver->id,
            'status' => 'delivered',
            'delivery_mode' => 'delivery',
            'subtotal' => 50000,
            'delivery_cost' => 2000,
            'platform_fee' => 1000,
            'total_amount' => 53000,
            'pickup_code' => 'RET-OTHER',
            'reception_code' => 'REC-OTHER',
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);

        $response = $this->actingAs($client)
            ->getJson("/api/v1/missions/{$mission->id}/evaluations-status");

        $response->assertOk();

        $ids = collect($response->json('data.actors'))->pluck('id')->all();
        $this->assertNotContains($otherDriver->id, $ids);
        $this->assertNotContains($otherSupplier->id, $ids);
    }

    public function test_order_actors_lists_supplier_and_driver(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'driver_id' => $driver->id,
            'status' => 'delivered',
            'delivery_mode' => 'delivery',
            'subtotal' => 50000,
            'delivery_cost' => 2000,
            'platform_fee' => 1000,
            'total_amount' => 53000,
            'pickup_code' => 'RET-EVAL1',
            'reception_code' => 'REC-EVAL1',
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);

        $response = $this->actingAs($client)
            ->getJson("/api/v1/orders/{$order->id}/evaluations-status");

        $response->assertOk();
        $response->assertJsonPath('data.is_delivered', true);

        $roles = collect($response->json('data.actors'))->pluck('role')->all();
        $this->assertContains('fournisseur', $roles);
        $this->assertContains('livreur', $roles);
    }

    public function test_order_actors_is_forbidden_to_an_unrelated_user(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $intruder = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'status' => 'delivered',
            'delivery_mode' => 'delivery',
            'subtotal' => 50000,
            'delivery_cost' => 2000,
            'platform_fee' => 1000,
            'total_amount' => 53000,
            'pickup_code' => 'RET-EVAL1',
            'reception_code' => 'REC-EVAL1',
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
        ]);

        $this->actingAs($intruder)
            ->getJson("/api/v1/orders/{$order->id}/evaluations-status")
            ->assertForbidden();
    }

    public function test_my_evaluations_separates_given_from_received(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $mission = $this->completedMission($client, $artisan);

        Evaluation::create([
            'mission_id' => $mission->id,
            'evaluateur_id' => $client->id,
            'evalue_id' => $artisan->id,
            'note' => 5,
            'commentaire' => 'Donnée par le client',
        ]);

        Evaluation::create([
            'mission_id' => $mission->id,
            'evaluateur_id' => $artisan->id,
            'evalue_id' => $client->id,
            'note' => 4,
            'commentaire' => 'Reçue par le client',
        ]);

        $response = $this->actingAs($client)->getJson('/api/v1/evaluations/my');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.given'));
        $this->assertCount(1, $response->json('data.received'));
        $this->assertSame('Donnée par le client', $response->json('data.given.0.commentaire'));
        $this->assertSame('Reçue par le client', $response->json('data.received.0.commentaire'));
    }

    public function test_my_evaluations_never_leaks_other_users_evaluations(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $stranger = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $mission = $this->completedMission($client, $artisan);

        Evaluation::create([
            'mission_id' => $mission->id,
            'evaluateur_id' => $stranger->id,
            'evalue_id' => $artisan->id,
            'note' => 2,
        ]);

        $response = $this->actingAs($client)->getJson('/api/v1/evaluations/my');

        $response->assertOk();
        $this->assertSame([], $response->json('data.given'));
        $this->assertSame([], $response->json('data.received'));
    }
}
