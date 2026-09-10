<?php

namespace Tests\Feature\Admin;

use App\Models\AiUserQuota;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Backoffice — module de gestion des quotas IA par utilisateur mobile.
 */
class AiQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
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

    public function test_admin_can_set_a_per_user_ai_quota_and_it_is_audited(): void
    {
        $admin = $this->restrictedAdmin(['admin.ai.manage']);
        $target = User::factory()->create(['role' => 'artisan']);

        $this->actingAs($admin)
            ->put("/admin/ai-dashboard/quotas/{$target->id}", [
                'daily_limit' => 3,
                'monthly_limit' => null,
                'blocked' => false,
                'note' => 'Consommation surveillée',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_user_quotas', [
            'user_id' => $target->id,
            'daily_limit' => 3,
            'blocked' => false,
            'updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'ai_quota.updated',
            'subject_id' => $target->id,
        ]);
    }

    public function test_clearing_all_constraints_removes_the_override(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'client']);
        AiUserQuota::create(['user_id' => $target->id, 'daily_limit' => 2]);

        $this->actingAs($admin)
            ->put("/admin/ai-dashboard/quotas/{$target->id}", [
                'daily_limit' => null,
                'monthly_limit' => null,
                'blocked' => false,
                'note' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('ai_user_quotas', ['user_id' => $target->id]);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'ai_quota.reset']);
    }

    public function test_quota_route_requires_the_ai_manage_capability(): void
    {
        $admin = $this->restrictedAdmin(['admin.users.view']);
        $target = User::factory()->create(['role' => 'artisan']);

        $this->actingAs($admin)
            ->put("/admin/ai-dashboard/quotas/{$target->id}", ['blocked' => true])
            ->assertForbidden();
    }

    public function test_ai_dashboard_exposes_the_paginated_user_quota_list_with_filters(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => 'artisan', 'name' => 'Awa Traore']);
        User::factory()->create(['role' => 'client', 'name' => 'Koffi Yao']);

        $this->actingAs($admin)
            ->get('/admin/ai-dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/ai-dashboard')
                ->has('aiUserQuotasPage.data'));

        $this->actingAs($admin)
            ->get('/admin/ai-dashboard?role_aiq=artisan')
            ->assertInertia(fn ($page) => $page
                ->where('aiUserQuotasPage.data.0.role', 'artisan')
                ->where('aiUserQuotasPage.total', 1));
    }

    public function test_monthly_user_limit_setting_is_accepted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/ai-dashboard/settings', [
                'daily_user_limit' => 20,
                'monthly_user_limit' => 300,
                'ai_enabled' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_settings', ['key' => 'monthly_user_limit', 'value' => '300']);
    }
}
