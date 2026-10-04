<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Permission;
use App\Models\User;
use App\Services\AntiBotService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 27, lot B — droits et statuts des comptes dans le backoffice.
 *
 * Chaque faille relevée par l'analyse du module « Utilisateurs » a ici un
 * test qui reproduit son exploitation (constats A1 à A4, B1 à B5, B7, C2, C3,
 * E1 et E2).
 */
class Chantier27LotBBackofficeAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * Administrateur limité aux capacités transmises.
     *
     * @param  array<int, string>  $capabilities
     * @param  array<string, mixed>  $attributes
     */
    private function adminWith(array $capabilities, array $attributes = []): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif', ...$attributes]);

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }

        return $admin;
    }

    /** Administrateur qui gère les comptes, sans le droit de gérer les rôles. */
    private function accountManager(): User
    {
        return $this->adminWith(['admin.users.view', 'admin.users.manage', 'admin.users.delete', 'admin.rgpd.manage', 'admin.kyc.review']);
    }

    private function fullAccessAdmin(): User
    {
        return $this->adminWith(['admin.full-access']);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'kyc_status' => 'actif',
            'email' => 'admin@prosartisan.ci',
            'password' => Hash::make('MotDePasseInitial1'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formOf(User $user, array $overrides = []): array
    {
        return [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role,
            'kyc_status' => $user->kyc_status,
            ...$overrides,
        ];
    }

    // ── A. Comptes administrateurs réservés ─────────────────────────────────

    public function test_a_restricted_admin_cannot_create_an_administrator(): void
    {
        $this->actingAs($this->accountManager())->post('/admin/users', [
            'name' => 'Nouvel Admin',
            'phone' => '+2250700000001',
            'role' => 'admin',
            'password' => 'secret-12-caracteres',
            'kyc_status' => 'actif',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['phone' => '+2250700000001']);
    }

    public function test_an_admin_created_by_a_role_manager_gets_an_explicit_capability(): void
    {
        $this->actingAs($this->adminWith(['admin.full-access']))->post('/admin/users', [
            'name' => 'Nouvel Admin',
            'phone' => '+2250700000002',
            'role' => 'admin',
            'password' => 'secret-12-caracteres',
            'kyc_status' => 'actif',
        ])->assertSessionHasNoErrors();

        $created = User::where('phone', '+2250700000002')->firstOrFail();

        $this->assertSame('admin', $created->role);
        $this->assertSame(
            ['admin.full-access'],
            DB::table('admin_permission_user')
                ->join('permissions', 'permissions.id', '=', 'admin_permission_user.permission_id')
                ->where('user_id', $created->id)
                ->pluck('permissions.name')
                ->all(),
        );
    }

    public function test_a_restricted_admin_cannot_promote_an_account_to_administrator(): void
    {
        $client = User::factory()->create(['role' => 'client', 'phone' => '+2250700000003']);

        $this->actingAs($this->accountManager())
            ->put("/admin/users/{$client->id}", $this->formOf($client, ['role' => 'admin']))
            ->assertSessionHasErrors('role');

        $this->assertSame('client', $client->refresh()->role);
    }

    public function test_a_demoted_administrator_loses_its_capabilities(): void
    {
        $target = $this->adminWith(['admin.faq.manage'], ['phone' => '+2250700000004']);

        $this->actingAs($this->fullAccessAdmin())
            ->put("/admin/users/{$target->id}", $this->formOf($target, ['role' => 'client']))
            ->assertSessionHasNoErrors();

        $this->assertSame('client', $target->refresh()->role);
        $this->assertSame(0, DB::table('admin_permission_user')->where('user_id', $target->id)->count());
    }

    public function test_a_restricted_admin_cannot_modify_another_administrator(): void
    {
        $other = $this->adminWith(['admin.faq.manage'], ['phone' => '+2250700000005', 'password' => Hash::make('Initial123')]);

        $this->actingAs($this->accountManager())
            ->put("/admin/users/{$other->id}", $this->formOf($other, ['password' => 'pris-en-main']))
            ->assertSessionHasErrors('name');

        $this->assertTrue(Hash::check('Initial123', $other->refresh()->password));
    }

    // ── A2, A4. Super administrateurs intouchables ───────────────────────────

    public function test_nobody_else_can_modify_a_protected_super_admin(): void
    {
        $super = $this->superAdmin();

        foreach ([$this->accountManager(), $this->fullAccessAdmin()] as $actor) {
            $this->actingAs($actor)
                ->put("/admin/users/{$super->id}", $this->formOf($super, [
                    'password' => 'pris-en-main',
                    'email' => 'autre@example.test',
                    'role' => 'client',
                ]))
                ->assertSessionHasErrors('name');
        }

        $super->refresh();
        $this->assertTrue(Hash::check('MotDePasseInitial1', $super->password));
        $this->assertSame('admin@prosartisan.ci', $super->email);
        $this->assertSame('admin', $super->role);
    }

    public function test_a_super_admin_edits_its_own_account_but_neither_its_role_nor_its_email(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)
            ->put("/admin/users/{$super->id}", $this->formOf($super, ['name' => 'Nouveau Nom']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Nouveau Nom', $super->refresh()->name);

        $this->actingAs($super)
            ->put("/admin/users/{$super->id}", $this->formOf($super, ['email' => 'autre@example.test']))
            ->assertSessionHasErrors('email');
        $this->actingAs($super)
            ->put("/admin/users/{$super->id}", $this->formOf($super, ['role' => 'client']))
            ->assertSessionHasErrors('role');

        $super->refresh();
        $this->assertSame('admin@prosartisan.ci', $super->email);
        $this->assertSame('admin', $super->role);
    }

    public function test_a_protected_super_admin_cannot_be_deleted_suspended_or_anonymized(): void
    {
        $super = $this->superAdmin();
        $actor = $this->fullAccessAdmin();

        $this->actingAs($actor)->delete("/admin/users/{$super->id}")->assertSessionHas('error');
        $this->actingAs($actor)->post("/admin/users/{$super->id}/toggle-status", [
            'account_status' => 'suspendu',
            'account_status_reason' => 'Tentative de suspension',
        ])->assertSessionHas('error');
        $this->actingAs($actor)->post('/admin/users/bulk-status', [
            'user_ids' => [$super->id],
            'account_status' => 'suspendu',
            'account_status_reason' => 'Tentative de suspension',
        ])->assertSessionHas('error');
        $this->actingAs($actor)->post("/admin/users/{$super->id}/anonymize")->assertSessionHas('error');

        $super->refresh();
        $this->assertNull($super->deleted_at);
        $this->assertNull($super->anonymized_at);
        $this->assertSame('actif', $super->account_status);
    }

    public function test_a_restricted_admin_cannot_delete_or_suspend_another_administrator(): void
    {
        $other = $this->adminWith(['admin.faq.manage']);
        $actor = $this->accountManager();

        $this->actingAs($actor)->delete("/admin/users/{$other->id}")->assertSessionHas('error');
        $this->actingAs($actor)->post("/admin/users/{$other->id}/toggle-status", [
            'account_status' => 'suspendu',
            'account_status_reason' => 'Tentative de suspension',
        ])->assertSessionHas('error');
        $this->actingAs($actor)->post("/admin/users/{$other->id}/anonymize")->assertSessionHas('error');

        $other->refresh();
        $this->assertNull($other->deleted_at);
        $this->assertNull($other->anonymized_at);
        $this->assertSame('actif', $other->account_status);
    }

    // ── B1. Administrateur suspendu ─────────────────────────────────────────

    public function test_a_suspended_admin_loses_the_backoffice_at_its_next_request(): void
    {
        $admin = $this->fullAccessAdmin();
        $client = User::factory()->create(['role' => 'client']);
        $admin->update(['account_status' => 'suspendu']);

        $this->actingAs($admin)->get('/admin/users')->assertRedirect(route('admin.login'));
        $this->assertGuest('web');

        $this->actingAs($admin->refresh())->post("/admin/users/{$client->id}/toggle-status", [
            'account_status' => 'suspendu',
        ]);
        $this->assertSame('actif', $client->refresh()->account_status);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'admin.session.closed_suspended',
            'subject_id' => $admin->id,
        ]);
    }

    public function test_a_suspended_admin_cannot_log_in(): void
    {
        $admin = $this->adminWith(['admin.full-access'], [
            'email' => 'suspendu@example.test',
            'password' => Hash::make('MonMotDePasse123'),
            'account_status' => 'suspendu',
        ]);

        $challenge = app(AntiBotService::class)->generateChallenge('admin_login');

        $this->post('/admin/login', [
            'identifier' => 'suspendu@example.test',
            'password' => 'MonMotDePasse123',
            '_bot_token' => $challenge['token'],
            '_bot_answer' => (string) $challenge['answer'],
        ])->assertSessionHasErrors('identifier');

        $this->assertNull(session('admin_2fa_user_id'));
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'admin.login.denied',
            'subject_id' => $admin->id,
        ]);
    }

    public function test_a_protected_super_admin_is_never_locked_out_by_its_status(): void
    {
        $super = $this->superAdmin();
        $super->update(['account_status' => 'suspendu']);

        $this->actingAs($super)->get('/admin/users')->assertOk();
    }

    // ── B2. Compte anonymisé figé ───────────────────────────────────────────

    public function test_an_anonymized_account_cannot_be_reactivated_or_reassigned(): void
    {
        $actor = $this->fullAccessAdmin();
        $ghost = User::factory()->create([
            'role' => 'artisan',
            'name' => 'Utilisateur anonymisé',
            'kyc_status' => 'en_attente',
            'account_status' => 'suspendu',
            'anonymized_at' => now(),
        ]);

        $this->actingAs($actor)->post("/admin/users/{$ghost->id}/toggle-status", [
            'account_status' => 'actif',
        ])->assertSessionHas('error');
        $this->actingAs($actor)->post('/admin/users/bulk-status', [
            'user_ids' => [$ghost->id],
            'account_status' => 'actif',
        ])->assertSessionHas('error');
        $this->actingAs($actor)
            ->put("/admin/users/{$ghost->id}", $this->formOf($ghost, ['name' => 'Nouveau Titulaire', 'password' => 'secret123']))
            ->assertSessionHasErrors('name');
        $this->actingAs($actor)->post("/admin/kyc/{$ghost->id}/review", [
            'decision' => 'approuve',
        ])->assertSessionHas('error');

        $ghost->refresh();
        $this->assertSame('suspendu', $ghost->account_status);
        $this->assertSame('Utilisateur anonymisé', $ghost->name);
        $this->assertSame('en_attente', $ghost->kyc_status);
    }

    // ── B3, B4, B5. Statut par une seule voie ───────────────────────────────

    public function test_the_edit_form_no_longer_writes_the_account_status(): void
    {
        $user = User::factory()->create(['role' => 'client', 'phone' => '+2250700000010', 'account_status' => 'actif']);

        $this->actingAs($this->fullAccessAdmin())
            ->put("/admin/users/{$user->id}", $this->formOf($user, ['account_status' => 'suspendu']))
            ->assertSessionHasNoErrors();

        $this->assertSame('actif', $user->refresh()->account_status);
    }

    public function test_a_banned_account_can_be_corrected_and_stays_banned(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'phone' => '+2250700000011',
            'account_status' => 'banni',
            'account_status_reason' => 'Litiges répétés',
            'blocked_at' => now()->subDay(),
        ]);

        $this->actingAs($this->fullAccessAdmin())
            ->put("/admin/users/{$user->id}", $this->formOf($user, ['name' => 'Nom Corrigé']))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nom Corrigé', $user->name);
        $this->assertSame('banni', $user->account_status);
        $this->assertSame('Litiges répétés', $user->account_status_reason);
    }

    public function test_reactivation_clears_the_reason_and_is_audited_with_the_previous_status(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'account_status' => 'suspendu',
            'account_status_reason' => 'Comportement suspect',
            'blocked_at' => now()->subDay(),
        ]);

        $this->actingAs($this->fullAccessAdmin())->post("/admin/users/{$user->id}/toggle-status", [
            'account_status' => 'actif',
        ])->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('actif', $user->account_status);
        $this->assertNull($user->account_status_reason);
        $this->assertNull($user->blocked_at);

        $log = AdminActivityLog::where('action', 'user.status_changed')->where('subject_id', $user->id)->firstOrFail();
        $this->assertSame('suspendu', $log->context['previous_status']);
        $this->assertSame('actif', $log->context['account_status']);
    }

    public function test_an_admin_cannot_change_its_own_role(): void
    {
        $admin = $this->adminWith(['admin.full-access'], ['phone' => '+2250700000012']);

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}", $this->formOf($admin, ['role' => 'client']))
            ->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->refresh()->role);
    }

    // ── B7. Audit du changement groupé ──────────────────────────────────────

    public function test_bulk_status_change_writes_one_audit_line_per_account(): void
    {
        $actor = $this->fullAccessAdmin();
        $targets = User::factory()->count(3)->create(['role' => 'client', 'account_status' => 'actif']);
        $ghost = User::factory()->create(['role' => 'client', 'account_status' => 'suspendu', 'anonymized_at' => now()]);

        $this->actingAs($actor)->post('/admin/users/bulk-status', [
            'user_ids' => $targets->pluck('id')->push($ghost->id)->push($actor->id)->all(),
            'account_status' => 'suspendu',
            'account_status_reason' => 'Campagne anti-fraude',
        ])->assertSessionHas('success');

        foreach ($targets as $target) {
            $this->assertSame('suspendu', $target->refresh()->account_status);
            $this->assertDatabaseHas('admin_activity_logs', ['action' => 'user.status_changed', 'subject_id' => $target->id]);
        }
        $this->assertSame(3, AdminActivityLog::where('action', 'user.status_changed')->count());

        $summary = AdminActivityLog::where('action', 'user.bulk_status_changed')->firstOrFail();
        $this->assertSame(3, $summary->context['count']);
        $this->assertCount(2, $summary->context['skipped']);
        $this->assertSame('actif', $actor->refresh()->account_status);
    }

    // ── C2, C3. Téléphone et rôle livreur ───────────────────────────────────

    public function test_the_phone_number_must_follow_the_ivorian_format(): void
    {
        $actor = $this->fullAccessAdmin();

        $this->actingAs($actor)->post('/admin/users', [
            'name' => 'Sans Numéro',
            'phone' => 'abc',
            'role' => 'client',
            'password' => 'secret123',
            'kyc_status' => 'en_attente',
        ])->assertSessionHasErrors('phone');

        $legacy = User::factory()->create(['role' => 'client', 'phone' => '0700000013']);

        // Un numéro enregistré hors format reste accepté tant qu'il n'est pas modifié.
        $this->actingAs($actor)
            ->put("/admin/users/{$legacy->id}", $this->formOf($legacy, ['name' => 'Nom Corrigé']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Nom Corrigé', $legacy->refresh()->name);

        $this->actingAs($actor)
            ->put("/admin/users/{$legacy->id}", $this->formOf($legacy, ['phone' => '0700000014']))
            ->assertSessionHasErrors('phone');
        $this->assertSame('0700000013', $legacy->refresh()->phone);
    }

    public function test_a_driver_account_can_be_created_and_modified(): void
    {
        $actor = $this->fullAccessAdmin();

        $this->actingAs($actor)->post('/admin/users', [
            'name' => 'Livreur Test',
            'phone' => '+2250700000015',
            'role' => 'livreur',
            'password' => 'secret123',
            'kyc_status' => 'en_attente',
        ])->assertSessionHasNoErrors();

        $driver = User::where('phone', '+2250700000015')->firstOrFail();
        $this->assertSame('livreur', $driver->role);

        $this->actingAs($actor)
            ->put("/admin/users/{$driver->id}", $this->formOf($driver, ['name' => 'Livreur Renommé']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Livreur Renommé', $driver->refresh()->name);
    }

    // ── E1, E2. Liste allégée ───────────────────────────────────────────────

    public function test_the_users_list_only_sends_what_the_screen_displays(): void
    {
        $actor = $this->fullAccessAdmin();
        User::factory()->create([
            'role' => 'artisan',
            'name' => 'Zed Dernier Inscrit',
            'fcm_token' => 'jeton-push-secret',
            'device_fingerprint' => 'empreinte-secrete',
            'payment_phone' => '+2250700000016',
            'created_at' => now()->addMinute(),
        ]);

        $this->actingAs($actor)->get('/admin/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('usersPage.data.0.name', 'Zed Dernier Inscrit')
            ->where('usersPage.data.0.has_device', true)
            ->where('usersPage.data.0.is_protected', false)
            ->missing('usersPage.data.0.fcm_token')
            ->missing('usersPage.data.0.device_fingerprint')
            ->missing('usersPage.data.0.payment_phone')
            ->missing('usersPage.data.0.wallet_mo')
            ->missing('usersPage.data.0.wallet_materiaux')
            ->missing('usersPage.data.0.coordinates'));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_rows(): void
    {
        $actor = $this->fullAccessAdmin();

        $count = function () use ($actor): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($actor)->get('/admin/users')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        User::factory()->count(3)->create(['role' => 'artisan']);
        // Premier appel hors mesure : il remplit les caches du tableau de bord et des droits.
        $count();
        $few = $count();

        User::factory()->count(20)->create(['role' => 'artisan']);
        $many = $count();

        $this->assertSame($few, $many, 'Le nombre de requêtes dépend du nombre de comptes affichés.');
    }
}
