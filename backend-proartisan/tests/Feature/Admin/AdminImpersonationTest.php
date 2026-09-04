<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chantier C7 — usurpation de session (« Se connecter en tant que »).
 */
class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    /** @param array<int, string> $capabilities */
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

    public function test_impersonation_requires_the_capability(): void
    {
        $target = User::factory()->create(['role' => 'client']);

        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->post("/admin/users/{$target->id}/impersonate")
            ->assertForbidden();
    }

    public function test_admin_can_impersonate_a_client_then_return(): void
    {
        $admin = $this->superAdmin();
        $client = User::factory()->create(['role' => 'client', 'name' => 'Awa Traoré']);

        $this->actingAs($admin)
            ->post("/admin/users/{$client->id}/impersonate")
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($client);
        $this->assertEquals($admin->id, session('impersonator_id'));
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'user.impersonation_started',
            'subject_id' => $client->id,
        ]);

        $this->post('/admin/stop-impersonating')->assertRedirect(route('admin.users'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'user.impersonation_stopped']);
    }

    public function test_cannot_impersonate_another_admin_or_self(): void
    {
        $admin = $this->superAdmin();
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post("/admin/users/{$otherAdmin->id}/impersonate")->assertForbidden();
        $this->actingAs($admin)->post("/admin/users/{$admin->id}/impersonate")->assertStatus(400);
    }

    public function test_cannot_impersonate_an_anonymized_account(): void
    {
        $admin = $this->superAdmin();
        $ghost = User::factory()->create(['role' => 'client', 'anonymized_at' => now()]);

        $this->actingAs($admin)->post("/admin/users/{$ghost->id}/impersonate")->assertForbidden();
    }

    public function test_stop_without_active_impersonation_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/admin/stop-impersonating')
            ->assertStatus(400);
    }

    public function test_cannot_chain_a_second_impersonation(): void
    {
        $admin = $this->superAdmin();
        $a = User::factory()->create(['role' => 'client']);
        $b = User::factory()->create(['role' => 'artisan']);

        $this->actingAs($admin)->post("/admin/users/{$a->id}/impersonate")->assertRedirect('/');

        // La session est désormais celle d'un non-admin : « admin.only » bloque toute
        // nouvelle usurpation ; il faut d'abord revenir à son compte.
        $this->post("/admin/users/{$b->id}/impersonate")->assertForbidden();
        $this->assertAuthenticatedAs($a);
    }
}
