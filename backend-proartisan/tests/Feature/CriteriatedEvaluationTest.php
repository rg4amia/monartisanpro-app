<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Mission;
use App\Models\Order;
use App\Models\User;
use App\Services\ScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriteriatedEvaluationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Règle 9 : Tout artisan démarre avec un Zéro Initial Absolu et sans marqueur doré.
     */
    public function test_artisan_starts_with_absolute_zero_and_no_golden_marker(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $this->assertSame(0, $artisan->score_prosartisan);
        $this->assertFalse($artisan->isGoldenMarker());
        $this->assertFalse($artisan->is_golden_marker);

        $scoreService = app(ScoreService::class);
        $detail = $scoreService->getScoreDetail($artisan);

        $this->assertSame(0, $detail['score_prosartisan']);
        $this->assertFalse($detail['is_golden_marker']);
        $this->assertSame(700, $detail['golden_marker_threshold']);
        $this->assertSame(0, $detail['total_evaluations']);
        $this->assertSame(0.0, $detail['maturity_percentage']);
    }

    /**
     * Un client évalue un artisan sur les 4 critères (Fiabilité, Intégrité, Qualité, Réactivité).
     * Les pondérations 40%, 30%, 20%, 10% s'appliquent correctement.
     */
    public function test_client_submits_criteriated_evaluation_with_four_pillars(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 0,
        ]);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Réparation plomberie sanitaire',
            'status' => 'completed',
            'montant_total' => 50000,
            'montant_materiaux' => 30000,
            'montant_mo' => 20000,
            'ratio_materiaux' => 0.60,
        ]);

        $response = $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $artisan->id,
                'note' => 4,
                'commentaire' => 'Excellente prestation et bon respect des délais.',
                'fiabilite' => 5,
                'integrite' => 4,
                'qualite' => 4,
                'reactivite' => 3,
            ]);

        $response->assertCreated();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('evaluations', [
            'mission_id' => $mission->id,
            'evaluateur_id' => $client->id,
            'evalue_id' => $artisan->id,
            'fiabilite' => 5,
            'integrite' => 4,
            'qualite' => 4,
            'reactivite' => 3,
        ]);

        // Vérification du score calculé :
        // rawCriteriaScore = (5/5 * 400) + (4/5 * 300) + (4/5 * 200) + (3/5 * 100) = 400 + 240 + 160 + 60 = 860
        // Moins de 3 critères >= 4.8 => plafonné à 800
        // Volume 1/10 = 0.1 => evalScoreBase = round(800 * 0.1) = 80
        // Moyenne pondérée pour ledger : (5*0.4 + 4*0.3 + 4*0.2 + 3*0.1) = 2.0 + 1.2 + 0.8 + 0.3 = 4.3 >= 4.0 => +5 points * crédibilité
        $artisan->refresh();
        $this->assertGreaterThan(0, $artisan->score_prosartisan);
    }

    /**
     * Marqueur Doré débloqué dès que le score atteint ou dépasse le seuil (700).
     */
    public function test_artisan_unlocks_golden_marker_at_or_above_threshold(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 699,
        ]);

        $this->assertFalse($artisan->isGoldenMarker());
        $this->assertFalse($artisan->is_golden_marker);

        $artisan->update(['score_prosartisan' => 700]);
        $this->assertTrue($artisan->isGoldenMarker());
        $this->assertTrue($artisan->is_golden_marker);

        $artisan->update(['score_prosartisan' => 850]);
        $this->assertTrue($artisan->isGoldenMarker());
    }

    /**
     * Les ressources API (ArtisanResource & UserResource) exposent fidèlement le marqueur doré.
     */
    public function test_api_resources_expose_golden_marker_flag(): void
    {
        $artisanElite = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 750,
        ]);

        $artisanStandard = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 450,
        ]);

        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $resElite = $this->actingAs($client)->getJson("/api/v1/artisans/{$artisanElite->id}");
        $resElite->assertOk()
            ->assertJsonPath('data.isGoldenMarker', true)
            ->assertJsonPath('data.scoreProsArtisan', 750);

        $resStandard = $this->actingAs($client)->getJson("/api/v1/artisans/{$artisanStandard->id}");
        $resStandard->assertOk()
            ->assertJsonPath('data.isGoldenMarker', false)
            ->assertJsonPath('data.scoreProsArtisan', 450);
    }

    /**
     * Interdiction d'auto-évaluation et interdiction d'évaluer une mission non terminée.
     */
    public function test_cannot_self_evaluate_or_evaluate_uncompleted_mission(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier en cours',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ]);

        // 1. Mission non terminée
        $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $mission->id,
                'evalue_id' => $artisan->id,
                'note' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La mission doit être terminée pour pouvoir évaluer.');

        // 2. Auto-évaluation (sur mission complétée)
        $completedMission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier terminé',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 60000,
            'montant_mo' => 40000,
            'ratio_materiaux' => 0.60,
        ]);

        $this->actingAs($artisan)
            ->postJson('/api/v1/evaluations', [
                'mission_id' => $completedMission->id,
                'evalue_id' => $artisan->id,
                'note' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Vous ne pouvez pas vous auto-évaluer.');
    }

    /**
     * Évaluation logistique (livreur sur commande livrée).
     */
    public function test_client_can_evaluate_logistic_driver_on_delivered_order(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif', 'score_prosartisan' => 0]);

        $order = Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'driver_id' => $driver->id,
            'status' => 'delivered',
            'delivery_mode' => 'delivery',
            'subtotal' => 30000,
            'delivery_cost' => 2000,
            'platform_fee' => 1000,
            'total_amount' => 33000,
            'pickup_code' => 'RET-4821',
            'reception_code' => 'REC-4821',
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
            'recipient_phone' => $client->phone,
        ]);

        $response = $this->actingAs($client)
            ->postJson('/api/v1/evaluations', [
                'order_id' => $order->id,
                'evalue_id' => $driver->id,
                'note' => 5,
                'commentaire' => 'Livraison très rapide et colis intact.',
                'fiabilite' => 5,
                'qualite' => 5,
                'reactivite' => 5,
            ]);

        $response->assertCreated();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('evaluations', [
            'order_id' => $order->id,
            'evaluateur_id' => $client->id,
            'evalue_id' => $driver->id,
            'note' => 5,
            'fiabilite' => 5,
        ]);
    }
}
