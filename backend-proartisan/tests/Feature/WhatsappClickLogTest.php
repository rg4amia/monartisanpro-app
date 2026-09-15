<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bouton WhatsApp "click-to-chat" du front office + module de suivi backoffice.
 */
class WhatsappClickLogTest extends TestCase
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

    public function test_public_endpoint_logs_a_whatsapp_click_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/vitrine/whatsapp-click', [
            'page' => '/artisans',
            'source' => 'floating_button',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('whatsapp_click_logs', [
            'page' => '/artisans',
            'source' => 'floating_button',
        ]);
    }

    public function test_public_endpoint_defaults_source_when_omitted(): void
    {
        $this->postJson('/api/v1/vitrine/whatsapp-click', ['page' => '/'])->assertOk();

        $this->assertDatabaseHas('whatsapp_click_logs', [
            'page' => '/',
            'source' => 'floating_button',
        ]);
    }

    public function test_whatsapp_admin_page_requires_manage_capability(): void
    {
        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->get('/admin/whatsapp')
            ->assertForbidden();

        $this->actingAs($this->restrictedAdmin(['admin.whatsapp.manage']))
            ->get('/admin/whatsapp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/whatsapp')->has('whatsappClickStats'));
    }

    public function test_admin_can_update_whatsapp_widget_settings(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/vitrine/settings', [
            'whatsapp_widget_enabled' => '1',
            'whatsapp_widget_phone' => '+2250160606183',
            'whatsapp_widget_message' => 'Bonjour, je souhaite un devis.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vitrine_settings', ['cle' => 'whatsapp_widget_phone', 'valeur' => '+2250160606183']);
        $this->assertDatabaseHas('vitrine_settings', ['cle' => 'whatsapp_widget_message', 'valeur' => 'Bonjour, je souhaite un devis.']);

        $this->getJson('/api/v1/vitrine/settings')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_widget_phone', '+2250160606183');
    }
}
