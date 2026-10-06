<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\MissionMessage;
use App\Models\Order;
use App\Models\Permission;
use App\Models\User;
use App\Services\SolvencyPassportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chantier 39 — clôture des anomalies résiduelles de sécurité et d'API.
 * Lot A : Constats 10 (capacités fines admin), 13 (markAsRead chat), 19 (routes redondantes).
 */
class Chantier39SecurityAndApiFixesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'kyc_status' => 'actif'], $attributes));
    }

    private function makeMission(User $client, User $artisan): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier de test',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 65000,
            'montant_mo' => 35000,
            'ratio_materiaux' => 0.65,
        ]);
    }

    private function makeOrder(User $client, User $supplier, ?User $driver = null): Order
    {
        return Order::create([
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'driver_id' => $driver?->id,
            'delivery_mode' => 'delivery',
            'status' => 'driver_picked_up',
            'subtotal' => 10000,
            'delivery_cost' => 2000,
            'platform_fee' => 300,
            'total_amount' => 12300,
            'pickup_code' => 'LIVREUR-1111',
            'reception_code' => 'RECEPTION-2222',
        ]);
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function restrictedAdmin(array $capabilities): User
    {
        $admin = $this->user('admin');

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }

        return $admin;
    }

    // ── Constat 13 : MissionChatController::markAsRead ───────────────────────────

    public function test_mark_as_read_autorise_les_participants_de_la_mission(): void
    {
        $client = $this->user('client');
        $artisan = $this->user('artisan');
        $mission = $this->makeMission($client, $artisan);

        $message = MissionMessage::create([
            'mission_id' => $mission->id,
            'sender_id' => $artisan->id,
            'content' => 'Bonjour, travail bien avancé.',
            'type' => 'text',
        ]);

        $response = $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/messages/{$message->id}/read");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_mark_as_read_refuse_un_utilisateur_externe_a_la_mission(): void
    {
        $client = $this->user('client');
        $artisan = $this->user('artisan');
        $outsider = $this->user('client');

        $mission = $this->makeMission($client, $artisan);

        $message = MissionMessage::create([
            'mission_id' => $mission->id,
            'sender_id' => $artisan->id,
            'content' => 'Message privé de chantier.',
            'type' => 'text',
        ]);

        $response = $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/messages/{$message->id}/read");

        $response->assertStatus(403);
        $this->assertNull($message->fresh()->read_at);
    }

    // ── Constat 10 : DeliveryTrackingController et capacités fines admin ──────────

    public function test_fleet_overview_refuse_un_administrateur_sans_capacite(): void
    {
        // Administrateur restreint avec seulement 'admin.users.view' (pas 'admin.missions.view')
        $adminSansDroit = $this->restrictedAdmin(['admin.users.view']);

        $response = $this->actingAs($adminSansDroit, 'sanctum')
            ->getJson('/api/v1/deliveries/fleet-map');

        $response->assertStatus(403);
    }

    public function test_fleet_overview_autorise_un_administrateur_avec_capacite(): void
    {
        $adminAvecDroit = $this->restrictedAdmin(['admin.missions.view']);

        $response = $this->actingAs($adminAvecDroit, 'sanctum')
            ->getJson('/api/v1/deliveries/fleet-map');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_fleet_overview_refuse_un_utilisateur_non_admin(): void
    {
        $client = $this->user('client');

        $response = $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/deliveries/fleet-map');

        $response->assertStatus(403);
    }

    public function test_order_tracking_refuse_un_administrateur_sans_capacite(): void
    {
        $client = $this->user('client');
        $supplier = $this->user('fournisseur');
        $order = $this->makeOrder($client, $supplier);

        $adminSansDroit = $this->restrictedAdmin(['admin.users.view']);

        $response = $this->actingAs($adminSansDroit, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertStatus(403);
    }

    public function test_order_tracking_autorise_un_administrateur_avec_capacite(): void
    {
        $client = $this->user('client');
        $supplier = $this->user('fournisseur');
        $order = $this->makeOrder($client, $supplier);

        $adminAvecDroit = $this->restrictedAdmin(['admin.missions.view']);

        $response = $this->actingAs($adminAvecDroit, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_order_tracking_autorise_le_client_de_la_commande_mais_refuse_un_tiers(): void
    {
        $client = $this->user('client');
        $supplier = $this->user('fournisseur');
        $order = $this->makeOrder($client, $supplier);
        $tiers = $this->user('client');

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertOk();

        $this->actingAs($tiers, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertStatus(403);
    }

    // ── Constat 19 : Nettoyage et intégrité des routes orders / deliveries ─────────

    public function test_creation_commande_exige_kyc_actif(): void
    {
        $clientSansKyc = $this->user('client', ['kyc_status' => 'en_attente']);

        $response = $this->actingAs($clientSansKyc, 'sanctum')
            ->postJson('/api/v1/orders', [
                'supplier_id' => 1,
                'items' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_deliveries_available_exige_kyc_actif(): void
    {
        $livreurSansKyc = $this->user('livreur', ['kyc_status' => 'en_attente']);

        $response = $this->actingAs($livreurSansKyc, 'sanctum')
            ->getJson('/api/v1/deliveries/available');

        $response->assertStatus(403);
    }

    public function test_route_orders_disputes_est_accessible_et_non_interceptee(): void
    {
        $client = $this->user('client');

        $response = $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/orders/disputes');

        // Doit répondre 200 avec la structure de litiges, jamais 404 ni modèle introuvable
        $response->assertOk();
    }

    // ── Constat 16 : Hygiène publique et CORS ─────────────────────────────────────

    public function test_opcache_clear_nest_pas_present_dans_public(): void
    {
        $this->assertFileDoesNotExist(public_path('opcache_clear.php'));
    }

    public function test_cors_wildcard_nest_pas_present_dans_htaccess(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));
        $this->assertStringNotContainsString('Access-Control-Allow-Origin "*"', $htaccess);
    }

    // ── Constat 12 : SolvencyPassportService et jetons ────────────────────────────

    public function test_solvency_passport_rejette_ancien_secret_par_defaut(): void
    {
        $artisan = $this->user('artisan');

        // Signature générée avec l'ancien secret statique 'prosartisan-secret'
        $score = 750;
        $payload = "passport:{$artisan->id}:{$score}:{$artisan->phone}";
        $fakeSig = hash_hmac('sha256', $payload, 'prosartisan-secret');

        $token = base64_encode(json_encode([
            'artisan_id' => $artisan->id,
            'score' => $score,
            'sig' => $fakeSig,
            'issued_at' => now()->timestamp,
        ]));

        $service = app(SolvencyPassportService::class);
        $result = $service->verifyPassportToken($token);

        // Doit être rejeté (null) car config('app.key') doit être respectée
        $this->assertNull($result);
    }

    public function test_solvency_passport_rejette_jeton_expire(): void
    {
        $artisan = $this->user('artisan');
        $score = 750;
        $payload = "passport:{$artisan->id}:{$score}:{$artisan->phone}";
        $sig = hash_hmac('sha256', $payload, config('app.key'));

        // Jeton émis il y a 35 jours (expiration fixée à 30 jours max)
        $token = base64_encode(json_encode([
            'artisan_id' => $artisan->id,
            'score' => $score,
            'sig' => $sig,
            'issued_at' => now()->subDays(35)->timestamp,
        ]));

        $service = app(SolvencyPassportService::class);
        $result = $service->verifyPassportToken($token);

        $this->assertNull($result);
    }

    public function test_solvency_passport_accepte_jeton_valide(): void
    {
        $artisan = $this->user('artisan');

        $service = app(SolvencyPassportService::class);
        $passport = $service->generatePassport($artisan);
        $token = $passport['certification']['token'];

        $result = $service->verifyPassportToken($token);

        $this->assertNotNull($result);
        $this->assertEquals($artisan->id, $result['artisan']['id']);
    }
}
