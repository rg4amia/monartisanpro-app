<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier C5 (P1-8) — export CSV des listes du backoffice.
 */
class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    public function test_users_export_streams_a_csv_with_header_and_rows(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create(['role' => 'client']);

        $response = $this->actingAs($admin)->get('/admin/exports/users');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('sep=;', $body);
        $this->assertStringContainsString('Score ProsArtisan', $body);
        // 1 admin + 3 clients = 4 lignes de données (hors BOM, sep=; et en-tête).
        $this->assertSame(4, $this->dataRowCount($body));
    }

    public function test_export_respects_list_filters(): void
    {
        $admin = $this->admin();
        User::factory()->count(4)->create(['role' => 'client']);
        User::factory()->count(2)->create(['role' => 'artisan', 'name' => 'ArtisanExportTest']);

        $body = $this->actingAs($admin)->get('/admin/exports/users?role_users=artisan')->streamedContent();

        $this->assertStringContainsString('ArtisanExportTest', $body);
        $this->assertSame(2, $this->dataRowCount($body));
    }

    private function dataRowCount(string $csv): int
    {
        $lines = preg_split('/\r\n|\n/', trim($csv));

        // Retire la ligne `sep=;` et l'en-tête.
        return max(0, count($lines) - 2);
    }

    public function test_transactions_export_works_and_is_audited(): void
    {
        $admin = $this->admin();
        Transaction::create([
            'type' => 'acompte', 'montant' => 12345, 'wallet_source' => 'a', 'wallet_dest' => 'b',
            'provider' => 'wave', 'statut' => 'confirme', 'reference_externe' => 'REF-EXPORT',
        ]);

        $body = $this->actingAs($admin)->get('/admin/exports/transactions')->streamedContent();

        $this->assertStringContainsString('REF-EXPORT', $body);
        $this->assertStringContainsString('12345', $body);

        $log = AdminActivityLog::where('action', 'export.generated')->first();
        $this->assertNotNull($log);
        $this->assertSame('transactions', $log->context['resource']);
    }

    public function test_unknown_resource_returns_404(): void
    {
        $this->actingAs($this->admin())->get('/admin/exports/secrets')->assertNotFound();
    }

    public function test_guest_cannot_export(): void
    {
        $this->get('/admin/exports/users')->assertRedirect('/admin/login');
    }
}
