<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\ScoreLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminObservabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Chantier C7 (P2-12) — observabilité + alerte Telegram.
 */
class AdminObservabilityTest extends TestCase
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

    private function seedFailedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => "RuntimeException: boom\n#0 /app",
            'failed_at' => now(),
        ]);
    }

    public function test_observability_page_requires_view_capability(): void
    {
        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->get('/admin/observability')
            ->assertForbidden();

        $this->actingAs($this->restrictedAdmin(['admin.observability.view']))
            ->get('/admin/observability')
            ->assertOk();
    }

    public function test_snapshot_aggregates_the_four_critical_signals(): void
    {
        $this->seedFailedJob();
        Transaction::create([
            'type' => 'acompte',
            'montant' => 5000,
            'wallet_source' => 'client_wallet',
            'wallet_dest' => 'escrow_wallet',
            'provider' => 'wave',
            'statut' => 'echoue',
            'error_message' => 'Webhook timeout',
        ]);
        $fraudster = User::factory()->create(['role' => 'fournisseur']);
        ScoreLedgerEntry::create([
            'user_id' => $fraudster->id,
            'event_type' => 'fraude_gps_tentative',
            'points' => -50,
            'credibility_factor' => 1,
            'description' => 'Scan hors zone',
        ]);
        User::factory()->create(['role' => 'client']); // bruit

        $snapshot = app(AdminObservabilityService::class)->snapshot();

        $this->assertSame(1, $snapshot['queue']['failed']);
        $this->assertSame(1, $snapshot['payments']['failed_24h']);
        $this->assertSame(1, $snapshot['fraud']['gps_attempts_7d']);
        $this->assertSame('Webhook timeout', $snapshot['payments']['recent'][0]['error']);
    }

    public function test_retry_failed_jobs_requires_manage_capability(): void
    {
        $this->actingAs($this->restrictedAdmin(['admin.observability.view']))
            ->post('/admin/observability/retry-failed-jobs')
            ->assertForbidden();
    }

    public function test_retry_failed_jobs_is_audited(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/admin/observability/retry-failed-jobs')
            ->assertRedirect();

        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'observability.jobs_retried']);
    }

    public function test_health_check_command_alerts_telegram_when_a_signal_is_present(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '123',
        ]);
        $this->seedFailedJob();

        $this->artisan('admin:health-check')->assertExitCode(0);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org/bottest-token/sendMessage'));
    }

    public function test_health_check_command_stays_silent_without_signal(): void
    {
        Http::fake();
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '123',
        ]);

        $this->artisan('admin:health-check')->assertExitCode(0);

        Http::assertNothingSent();
    }
}
