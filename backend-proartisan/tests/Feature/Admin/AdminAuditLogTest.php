<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier C3 (P0-4 + P0-5) — journal d'audit admin et anti-bruteforce du login.
 */
class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    public function test_sensitive_service_action_writes_an_attributed_audit_row(): void
    {
        $admin = $this->admin();
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_frozen' => false]);

        $this->actingAs($admin);
        app(AdminUserService::class)->toggleScoreFreeze($artisan);

        $log = AdminActivityLog::where('action', 'user.score_freeze_toggled')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame($admin->name, $log->admin_name);
        $this->assertSame(User::class, $log->subject_type);
        $this->assertSame($artisan->id, $log->subject_id);
        $this->assertTrue($log->context['frozen']);
    }

    public function test_kyc_review_is_audited(): void
    {
        $admin = $this->admin();
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'en_attente']);

        $this->actingAs($admin)
            ->post("/admin/kyc/{$artisan->id}/review", ['decision' => 'approuve'])
            ->assertRedirect();

        $log = AdminActivityLog::where('action', 'kyc.reviewed')->first();
        $this->assertNotNull($log);
        $this->assertSame($artisan->id, $log->subject_id);
        $this->assertSame('approuve', $log->context['decision']);
    }

    public function test_audit_page_renders_and_filters_by_action(): void
    {
        $admin = $this->admin();
        AdminActivityLog::create(['admin_id' => $admin->id, 'admin_name' => $admin->name, 'action' => 'user.deleted', 'created_at' => now()]);
        AdminActivityLog::create(['admin_id' => $admin->id, 'admin_name' => $admin->name, 'action' => 'setting.updated', 'created_at' => now()]);

        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/audit-logs')
                ->has('auditLogs.data', 2)
                ->has('auditActions', 2));

        $this->actingAs($admin)->get('/admin/audit-logs?action_audit=user.deleted')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('auditLogs.data', 1));
    }

    public function test_failed_admin_login_is_rate_limited_and_audited(): void
    {
        User::factory()->create([
            'role' => 'admin',
            'email' => 'boss@prosartisan.ci',
            'password' => Hash::make('correct-horse'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['identifier' => 'boss@prosartisan.ci', 'password' => 'wrongpass'])
                ->assertSessionHasErrors('identifier');
        }

        // 6e tentative : bloquée par le throttle, même avec le bon mot de passe.
        $blocked = $this->post('/admin/login', ['identifier' => 'boss@prosartisan.ci', 'password' => 'correct-horse']);
        $blocked->assertSessionHasErrors('identifier');
        $this->assertGuest();

        $this->assertGreaterThanOrEqual(5, AdminActivityLog::where('action', 'admin.login.failed')->count());
    }

    public function test_valid_credentials_below_threshold_still_reach_2fa(): void
    {
        User::factory()->create([
            'role' => 'admin',
            'kyc_status' => 'actif',
            'email' => 'ok@prosartisan.ci',
            'password' => Hash::make('correct-horse'),
            'google_2fa_secret' => null,
        ]);

        // 3 échecs puis un succès (les identifiants sont bons, on s'arrête avant la 2FA).
        for ($i = 0; $i < 3; $i++) {
            $this->post('/admin/login', ['identifier' => 'ok@prosartisan.ci', 'password' => 'nope-nope']);
        }

        $this->post('/admin/login', ['identifier' => 'ok@prosartisan.ci', 'password' => 'correct-horse'])
            ->assertRedirect(route('admin.login.verify-2fa'));
    }
}
