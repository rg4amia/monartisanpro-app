<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Mission;
use App\Models\MissionStateTransition;
use App\Models\MobileMoneyPayout;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 21 — compléments des historiques : issue des litiges de commande,
 * versements, types d'opération, pagination des commandes, historique des
 * états pour le Référent.
 */
class Chantier21HistoryComplementsTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $supplier;

    private User $livreur;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif', 'name' => 'Awa Traoré']);
        $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $this->livreur = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
        $this->admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'client_id' => $this->client->id, 'supplier_id' => $this->supplier->id, 'driver_id' => $this->livreur->id,
            'delivery_mode' => 'delivery', 'status' => 'delivered', 'delivered_at' => now(),
            'subtotal' => 20000, 'platform_fee' => 0, 'delivery_cost' => 1500, 'total_amount' => 21500,
            'pickup_code' => '4821', 'reception_code' => '7734',
        ], $attributes));
    }

    // ── Litiges de commande ──────────────────────────────────────────────

    public function test_l_ouverture_d_un_litige_de_commande_laisse_une_ligne_d_historique(): void
    {
        $order = $this->order();

        app(OrderService::class)->openOrderDispute($order, $this->client, 'Colis incomplet à la réception');

        $dispute = OrderDispute::where('order_id', $order->id)->sole();
        $this->assertSame('ouvert', $dispute->statut);
        $this->assertSame('Colis incomplet à la réception', $dispute->reason);
        $this->assertSame($this->client->id, $dispute->opened_by);
    }

    public function test_l_administrateur_clot_un_litige_de_commande_et_son_issue_est_conservee(): void
    {
        $order = $this->order();
        app(OrderService::class)->openOrderDispute($order, $this->client, 'Colis incomplet à la réception');

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/dispute/resolve", [
                'outcome' => 'reclamation_acceptee',
                'note' => 'Sacs manquants confirmés par la photo de livraison.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $dispute = OrderDispute::where('order_id', $order->id)->sole();
        $this->assertSame('resolu', $dispute->statut);
        $this->assertSame('reclamation_acceptee', $dispute->outcome);
        $this->assertSame($this->admin->id, $dispute->resolved_by);
        $this->assertNotNull($dispute->resolved_at);
        $this->assertSame('delivered', $order->fresh()->status);

        $this->assertTrue(AdminActivityLog::where('action', 'order_dispute.resolved')->exists());
        foreach ([[$this->client, 'client'], [$this->supplier, 'fournisseur'], [$this->livreur, 'livreur']] as [$user, $audience]) {
            $this->assertTrue(
                Notification::where('user_id', $user->id)->where('event_key', "commande.litige_clos.{$audience}")->exists(),
                "Notification de clôture manquante pour {$audience}."
            );
        }

        // Une seconde clôture est refusée : la commande n'est plus en litige.
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/dispute/resolve", ['outcome' => 'reclamation_rejetee', 'note' => 'Second avis.'])
            ->assertSessionHasErrors('order');
        $this->assertSame('reclamation_acceptee', $dispute->fresh()->outcome);
    }

    public function test_la_cloture_exige_une_issue_connue_un_motif_et_le_droit_d_arbitrer(): void
    {
        $order = $this->order();
        app(OrderService::class)->openOrderDispute($order, $this->client, 'Colis incomplet à la réception');

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/dispute/resolve", ['outcome' => 'rembourse', 'note' => 'Décision.'])
            ->assertSessionHasErrors('outcome');
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/dispute/resolve", ['outcome' => 'reclamation_rejetee', 'note' => 'Non'])
            ->assertSessionHasErrors('note');

        $lecteur = User::factory()->create(['role' => 'admin']);
        app(AdminPermissionService::class)->sync($lecteur, ['admin.litiges.view'], $this->admin);
        $this->actingAs($lecteur)
            ->post("/admin/orders/{$order->id}/dispute/resolve", ['outcome' => 'reclamation_rejetee', 'note' => 'Réclamation infondée.'])
            ->assertForbidden();

        $this->assertSame('disputed', $order->fresh()->status);
    }

    public function test_le_fournisseur_le_livreur_et_le_client_retrouvent_l_issue_de_leurs_litiges_de_commande(): void
    {
        $order = $this->order();
        app(OrderService::class)->openOrderDispute($order, $this->client, 'Colis incomplet à la réception');
        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/dispute/resolve", [
            'outcome' => 'reclamation_rejetee', 'note' => 'Livraison conforme à la photo.',
        ]);

        $autre = $this->order(['supplier_id' => User::factory()->create(['role' => 'fournisseur'])->id, 'driver_id' => null]);
        app(OrderService::class)->openOrderDispute($autre, $this->client, 'Mauvais produit livré');

        foreach ([$this->supplier, $this->livreur] as $acteur) {
            $response = $this->actingAs($acteur, 'sanctum')
                ->getJson('/api/v1/orders/disputes')
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.order_id', $order->id)
                ->assertJsonPath('data.0.statut_label', 'Résolu')
                ->assertJsonPath('data.0.outcome_label', 'Réclamation du client rejetée');

            $this->assertStringNotContainsString($this->client->phone, $response->getContent());
            $this->assertStringNotContainsString('Awa Traor', $response->getContent());
        }

        $this->actingAs($this->client, 'sanctum')->getJson('/api/v1/orders/disputes')->assertOk()->assertJsonPath('meta.total', 2);
        $this->actingAs($this->client, 'sanctum')->getJson('/api/v1/orders/disputes?statut=ouvert')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($this->client, 'sanctum')->getJson('/api/v1/orders/disputes?statut=clos')->assertStatus(422);

        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $this->actingAs($artisan, 'sanctum')->getJson('/api/v1/orders/disputes')->assertForbidden();
    }

    // ── Commandes paginées ───────────────────────────────────────────────

    public function test_les_commandes_paginees_placent_les_commandes_a_suivre_avant_les_commandes_closes(): void
    {
        $ancienneEnCours = $this->order(['status' => 'paid']);
        Order::whereKey($ancienneEnCours->id)->update(['created_at' => now()->subDays(30)]);
        $this->order();
        $this->order(['status' => 'cancelled']);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/orders?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $ancienneEnCours->id);

        $this->actingAs($this->supplier, 'sanctum')
            ->getJson('/api/v1/supplier/orders?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $ancienneEnCours->id);

        // Sans pagination demandée : la liste complète, comme avant.
        $this->actingAs($this->supplier, 'sanctum')
            ->getJson('/api/v1/supplier/orders')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('meta');
    }

    // ── Versements ───────────────────────────────────────────────────────

    public function test_l_historique_des_versements_se_pagine_et_se_filtre_par_statut(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        foreach (['verse', 'verse', 'echoue'] as $index => $statut) {
            MobileMoneyPayout::create([
                'reference' => 'PAY-C21-'.$index, 'user_id' => $artisan->id, 'context' => 'jalon', 'wallet_type' => 'wallet_mo',
                'montant' => 10000, 'provider' => 'wave', 'phone' => '+2250700000000', 'statut' => $statut,
            ]);
        }
        MobileMoneyPayout::create([
            'reference' => 'PAY-C21-X', 'user_id' => $this->client->id, 'context' => 'jalon', 'wallet_type' => 'wallet_mo',
            'montant' => 10000, 'provider' => 'wave', 'phone' => '+2250700000001', 'statut' => 'verse',
        ]);

        $this->actingAs($artisan, 'sanctum')
            ->getJson('/api/v1/payouts?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAs($artisan, 'sanctum')
            ->getJson('/api/v1/payouts?per_page=10&statut=verse')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($artisan, 'sanctum')->getJson('/api/v1/payouts?per_page=10&statut=paye')->assertStatus(422);

        // Sans pagination demandée : la liste nue, comme avant.
        $this->actingAs($artisan, 'sanctum')->getJson('/api/v1/payouts')->assertOk()->assertJsonCount(3, 'data')->assertJsonMissingPath('meta');
    }

    // ── Types d'opération ────────────────────────────────────────────────

    public function test_l_historique_des_paiements_annonce_les_types_d_operation_presents(): void
    {
        foreach (['acompte', 'acompte', 'paiement_livraison'] as $index => $type) {
            Transaction::create([
                'user_id' => $this->client->id, 'type' => $type, 'montant' => 5000,
                'wallet_source' => 'client_mobile_money', 'wallet_dest' => 'escrow', 'provider' => 'wave',
                'statut' => 'confirme', 'reference_externe' => 'TXN-C21-'.$index,
            ]);
        }

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v1/transactions?type=paiement_livraison')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Les types proposés ne dépendent pas du filtre en cours.
        $this->assertEqualsCanonicalizing(
            ['acompte', 'paiement_livraison'],
            collect($response->json('meta.types'))->pluck('value')->all(),
        );
        $this->assertSame('Courses de livraison', collect($response->json('meta.types'))->firstWhere('value', 'paiement_livraison')['label']);
    }

    // ── Historique des états pour le Référent ────────────────────────────

    public function test_le_referent_lit_l_historique_d_un_chantier_de_son_ressort_sans_le_nom_des_parties(): void
    {
        $referent = User::factory()->create(['role' => 'referent', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $base = [
            'client_id' => $this->client->id, 'artisan_id' => $artisan->id, 'description' => 'Extension',
            'status' => 'in_progress', 'montant_materiaux' => 0, 'ratio_materiaux' => 0,
        ];
        $grand = Mission::create($base + ['montant_total' => 3000000, 'montant_mo' => 3000000]);
        $petit = Mission::create($base + ['montant_total' => 100000, 'montant_mo' => 100000]);

        MissionStateTransition::create([
            'mission_id' => $grand->id, 'from_state' => 'funded_locked', 'to_state' => 'in_progress',
            'user_id' => $this->client->id, 'reason' => 'Première étape validée', 'created_at' => now(),
        ]);

        $response = $this->actingAs($referent, 'sanctum')
            ->getJson("/api/v1/missions/{$grand->id}/state-history")
            ->assertOk()
            ->assertJsonPath('data.transitions.0.role_label', 'Client')
            ->assertJsonPath('data.transitions.0.user.name', null);

        $this->assertStringNotContainsString('Awa Traor', $response->getContent());

        // Un chantier sous le seuil, ni à visiter ni visité : hors de son ressort.
        $this->actingAs($referent, 'sanctum')->getJson("/api/v1/missions/{$petit->id}/state-history")->assertForbidden();

        $petit->update(['referent_validated_by' => $referent->id, 'referent_validated_at' => now()]);
        $this->actingAs($referent, 'sanctum')->getJson("/api/v1/missions/{$petit->id}/state-history")->assertOk();
    }
}
