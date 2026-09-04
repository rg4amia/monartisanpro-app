<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier C5 (P1-9) — actions groupées du backoffice.
 */
class AdminBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    public function test_bulk_kyc_approval_processes_all_selected_pending_files(): void
    {
        $admin = $this->admin();
        $pending = User::factory()->count(3)->create(['role' => 'client', 'kyc_status' => 'en_attente']);
        $alreadyActive = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($admin)->post('/admin/kyc/bulk-review', [
            'user_ids' => $pending->pluck('id')->push($alreadyActive->id)->all(),
            'decision' => 'approuve',
        ])->assertRedirect();

        foreach ($pending as $user) {
            $this->assertSame('actif', $user->refresh()->kyc_status);
        }
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'kyc.bulk_reviewed']);
        // reviewKyc journalise aussi chaque dossier individuellement.
        $this->assertSame(3, AdminActivityLog::where('action', 'kyc.reviewed')->count());
    }

    public function test_bulk_kyc_reject_requires_a_reason(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'client', 'kyc_status' => 'en_attente']);

        $this->actingAs($admin)->post('/admin/kyc/bulk-review', [
            'user_ids' => [$user->id],
            'decision' => 'rejete',
        ])->assertSessionHasErrors('rejection_reason');

        $this->assertSame('en_attente', $user->refresh()->kyc_status);
    }

    public function test_bulk_suspend_updates_accounts_and_skips_the_acting_admin(): void
    {
        $admin = $this->admin();
        $targets = User::factory()->count(3)->create(['role' => 'client', 'account_status' => 'actif']);

        $this->actingAs($admin)->post('/admin/users/bulk-status', [
            'user_ids' => $targets->pluck('id')->push($admin->id)->all(),
            'account_status' => 'suspendu',
            'account_status_reason' => 'Campagne anti-fraude',
        ])->assertRedirect();

        foreach ($targets as $user) {
            $user->refresh();
            $this->assertSame('suspendu', $user->account_status);
            $this->assertNotNull($user->blocked_at);
        }
        // L'admin qui agit n'est jamais suspendu par le lot.
        $this->assertSame('actif', $admin->refresh()->account_status);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'user.bulk_status_changed']);
    }

    public function test_bulk_status_rejects_empty_selection(): void
    {
        $this->actingAs($this->admin())->post('/admin/users/bulk-status', [
            'user_ids' => [],
            'account_status' => 'suspendu',
        ])->assertSessionHasErrors('user_ids');
    }
}
