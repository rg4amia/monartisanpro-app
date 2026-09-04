<?php

namespace Tests\Feature\Admin;

use App\Models\KycDocument;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chantier C6 (P2-11) — conformité RGPD du backoffice.
 */
class AdminGdprTest extends TestCase
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

    public function test_personal_data_requires_rgpd_view_capability(): void
    {
        $target = User::factory()->create(['role' => 'client']);

        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->getJson("/admin/users/{$target->id}/personal-data")
            ->assertForbidden();

        $this->actingAs($this->restrictedAdmin(['admin.rgpd.view']))
            ->getJson("/admin/users/{$target->id}/personal-data")
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'phone', 'cgu_accepted_at', 'anonymized_at'],
                'kyc_documents',
                'evaluations_given',
                'missions_as_client',
                'activity_trace',
            ]);
    }

    public function test_anonymize_requires_rgpd_manage_capability(): void
    {
        $target = User::factory()->create(['role' => 'client']);

        $this->actingAs($this->restrictedAdmin(['admin.rgpd.view']))
            ->post("/admin/users/{$target->id}/anonymize")
            ->assertForbidden();

        $this->assertNull($target->refresh()->anonymized_at);
    }

    public function test_anonymize_scrubs_personal_data_but_keeps_the_account_row(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create([
            'role' => 'client',
            'email' => 'jean@example.com',
            'device_fingerprint' => 'IMEI-123456',
        ]);
        KycDocument::create(['user_id' => $target->id, 'type' => 'cni', 'file_url' => 'x.jpg', 'statut' => 'en_attente']);
        Notification::create(['user_id' => $target->id, 'type' => 'info', 'title' => 'Bonjour', 'body' => 'test']);

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/anonymize")
            ->assertRedirect();

        $target->refresh();
        $this->assertNotNull($target->anonymized_at);
        $this->assertSame($admin->id, $target->anonymized_by);
        $this->assertSame("Utilisateur anonymisé #{$target->id}", $target->name);
        $this->assertNull($target->email);
        $this->assertStringStartsWith('+22599', $target->phone);
        $this->assertNull($target->device_fingerprint);
        $this->assertSame('suspendu', $target->account_status);

        // La ligne compte est conservée (intégrité référentielle), mais les PII annexes sont purgées.
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertSame(0, KycDocument::where('user_id', $target->id)->count());
        $this->assertSame(0, Notification::where('user_id', $target->id)->count());
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'user.anonymized',
            'subject_id' => $target->id,
        ]);
    }

    public function test_anonymize_rejects_second_pass_and_self(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post("/admin/users/{$admin->id}/anonymize")
            ->assertSessionHas('error');
        $this->assertNull($admin->refresh()->anonymized_at);

        $target = User::factory()->create(['role' => 'client']);
        $this->actingAs($admin)->post("/admin/users/{$target->id}/anonymize")->assertRedirect();
        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/anonymize")
            ->assertSessionHas('error');
    }

    public function test_personal_data_export_returns_json_download(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($admin)->get("/admin/users/{$target->id}/personal-data/export");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertSame($target->id, $response->streamedContent() ? json_decode($response->streamedContent(), true)['user']['id'] : null);
    }
}
