<?php

namespace Tests\Feature;

use App\Models\JCode;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\MissionStateTransition;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 20 — points d'API des historiques de l'application mobile :
 * paiements, commandes et courses, litiges, inspections du Référent,
 * historique des états d'une mission.
 */
class Chantier20HistoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    private User $referent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif', 'name' => 'Awa Traoré']);
        $this->artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'name' => 'Koffi Yao']);
        $this->referent = User::factory()->create(['role' => 'referent', 'kyc_status' => 'actif', 'name' => 'Référent Sud']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mission(array $attributes = []): Mission
    {
        return Mission::create(array_merge([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Extension de la terrasse',
            'client_address' => 'Cocody, rue des Jardins',
            'status' => 'in_progress',
            'montant_total' => 3000000,
            'montant_materiaux' => 0,
            'montant_mo' => 3000000,
            'ratio_materiaux' => 0,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function litige(Mission $mission, array $attributes = []): Litige
    {
        return Litige::create(array_merge([
            'mission_id' => $mission->id, 'declencheur_id' => $this->client->id, 'type' => 'client',
            'motif' => 'Malfaçon', 'description' => 'Dalle fissurée.', 'statut' => 'ouvert',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transaction(array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id' => $this->client->id, 'type' => 'acompte', 'montant' => 50000,
            'wallet_source' => 'client_mobile_money', 'wallet_dest' => 'escrow', 'provider' => 'wave',
            'statut' => 'confirme', 'reference_externe' => 'TXN-C20-'.uniqid(),
        ], $attributes));
    }

    // ── Paiements ────────────────────────────────────────────────────────

    public function test_l_historique_des_paiements_se_filtre_et_se_pagine(): void
    {
        $this->transaction();
        $this->transaction(['statut' => 'echoue']);
        $this->transaction(['type' => 'paiement_livraison']);
        $this->transaction(['user_id' => $this->artisan->id]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/transactions?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/transactions?status=echoue')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/transactions?type=paiement_livraison')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_un_filtre_de_paiement_inconnu_est_refuse(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/transactions?status=termine')
            ->assertStatus(422);
    }

    // ── Commandes et courses ─────────────────────────────────────────────

    public function test_le_livreur_retrouve_ses_courses_passees_sans_aucun_code(): void
    {
        $livreur = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
        $autre = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
        $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);

        $base = [
            'client_id' => $this->client->id, 'supplier_id' => $supplier->id, 'delivery_mode' => 'delivery',
            'subtotal' => 20000, 'platform_fee' => 0, 'delivery_cost' => 1500, 'total_amount' => 21500,
            'pickup_code' => '4821', 'reception_code' => '7734',
        ];
        Order::create($base + ['driver_id' => $livreur->id, 'status' => 'delivered']);
        Order::create($base + ['driver_id' => $livreur->id, 'status' => 'shipping']);
        Order::create($base + ['driver_id' => $autre->id, 'status' => 'delivered']);

        $response = $this->actingAs($livreur, 'sanctum')
            ->getJson('/api/v1/orders?status=delivered,cancelled&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'delivered');

        $this->assertArrayNotHasKey('pickup_code', $response->json('data.0'));
        $this->assertArrayNotHasKey('reception_code', $response->json('data.0'));

        // Sans pagination demandée, la réponse reste la liste complète.
        $this->actingAs($livreur, 'sanctum')
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('meta');
    }

    public function test_un_statut_de_commande_inconnu_est_refuse(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/orders?status=livree')
            ->assertStatus(422);
    }

    // ── Litiges ──────────────────────────────────────────────────────────

    public function test_le_client_et_l_artisan_retrouvent_leurs_litiges_par_etat(): void
    {
        $this->litige($this->mission(), ['statut' => 'resolu', 'decision' => 'client', 'resolu_at' => now()]);
        $this->litige($this->mission());

        $tiers = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->litige($this->mission(['client_id' => $tiers->id, 'artisan_id' => User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif'])->id]));

        $this->actingAs($this->client, 'sanctum')->getJson('/api/v1/litiges')->assertOk()->assertJsonPath('meta.total', 2);
        $this->actingAs($this->artisan, 'sanctum')->getJson('/api/v1/litiges?statut=resolu')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_un_jure_ne_voit_pas_les_parties_dans_la_liste_des_litiges(): void
    {
        $litige = $this->litige($this->mission());
        $jure = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $litige->juryReviews()->create(['jure_id' => $jure->id]);

        $this->actingAs($jure, 'sanctum')
            ->getJson('/api/v1/litiges')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_le_fournisseur_retrouve_les_litiges_resolus_des_chantiers_qu_il_a_fournis(): void
    {
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $mission = $this->mission(['status' => 'completed']);
        JCode::create([
            'mission_id' => $mission->id, 'artisan_id' => $this->artisan->id, 'fournisseur_id' => $fournisseur->id,
            'code' => 'PA-C20A', 'montant' => 50000, 'statut' => 'utilise', 'expires_at' => now()->addDay(),
        ]);
        $this->litige($mission, ['statut' => 'resolu', 'decision' => 'artisan', 'resolu_at' => now()]);

        $this->actingAs($fournisseur, 'sanctum')
            ->getJson('/api/v1/supplier/litiges')
            ->assertOk()
            ->assertJsonCount(1, 'data.mission_litiges')
            ->assertJsonPath('data.mission_litiges.0.litiges.0.statut', 'resolu');
    }

    // ── Référent ─────────────────────────────────────────────────────────

    public function test_le_referent_retrouve_les_inspections_qu_il_a_realisees(): void
    {
        $visitee = $this->mission(['referent_validated_at' => now()->subDay(), 'referent_validated_by' => $this->referent->id]);
        $this->mission(['referent_validated_at' => now(), 'referent_validated_by' => User::factory()->create(['role' => 'referent'])->id]);
        $this->mission();

        $response = $this->actingAs($this->referent, 'sanctum')
            ->getJson('/api/v1/referent/inspections')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $visitee->id)
            ->assertJsonPath('data.0.status_label', 'En cours')
            ->assertJsonPath('data.0.address', 'Cocody, rue des Jardins');

        $this->assertNotNull($response->json('data.0.inspected_at'));
    }

    public function test_le_referent_voit_les_litiges_a_visiter_et_visites_sans_les_coordonnees_des_parties(): void
    {
        $aVisiter = $this->litige($this->mission(['status' => 'disputed', 'referent_required' => true]));
        $visite = $this->litige(
            $this->mission(['status' => 'completed', 'referent_validated_at' => now(), 'referent_validated_by' => $this->referent->id]),
            ['statut' => 'resolu', 'decision' => 'artisan', 'resolu_at' => now()],
        );
        $this->litige($this->mission(['montant_total' => 100000]));

        $response = $this->actingAs($this->referent, 'sanctum')
            ->getJson('/api/v1/referent/litiges')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$aVisiter->id, $visite->id], $ids);

        $resolu = collect($response->json('data'))->firstWhere('id', $visite->id);
        $this->assertSame("En faveur de l'artisan", $resolu['decision_label']);
        $this->assertTrue($resolu['visited_by_me']);
        $this->assertFalse($resolu['visit_required']);

        $body = $response->getContent();
        $this->assertStringNotContainsString($this->client->phone, $body);
        $this->assertStringNotContainsString($this->artisan->phone, $body);
        $this->assertStringNotContainsString('Awa Traor', $body);
        $this->assertStringNotContainsString('Koffi Yao', $body);

        $this->actingAs($this->referent, 'sanctum')
            ->getJson('/api/v1/referent/litiges?statut=resolu')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_les_historiques_du_referent_sont_refuses_aux_autres_roles(): void
    {
        $this->actingAs($this->client, 'sanctum')->getJson('/api/v1/referent/inspections')->assertForbidden();
        $this->actingAs($this->artisan, 'sanctum')->getJson('/api/v1/referent/litiges')->assertForbidden();
        $this->actingAs($this->referent, 'sanctum')->getJson('/api/v1/referent/litiges?statut=clos')->assertStatus(422);
    }

    // ── Historique des états d'une mission ───────────────────────────────

    public function test_l_historique_d_une_mission_montre_le_role_jamais_le_nom_d_un_administrateur(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Mariam Koné']);
        $mission = $this->mission(['status' => 'completed']);
        MissionStateTransition::create([
            'mission_id' => $mission->id, 'from_state' => 'disputed', 'to_state' => 'completed',
            'user_id' => $admin->id, 'reason' => 'Litige arbitré', 'created_at' => now(),
        ]);
        MissionStateTransition::create([
            'mission_id' => $mission->id, 'from_state' => 'in_progress', 'to_state' => 'disputed',
            'user_id' => $this->client->id, 'reason' => 'Litige ouvert', 'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->artisan, 'sanctum')
            ->getJson("/api/v1/missions/{$mission->id}/state-history")
            ->assertOk();

        $lines = collect($response->json('data.transitions'));
        $closing = $lines->firstWhere('to_state', 'completed');
        $this->assertNull($closing['user']['name']);
        $this->assertSame('Administrateur', $closing['role_label']);
        $this->assertSame('Awa Traoré', $lines->firstWhere('to_state', 'disputed')['user']['name']);
        $this->assertStringNotContainsString('Mariam', $response->getContent());

        // Un tiers n'y a pas accès.
        $tiers = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->actingAs($tiers, 'sanctum')->getJson("/api/v1/missions/{$mission->id}/state-history")->assertForbidden();
    }
}
