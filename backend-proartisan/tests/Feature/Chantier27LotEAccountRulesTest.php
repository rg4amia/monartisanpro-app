<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\AdminActivityLog;
use App\Models\DriverCashout;
use App\Models\FournisseurAgree;
use App\Models\KycDocument;
use App\Models\Mission;
use App\Models\MobileMoneyPayout;
use App\Models\Notification;
use App\Models\Otp;
use App\Models\Permission;
use App\Models\User;
use App\Services\MobileMoneyPayoutService;
use App\Services\PaymentPhoneService;
use App\Services\WalletService;
use App\Services\WaveService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Chantier 27, lot E — les onze règles décidées le 04/10/2026 : récupération
 * de compte, numéro de paiement, suppression et restauration, statut KYC,
 * suspension, changement de rôle, mot de passe, boutique, droits des rôles.
 */
class Chantier27LotEAccountRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        DB::table('admin_permission_user')->insert([
            'user_id' => $admin->id,
            'permission_id' => Permission::where('name', 'admin.full-access')->value('id'),
            'created_at' => now(),
        ]);

        return $admin;
    }

    /** @param  array<string, mixed>  $attributes */
    private function account(string $role, array $attributes = []): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif', ...$attributes]);
    }

    private function ongoingMission(User $client, User $artisan): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Pose de carrelage',
            'client_address' => 'Cocody, rue des Jardins',
            'status' => 'in_progress',
            'montant_total' => 100000,
            'montant_materiaux' => 0,
            'montant_mo' => 100000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formOf(User $user, array $overrides = []): array
    {
        return ['name' => $user->name, 'phone' => $user->phone, 'email' => $user->email, 'role' => $user->role, ...$overrides];
    }

    private function paymentCode(User $user): string
    {
        $this->actingAs($user)->postJson('/api/v1/users/payment-phone/code')->assertOk();

        return (string) Otp::where('phone', $user->phone)->where('action', 'payment_phone')->latest('id')->value('code');
    }

    // ── 1. Changement de numéro par le support ──────────────────────────────

    public function test_a_phone_change_by_support_closes_sessions_and_warns_the_holder(): void
    {
        $artisan = $this->account('artisan', ['phone' => '+2250700000100']);
        $artisan->createToken('mobile');

        $this->actingAs($this->admin())
            ->put("/admin/users/{$artisan->id}", $this->formOf($artisan, ['phone' => '+2250700000101']))
            ->assertSessionHasNoErrors();

        $artisan->refresh();
        $this->assertSame('+2250700000101', $artisan->phone);
        $this->assertSame(0, $artisan->tokens()->count());

        $notification = Notification::where('user_id', $artisan->id)->where('event_key', 'compte.telephone_modifie.utilisateur')->firstOrFail();
        $this->assertStringContainsString('+2250700000101', $notification->body);

        $log = AdminActivityLog::where('action', 'user.updated')->where('subject_id', $artisan->id)->firstOrFail();
        $this->assertTrue($log->context['phone_changed']);
        $this->assertSame('+2250700000100', $log->context['before']['phone']);
    }

    // ── 2. Numéro de paiement ───────────────────────────────────────────────

    public function test_changing_the_payment_number_requires_the_code_sent_to_the_account_number(): void
    {
        $artisan = $this->account('artisan', ['phone' => '+2250700000110', 'payment_phone' => '+2250700000110']);

        $this->actingAs($artisan)
            ->putJson("/api/v1/users/{$artisan->id}", ['payment_phone' => '+2250500000111', 'preferred_payment_provider' => 'wave'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_phone_code');
        $this->actingAs($artisan)
            ->putJson("/api/v1/users/{$artisan->id}", ['payment_phone' => '+2250500000111', 'payment_phone_code' => '0000'])
            ->assertStatus(422);

        $this->assertSame('+2250700000110', $artisan->fresh()->payment_phone);
        $this->assertNull($artisan->fresh()->payment_phone_changed_at);
    }

    public function test_a_confirmed_change_warns_the_holder_and_suspends_withdrawals(): void
    {
        $artisan = $this->account('artisan', ['phone' => '+2250700000120', 'payment_phone' => '+2250700000120']);

        $this->actingAs($artisan)
            ->putJson("/api/v1/users/{$artisan->id}", [
                'payment_phone' => '+2250500000121',
                'preferred_payment_provider' => 'orange_money',
                'payment_phone_code' => $this->paymentCode($artisan),
            ])
            ->assertOk();

        $artisan->refresh();
        $this->assertSame('+2250500000121', $artisan->payment_phone);
        $this->assertNotNull($artisan->payment_phone_changed_at);

        $notification = Notification::where('user_id', $artisan->id)->where('event_key', 'compte.numero_paiement_modifie.utilisateur')->firstOrFail();
        $this->assertStringContainsString('0121', $notification->body);
        $this->assertStringContainsString('24', $notification->body);
    }

    public function test_returning_to_the_account_number_needs_no_code(): void
    {
        $artisan = $this->account('artisan', ['phone' => '+2250700000130', 'payment_phone' => '+2250500000131']);

        $this->actingAs($artisan)
            ->putJson("/api/v1/users/{$artisan->id}", ['payment_phone' => '+2250700000130', 'preferred_payment_provider' => 'wave'])
            ->assertOk();

        $this->assertSame('+2250700000130', $artisan->fresh()->payment_phone);
        $this->assertNull($artisan->fresh()->payment_phone_changed_at);
    }

    public function test_another_screen_cannot_replace_the_payment_number_of_an_earner(): void
    {
        $driver = $this->account('livreur', ['phone' => '+2250700000140', 'payment_phone' => '+2250700000140']);

        app(PaymentPhoneService::class)->syncFromFlow($driver, '+2250500000141', 'wave');
        $this->assertSame('+2250700000140', $driver->fresh()->payment_phone);

        // Un compte sans numéro de paiement peut en recevoir un, avec le délai de sécurité.
        $newDriver = $this->account('livreur', ['phone' => '+2250700000142', 'payment_phone' => null]);
        app(PaymentPhoneService::class)->syncFromFlow($newDriver, '+2250500000143', 'wave');
        $this->assertSame('+2250500000143', $newDriver->fresh()->payment_phone);
        $this->assertNotNull($newDriver->fresh()->payment_phone_changed_at);
    }

    public function test_withdrawals_are_suspended_for_24_hours_after_the_change(): void
    {
        $driver = $this->account('livreur', [
            'phone' => '+2250700000150',
            'payment_phone' => '+2250500000151',
            'payment_phone_changed_at' => now()->subHours(2),
        ]);
        app(WalletService::class)->credit($driver, WalletType::WALLET_MO, 20000, 'Gains de courses');

        $this->actingAs($driver)->postJson('/api/v1/driver/cashouts', ['montant_brut' => 5000, 'mode_retrait' => 'wave'])
            ->assertStatus(422);
        $this->assertSame(0, DriverCashout::count());

        $this->travel(25)->hours();

        $this->actingAs($driver->fresh())->postJson('/api/v1/driver/cashouts', ['montant_brut' => 5000, 'mode_retrait' => 'wave'])
            ->assertSuccessful();
    }

    public function test_a_withdrawal_goes_to_the_registered_number_only(): void
    {
        $driver = $this->account('livreur', ['phone' => '+2250700000160', 'payment_phone' => '+2250500000161']);
        app(WalletService::class)->credit($driver, WalletType::WALLET_MO, 20000, 'Gains de courses');

        $this->actingAs($driver)->postJson('/api/v1/driver/cashouts', [
            'montant_brut' => 5000,
            'mode_retrait' => 'wave',
            'beneficiary_phone' => '+2250100000999',
        ])->assertStatus(422);

        $this->actingAs($driver)->postJson('/api/v1/driver/cashouts', [
            'montant_brut' => 5000,
            'mode_retrait' => 'wave',
            'beneficiary_phone' => '+2250500000161',
        ])->assertSuccessful();
    }

    public function test_an_automatic_payout_waits_for_the_end_of_the_security_delay(): void
    {
        $artisan = $this->account('artisan', [
            'phone' => '+2250700000170',
            'payment_phone' => '+2250500000171',
            'preferred_payment_provider' => 'wave',
            'payment_phone_changed_at' => now()->subHour(),
        ]);
        app(WalletService::class)->credit($artisan, WalletType::WALLET_MO, 30000, 'Séquestre libéré');

        $this->mock(WaveService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('transferToMobileMoney');
        });

        $payout = app(MobileMoneyPayoutService::class)->dispatch(
            $artisan,
            WalletType::WALLET_MO,
            10000,
            MobileMoneyPayout::CONTEXT_JALON,
            'wave',
            'Paiement d\'étape',
            ['type' => 'paiement_artisan', 'wallet_source' => 'wallet_mo_'.$artisan->id, 'wallet_dest' => 'wave_'.$artisan->id],
        );

        $this->assertSame(MobileMoneyPayout::STATUT_ECHOUE, $payout->statut);
        $this->assertSame(0, $payout->attempts);
        $this->assertNotNull($payout->next_retry_at);
        $this->assertTrue($payout->next_retry_at->isFuture());
        // Rien n'est débité : les fonds restent sur le portefeuille.
        $this->assertSame(30000, $artisan->fresh()->getWalletBalance(WalletType::WALLET_MO));
    }

    // ── 3. Suppression d'un compte engagé ───────────────────────────────────

    public function test_an_engaged_account_cannot_delete_itself(): void
    {
        $client = $this->account('client');
        $artisan = $this->account('artisan');
        app(WalletService::class)->credit($artisan, WalletType::WALLET_MO, 60000, 'Séquestre');
        $this->ongoingMission($client, $artisan);

        Sanctum::actingAs($artisan);
        $response = $this->deleteJson("/api/v1/users/{$artisan->id}")->assertStatus(422);

        $this->assertStringContainsString('60 000 FCFA', $response->json('message'));
        $this->assertStringContainsString('une mission en cours', $response->json('message'));
        $this->assertNull($artisan->fresh()->anonymized_at);
    }

    public function test_an_admin_cannot_delete_or_anonymize_an_engaged_account(): void
    {
        $admin = $this->admin();
        $client = $this->account('client');
        $artisan = $this->account('artisan');
        $this->ongoingMission($client, $artisan);

        $this->actingAs($admin)->delete("/admin/users/{$artisan->id}")->assertSessionHas('error');
        $this->actingAs($admin)->post("/admin/users/{$client->id}/anonymize")->assertSessionHas('error');

        $this->assertNull($artisan->fresh()->deleted_at);
        $this->assertNull($client->fresh()->anonymized_at);
    }

    public function test_a_free_account_is_still_deleted(): void
    {
        $client = $this->account('client');

        Sanctum::actingAs($client);
        $this->deleteJson("/api/v1/users/{$client->id}")->assertOk();

        $this->assertNotNull($client->fresh()->anonymized_at);
    }

    // ── 4. Compte supprimé : restauration et filtre ─────────────────────────

    public function test_a_deleted_account_is_listed_and_restored(): void
    {
        $admin = $this->admin();
        $client = $this->account('client', ['name' => 'Client Supprimé', 'phone' => '+2250700000200']);
        $client->delete();

        $this->actingAs($admin)->get('/admin/users?etat_users=supprimes')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('usersPage.data', 1)
            ->where('usersPage.data.0.name', 'Client Supprimé')
            ->whereNot('usersPage.data.0.deleted_at', null));

        $this->actingAs($admin)->post("/admin/users/{$client->id}/restore")->assertSessionHas('success');

        $this->assertNull($client->fresh()->deleted_at);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'user.restored', 'subject_id' => $client->id]);
    }

    public function test_restoring_requires_the_delete_capability(): void
    {
        $restricted = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        DB::table('admin_permission_user')->insert([
            'user_id' => $restricted->id,
            'permission_id' => Permission::where('name', 'admin.users.manage')->value('id'),
            'created_at' => now(),
        ]);
        $client = $this->account('client');
        $client->delete();

        $this->actingAs($restricted)->post("/admin/users/{$client->id}/restore")->assertForbidden();

        $this->assertNotNull(User::withTrashed()->find($client->id)->deleted_at);
    }

    // ── 5. Statut KYC hors du formulaire ────────────────────────────────────

    public function test_the_form_no_longer_activates_the_kyc_of_an_application_account(): void
    {
        $admin = $this->admin();
        $artisan = $this->account('artisan', ['kyc_status' => 'en_attente', 'phone' => '+2250700000210']);

        $this->actingAs($admin)
            ->put("/admin/users/{$artisan->id}", $this->formOf($artisan, ['kyc_status' => 'actif']))
            ->assertSessionHasNoErrors();
        $this->assertSame('en_attente', $artisan->fresh()->kyc_status);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Nouveau Client',
            'phone' => '+2250700000211',
            'role' => 'client',
            'password' => 'secret123',
            'kyc_status' => 'actif',
        ])->assertSessionHasNoErrors();
        $this->assertSame('en_attente', User::where('phone', '+2250700000211')->value('kyc_status'));
    }

    public function test_a_referent_is_created_active_by_an_administrator(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Référent Zone Nord',
            'phone' => '+2250700000212',
            'role' => 'referent',
            'password' => 'secret123',
        ])->assertSessionHasNoErrors();

        $this->assertSame('actif', User::where('phone', '+2250700000212')->value('kyc_status'));
    }

    // ── 6. Rejet d'un compte déjà actif ─────────────────────────────────────

    public function test_rejecting_an_active_account_closes_its_sessions_and_records_ongoing_missions(): void
    {
        $admin = $this->admin();
        $client = $this->account('client');
        $artisan = $this->account('artisan');
        $artisan->createToken('mobile');
        foreach (['cni', 'selfie'] as $type) {
            KycDocument::create(['user_id' => $artisan->id, 'type' => $type, 'file_url' => "kyc/{$type}.jpg", 'statut' => 'approuve']);
        }
        $this->ongoingMission($client, $artisan);

        $this->actingAs($admin)->post("/admin/kyc/{$artisan->id}/review", [
            'decision' => 'rejete',
            'rejection_reason' => 'Pièce signalée comme falsifiée.',
        ])->assertSessionHas('success');

        $artisan->refresh();
        $this->assertSame('rejete', $artisan->kyc_status);
        $this->assertSame(0, $artisan->tokens()->count());

        $log = AdminActivityLog::where('action', 'kyc.reviewed')->where('subject_id', $artisan->id)->firstOrFail();
        $this->assertTrue($log->context['compte_actif_avant']);
        $this->assertTrue($log->context['sessions_fermees']);
        $this->assertSame(1, $log->context['missions_en_cours']);
    }

    public function test_the_users_list_gives_the_number_of_ongoing_missions(): void
    {
        $client = $this->account('client');
        $artisan = $this->account('artisan', ['name' => 'Zed Artisan', 'created_at' => now()->addMinute()]);
        $this->ongoingMission($client, $artisan);

        $this->actingAs($this->admin())->get('/admin/users?role_users=artisan')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('usersPage.data.0.name', 'Zed Artisan')
            ->where('usersPage.data.0.missions_ongoing_count', 1));
    }

    // ── 7. Suspension ───────────────────────────────────────────────────────

    public function test_a_suspension_requires_a_reason_and_closes_the_sessions(): void
    {
        $admin = $this->admin();
        $client = $this->account('client');
        $client->createToken('mobile');

        $this->actingAs($admin)->post("/admin/users/{$client->id}/toggle-status", ['account_status' => 'suspendu'])
            ->assertSessionHasErrors('account_status_reason');
        $this->actingAs($admin)->post("/admin/users/{$client->id}/toggle-status", ['account_status' => 'suspendu', 'account_status_reason' => 'abc'])
            ->assertSessionHasErrors('account_status_reason');
        $this->actingAs($admin)->post('/admin/users/bulk-status', ['user_ids' => [$client->id], 'account_status' => 'suspendu'])
            ->assertSessionHasErrors('account_status_reason');
        $this->assertSame('actif', $client->fresh()->account_status);
        $this->assertSame(1, $client->tokens()->count());

        $this->actingAs($admin)->post("/admin/users/{$client->id}/toggle-status", [
            'account_status' => 'suspendu',
            'account_status_reason' => 'Paiements frauduleux signalés',
        ])->assertSessionHas('success');

        $this->assertSame('suspendu', $client->fresh()->account_status);
        $this->assertSame(0, $client->tokens()->count());
    }

    public function test_the_application_shows_the_real_reason_of_the_suspension(): void
    {
        $client = $this->account('client', [
            'account_status' => 'suspendu',
            'account_status_reason' => 'Paiements frauduleux signalés',
        ]);

        Sanctum::actingAs($client);
        $response = $this->getJson('/api/v1/missions')->assertForbidden();

        $this->assertStringContainsString('Paiements frauduleux signalés', $response->json('message'));
        $this->assertStringNotContainsString('litiges', $response->json('message'));
    }

    // ── 8. Changement de rôle : un seul circuit ─────────────────────────────

    public function test_a_role_change_from_the_backoffice_resets_the_kyc_and_closes_the_sessions(): void
    {
        $client = $this->account('client', ['phone' => '+2250700000220']);
        $client->createToken('mobile');

        $this->actingAs($this->admin())
            ->put("/admin/users/{$client->id}", $this->formOf($client, ['role' => 'artisan']))
            ->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('artisan', $client->role);
        $this->assertSame('en_attente', $client->kyc_status);
        $this->assertSame(0, $client->tokens()->count());
        $this->assertDatabaseHas('artisan_profiles', ['user_id' => $client->id]);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'user.role.updated', 'subject_id' => $client->id]);
    }

    public function test_an_engaged_account_does_not_change_role_by_either_route(): void
    {
        $admin = $this->admin();
        $client = $this->account('client');
        $artisan = $this->account('artisan', ['phone' => '+2250700000221']);
        $this->ongoingMission($client, $artisan);

        $this->actingAs($admin)
            ->put("/admin/users/{$artisan->id}", $this->formOf($artisan, ['role' => 'client']))
            ->assertSessionHasErrors('role');
        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$artisan->id}/role", ['role' => 'client'])
            ->assertStatus(422);

        $this->assertSame('artisan', $artisan->fresh()->role);
        $this->assertSame('actif', $artisan->fresh()->kyc_status);
    }

    // ── 9. Mot de passe ─────────────────────────────────────────────────────

    public function test_password_length_depends_on_the_role(): void
    {
        $admin = $this->admin();
        $base = ['name' => 'Compte Test', 'kyc_status' => 'actif'];

        $this->actingAs($admin)->post('/admin/users', $base + ['phone' => '+2250700000230', 'role' => 'admin', 'password' => 'onze-caract'])
            ->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/admin/users', $base + ['phone' => '+2250700000230', 'role' => 'admin', 'password' => 'douze-caract'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/admin/users', $base + ['phone' => '+2250700000231', 'role' => 'client', 'password' => '7-carac'])
            ->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/admin/users', $base + ['phone' => '+2250700000231', 'role' => 'client', 'password' => '8-caract'])
            ->assertSessionHasNoErrors();

        $existing = User::where('phone', '+2250700000230')->firstOrFail();
        $this->actingAs($admin)->put("/admin/users/{$existing->id}", $this->formOf($existing, ['password' => 'trop-court']))
            ->assertSessionHasErrors('password');
        // Sans nouveau mot de passe, un compte se modifie quel que soit l'ancien.
        $this->actingAs($admin)->put("/admin/users/{$existing->id}", $this->formOf($existing, ['name' => 'Renommé']))
            ->assertSessionHasNoErrors();
    }

    // ── 10. Boutique ────────────────────────────────────────────────────────

    public function test_a_supplier_account_no_longer_gets_an_invented_shop(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Quincaillerie Koné',
            'phone' => '+2250700000240',
            'role' => 'fournisseur',
            'password' => 'secret123',
        ])->assertSessionHasNoErrors();

        $supplier = User::where('phone', '+2250700000240')->firstOrFail();
        $this->assertSame(0, FournisseurAgree::where('user_id', $supplier->id)->count());
    }

    public function test_the_shop_is_created_from_the_real_position_of_the_supplier(): void
    {
        $supplier = $this->account('fournisseur', ['name' => 'Quincaillerie Koné']);

        Sanctum::actingAs($supplier);
        $this->putJson("/api/v1/users/{$supplier->id}/location", ['lat' => 5.3484, 'lng' => -3.9881])->assertOk();

        $shop = FournisseurAgree::where('user_id', $supplier->id)->firstOrFail();
        $this->assertSame('en_attente', $shop->statut);
        $this->assertSame('Quincaillerie Koné', $shop->nom_boutique);
        $coords = $shop->getPositionCoords();
        $this->assertEqualsWithDelta(5.3484, $coords['lat'], 0.0001);
        $this->assertEqualsWithDelta(-3.9881, $coords['lng'], 0.0001);

        // Une seconde position met la fiche à jour, sans en créer une autre.
        $this->putJson("/api/v1/users/{$supplier->id}/location", ['lat' => 5.36, 'lng' => -4.0])->assertOk();
        $this->assertSame(1, FournisseurAgree::where('user_id', $supplier->id)->count());
    }

    public function test_the_administrator_sets_the_real_shop_name(): void
    {
        $supplier = $this->account('fournisseur', ['name' => 'Koné Ibrahim', 'phone' => '+2250700000241']);
        Sanctum::actingAs($supplier);
        $this->putJson("/api/v1/users/{$supplier->id}/location", ['lat' => 5.3484, 'lng' => -3.9881])->assertOk();

        $this->actingAs($this->admin(), 'web')
            ->put("/admin/users/{$supplier->id}", $this->formOf($supplier, ['fournisseur_shop_name' => 'Quincaillerie du Plateau']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Quincaillerie du Plateau', FournisseurAgree::where('user_id', $supplier->id)->value('nom_boutique'));
    }

    // ── 11. Droits des rôles ────────────────────────────────────────────────

    public function test_a_protected_action_cannot_be_removed_from_its_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/roles-permissions/revoke', ['role' => 'client', 'permission' => 'mission.create'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permission');

        $this->assertTrue($this->account('client')->hasPermissionTo('mission.create'));
    }

    public function test_a_reserved_action_cannot_be_given_to_another_role(): void
    {
        $admin = $this->admin();

        foreach ([['artisan', 'mission.create'], ['artisan', 'devis.accept'], ['client', 'jcode.scan']] as [$role, $permission]) {
            $this->actingAs($admin)
                ->postJson('/api/v1/admin/roles-permissions/assign', ['role' => $role, 'permission' => $permission])
                ->assertStatus(422)
                ->assertJsonValidationErrors('permission');
        }

        $this->assertFalse($this->account('artisan')->hasPermissionTo('mission.create'));
        $this->assertFalse($this->account('artisan')->hasPermissionTo('devis.accept'));
    }

    public function test_an_ordinary_action_is_still_assigned_and_removed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/roles-permissions/revoke', ['role' => 'client', 'permission' => 'mission.estimate'])
            ->assertOk();
        $this->assertFalse($this->account('client')->hasPermissionTo('mission.estimate'));

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/roles-permissions/assign', ['role' => 'client', 'permission' => 'mission.estimate'])
            ->assertOk();

        $this->actingAs($admin)->get('/admin/roles-permissions')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('protectedRolePermissions.client')
            ->has('reservedRolePermissions'));
    }
}
