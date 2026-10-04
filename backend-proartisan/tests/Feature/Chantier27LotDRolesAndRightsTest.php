<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 27, lot D — onglet « Rôles & Actions » (constats I1, I3, I4 pour
 * les rôles sans effet, et I5 de l'analyse du module « Utilisateurs »).
 */
class Chantier27LotDRolesAndRightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * @param  array<int, string>  $capabilities
     */
    private function adminWith(array $capabilities): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }

        return $admin;
    }

    /** @return array<int, string> */
    private function storedCapabilities(User $user): array
    {
        return DB::table('admin_permission_user')
            ->join('permissions', 'permissions.id', '=', 'admin_permission_user.permission_id')
            ->where('user_id', $user->id)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    // ── I1. « Aucune capacité » distinct d'« accès total » ──────────────────

    public function test_removing_every_capability_no_longer_grants_full_access(): void
    {
        $actor = $this->adminWith(['admin.full-access']);
        $target = $this->adminWith(['admin.faq.manage']);

        $this->actingAs($actor)
            ->post("/admin/admins/{$target->id}/permissions", ['capabilities' => []])
            ->assertSessionHasErrors(['capabilities' => "Cochez au moins une capacité, ou l'accès total."]);

        $this->assertSame(['admin.faq.manage'], $this->storedCapabilities($target));
        $this->assertSame(['admin.faq.manage'], app(AdminPermissionService::class)->capabilitiesFor($target->fresh()));
    }

    public function test_the_service_itself_refuses_an_empty_list(): void
    {
        $actor = $this->adminWith(['admin.full-access']);
        $target = $this->adminWith(['admin.faq.manage']);

        try {
            app(AdminPermissionService::class)->sync($target, [], $actor);
            $this->fail('Une liste vide aurait dû être refusée.');
        } catch (ValidationException $e) {
            $this->assertSame("Cochez au moins une capacité, ou l'accès total.", $e->validator->errors()->first('capabilities'));
        }

        $this->assertSame(['admin.faq.manage'], $this->storedCapabilities($target));
    }

    public function test_full_access_is_granted_by_its_explicit_capability(): void
    {
        $actor = $this->adminWith(['admin.full-access']);
        $target = $this->adminWith(['admin.faq.manage']);

        $this->actingAs($actor)
            ->post("/admin/admins/{$target->id}/permissions", ['capabilities' => ['admin.full-access']])
            ->assertSessionHas('success');

        $this->assertSame(['admin.full-access'], $this->storedCapabilities($target));
        $this->assertSame(['*'], app(AdminPermissionService::class)->capabilitiesFor($target->fresh()));
    }

    public function test_the_migration_writes_full_access_for_admins_without_any_capability(): void
    {
        $bare = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $restricted = $this->adminWith(['admin.faq.manage']);
        $client = User::factory()->create(['role' => 'client']);

        $migration = require database_path('migrations/2026_10_04_110000_grant_explicit_full_access_to_admins_without_capability.php');
        $migration->up();
        $migration->up();

        $this->assertSame(['admin.full-access'], $this->storedCapabilities($bare));
        $this->assertSame(['admin.faq.manage'], $this->storedCapabilities($restricted));
        $this->assertSame([], $this->storedCapabilities($client));
    }

    public function test_an_admin_keeps_role_management_and_user_view_on_its_own_account(): void
    {
        $actor = $this->adminWith(['admin.roles.manage', 'admin.users.view', 'admin.faq.manage']);

        $this->actingAs($actor)
            ->post("/admin/admins/{$actor->id}/permissions", ['capabilities' => ['admin.faq.manage']])
            ->assertSessionHas('success');

        $this->assertSame(
            ['admin.faq.manage', 'admin.roles.manage', 'admin.users.view'],
            $this->storedCapabilities($actor),
        );
    }

    public function test_another_admin_can_be_restricted_without_role_management(): void
    {
        $actor = $this->adminWith(['admin.full-access']);
        $target = $this->adminWith(['admin.full-access']);

        $this->actingAs($actor)
            ->post("/admin/admins/{$target->id}/permissions", ['capabilities' => ['admin.faq.manage']])
            ->assertSessionHas('success');

        $this->assertSame(['admin.faq.manage'], $this->storedCapabilities($target));
        $this->assertFalse($target->fresh()->adminCan('admin.roles.manage'));
    }

    // ── I5. Audit avant / après ─────────────────────────────────────────────

    public function test_the_audit_records_the_capabilities_before_and_after(): void
    {
        $actor = $this->adminWith(['admin.full-access']);
        $target = $this->adminWith(['admin.faq.manage', 'admin.kyc.view']);

        $this->actingAs($actor)
            ->post("/admin/admins/{$target->id}/permissions", ['capabilities' => ['admin.kyc.view', 'admin.kyc.review']])
            ->assertSessionHas('success');

        $log = AdminActivityLog::where('action', 'admin.permissions_updated')->where('subject_id', $target->id)->firstOrFail();

        $this->assertSame(['admin.faq.manage', 'admin.kyc.view'], $log->context['before']);
        $this->assertEqualsCanonicalizing(['admin.kyc.view', 'admin.kyc.review'], $log->context['after']);
        $this->assertFalse($log->context['full_access']);
    }

    // ── I3. Droits des rôles de l'application audités ───────────────────────

    public function test_assigning_and_revoking_a_role_action_is_audited(): void
    {
        $actor = $this->adminWith(['admin.full-access']);

        $this->actingAs($actor)
            ->post('/admin/roles-permissions/assign', ['role' => 'artisan', 'permission' => 'mission.create'], ['X-Inertia' => 'true'])
            ->assertSessionHas('success');
        $this->actingAs($actor)
            ->post('/admin/roles-permissions/revoke', ['role' => 'artisan', 'permission' => 'mission.create'], ['X-Inertia' => 'true'])
            ->assertSessionHas('success');

        foreach (['role_permission.assigned', 'role_permission.revoked'] as $action) {
            $log = AdminActivityLog::where('action', $action)->firstOrFail();
            $this->assertSame($actor->id, $log->admin_id);
            $this->assertSame('artisan', $log->context['role']);
            $this->assertSame('mission.create', $log->context['permission']);
        }
    }

    public function test_a_change_without_effect_is_not_audited(): void
    {
        $actor = $this->adminWith(['admin.full-access']);

        // L'artisan n'a pas cette action : la retirer ne change rien.
        $this->actingAs($actor)
            ->postJson('/api/v1/admin/roles-permissions/revoke', ['role' => 'artisan', 'permission' => 'mission.create'])
            ->assertOk();

        $this->assertSame(0, AdminActivityLog::where('action', 'role_permission.revoked')->count());
    }

    // ── I4. Rôles sans effet retirés ────────────────────────────────────────

    public function test_roles_without_effect_are_refused(): void
    {
        $actor = $this->adminWith(['admin.full-access']);

        foreach (['admin', 'driver'] as $role) {
            $this->actingAs($actor)
                ->postJson('/api/v1/admin/roles-permissions/assign', ['role' => $role, 'permission' => 'mission.create'])
                ->assertStatus(422)
                ->assertJsonValidationErrors('role');
        }

        $this->assertSame(0, DB::table('permission_role')->whereIn('role', ['admin', 'driver'])
            ->where('permission_id', Permission::where('name', 'mission.create')->value('id'))
            ->count());
    }

    public function test_a_backoffice_capability_is_never_assigned_to_an_application_role(): void
    {
        $actor = $this->adminWith(['admin.full-access']);

        $this->actingAs($actor)
            ->postJson('/api/v1/admin/roles-permissions/assign', ['role' => 'client', 'permission' => 'admin.full-access'])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('permission_role')->where('role', 'client')
            ->where('permission_id', Permission::where('name', 'admin.full-access')->value('id'))
            ->count());
    }

    public function test_the_screen_no_longer_lists_the_admin_role(): void
    {
        $actor = $this->adminWith(['admin.full-access']);

        $this->actingAs($actor)->get('/admin/roles-permissions')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rolesPermissions.client')
            ->has('rolesPermissions.livreur')
            ->missing('rolesPermissions.admin')
            ->where('admins.0.is_self', true));

        $this->actingAs($actor)->getJson('/api/v1/admin/roles-permissions')
            ->assertOk()
            ->assertJsonMissingPath('data.admin')
            ->assertJsonStructure(['data' => ['client', 'artisan', 'fournisseur', 'referent', 'livreur']]);
    }
}
