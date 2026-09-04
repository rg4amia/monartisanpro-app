<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chantier C6 (P2-10) — permissions fines du backoffice admin.
 */
class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    /** Admin « super » : aucune capacité affectée => accès total. */
    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    /**
     * Admin restreint aux capacités transmises.
     *
     * @param  array<int, string>  $capabilities
     */
    private function restrictedAdmin(array $capabilities): User
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

    public function test_super_admin_reaches_every_section(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/admin/kyc')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/transactions')->assertOk();
        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk();
        $this->actingAs($admin)->get('/admin/roles-permissions')->assertOk();
    }

    public function test_restricted_admin_is_denied_sections_outside_scope(): void
    {
        $admin = $this->restrictedAdmin(['admin.kyc.view']);

        $this->actingAs($admin)->get('/admin/kyc')->assertOk();
        $this->actingAs($admin)->get('/admin/transactions')->assertForbidden();
        $this->actingAs($admin)->get('/admin/users')->assertForbidden();
        $this->actingAs($admin)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_full_access_sentinel_grants_everything(): void
    {
        $admin = $this->restrictedAdmin(['admin.full-access']);

        $this->actingAs($admin)->get('/admin/transactions')->assertOk();
        $this->actingAs($admin)->get('/admin/roles-permissions')->assertOk();
    }

    public function test_view_only_admin_cannot_bulk_review_kyc(): void
    {
        $admin = $this->restrictedAdmin(['admin.kyc.view']);
        $pending = User::factory()->create(['role' => 'client', 'kyc_status' => 'en_attente']);

        $this->actingAs($admin)->post('/admin/kyc/bulk-review', [
            'user_ids' => [$pending->id],
            'decision' => 'approuve',
        ])->assertForbidden();

        $this->assertSame('en_attente', $pending->refresh()->kyc_status);
    }

    public function test_admin_with_review_capability_can_bulk_review_kyc(): void
    {
        $admin = $this->restrictedAdmin(['admin.kyc.view', 'admin.kyc.review']);
        $pending = User::factory()->count(2)->create(['role' => 'client', 'kyc_status' => 'en_attente']);

        $this->actingAs($admin)->post('/admin/kyc/bulk-review', [
            'user_ids' => $pending->pluck('id')->all(),
            'decision' => 'approuve',
        ])->assertRedirect();

        foreach ($pending as $user) {
            $this->assertSame('actif', $user->refresh()->kyc_status);
        }
    }

    public function test_admin_without_delete_capability_cannot_delete_users(): void
    {
        $admin = $this->restrictedAdmin(['admin.users.view', 'admin.users.manage']);
        $target = User::factory()->create(['role' => 'client']);

        $this->actingAs($admin)->delete("/admin/users/{$target->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
    }

    public function test_sync_admin_permissions_requires_roles_manage_capability(): void
    {
        $admin = $this->restrictedAdmin(['admin.kyc.view']);
        $target = $this->superAdmin();

        $this->actingAs($admin)->post("/admin/admins/{$target->id}/permissions", [
            'capabilities' => ['admin.kyc.view'],
        ])->assertForbidden();
    }

    public function test_sync_admin_permissions_updates_pivot_and_audits(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $this->actingAs($admin)->post("/admin/admins/{$target->id}/permissions", [
            'capabilities' => ['admin.kyc.view', 'admin.kyc.review'],
        ])->assertRedirect();

        $this->assertSame(2, DB::table('admin_permission_user')->where('user_id', $target->id)->count());
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'admin.permissions_updated',
            'subject_id' => $target->id,
        ]);

        $target->refresh();
        $this->assertTrue($target->adminCan('admin.kyc.review'));
        $this->assertFalse($target->adminCan('admin.transactions.view'));
    }

    public function test_permission_seeder_grants_full_access_to_every_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $this->seed(PermissionSeeder::class);

        $fullAccessId = Permission::where('name', 'admin.full-access')->value('id');
        $this->assertDatabaseHas('admin_permission_user', [
            'user_id' => $admin->id,
            'permission_id' => $fullAccessId,
        ]);
        $this->assertSame(['*'], app(AdminPermissionService::class)->capabilitiesFor($admin->fresh()));
    }

    public function test_protected_super_admin_keeps_full_access_despite_a_restricted_pivot(): void
    {
        config(['prosartisan.super_admins' => ['boss@prosartisan.ci']]);
        $boss = User::factory()->create(['role' => 'admin', 'email' => 'boss@prosartisan.ci', 'kyc_status' => 'actif']);

        // Pivot volontairement restreint : doit être ignoré pour un super admin protégé.
        DB::table('admin_permission_user')->insert([
            'user_id' => $boss->id,
            'permission_id' => Permission::where('name', 'admin.kyc.view')->value('id'),
            'created_at' => now(),
        ]);

        $service = app(AdminPermissionService::class);
        $this->assertTrue($service->isProtectedSuperAdmin($boss));
        $this->assertSame(['*'], $service->capabilitiesFor($boss));
        $this->assertTrue($boss->adminCan('admin.transactions.view'));

        $this->actingAs($boss)->get('/admin/transactions')->assertOk();
    }

    public function test_protected_super_admin_cannot_be_restricted_via_sync(): void
    {
        config(['prosartisan.super_admins' => ['boss@prosartisan.ci']]);
        $boss = User::factory()->create(['role' => 'admin', 'email' => 'boss@prosartisan.ci', 'kyc_status' => 'actif']);
        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->post("/admin/admins/{$boss->id}/permissions", ['capabilities' => ['admin.kyc.view']])
            ->assertSessionHas('error');

        $this->assertSame(['*'], app(AdminPermissionService::class)->capabilitiesFor($boss->fresh()));
    }

    public function test_grant_full_access_command_restores_a_locked_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'locked@prosartisan.ci', 'kyc_status' => 'actif']);
        DB::table('admin_permission_user')->insert([
            'user_id' => $admin->id,
            'permission_id' => Permission::where('name', 'admin.kyc.view')->value('id'),
            'created_at' => now(),
        ]);

        $this->artisan('admin:full-access', ['email' => 'locked@prosartisan.ci'])->assertExitCode(0);

        $this->assertSame(['*'], app(AdminPermissionService::class)->capabilitiesFor($admin->fresh()));
    }

    public function test_non_admin_capabilities_are_not_short_circuited_for_regular_roles(): void
    {
        $referent = User::factory()->create(['role' => 'referent', 'kyc_status' => 'actif']);

        // admin.only bloque déjà l'accès, mais la capacité fine ne doit jamais être accordée.
        $this->assertFalse($referent->adminCan('admin.litiges.arbitrate'));
    }
}
