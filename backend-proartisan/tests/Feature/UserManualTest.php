<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Manuel d'utilisation consulté et téléchargé depuis le backoffice. Source
 * unique : `docs/produit/manuel-utilisation.html`.
 */
class UserManualTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_le_manuel_du_depot_est_bien_la_source_servie(): void
    {
        $path = config('prosartisan.user_manual_path');

        $this->assertFileExists($path);
        $this->assertStringContainsString('Manuel d\'utilisation ProsArtisan', file_get_contents($path));
    }

    public function test_tout_administrateur_consulte_le_manuel(): void
    {
        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->get('/admin/manuel')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/manual')
                ->where('userManual.available', true)
                ->where('userManual.html', fn ($html) => str_contains((string) $html, 'Espace juré')));
    }

    public function test_le_manuel_se_telecharge_en_piece_jointe(): void
    {
        $response = $this->actingAs($this->restrictedAdmin([]))
            ->get('/admin/manuel/telecharger')
            ->assertOk();

        $this->assertStringStartsWith('attachment; filename="manuel-prosartisan-', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<!doctype html>', $response->getContent());
    }

    public function test_le_manuel_s_ouvre_en_pleine_page(): void
    {
        $this->actingAs($this->restrictedAdmin([]))
            ->get('/admin/manuel/document')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Glossaire');
    }

    public function test_le_manuel_est_reserve_au_backoffice(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->get('/admin/manuel/telecharger')->assertRedirect();
        $this->actingAs($client)->get('/admin/manuel/telecharger')->assertForbidden();
    }

    public function test_un_manuel_absent_est_signale_sans_page_vide(): void
    {
        config(['prosartisan.user_manual_path' => storage_path('framework/testing/absent.html')]);
        $admin = $this->restrictedAdmin([]);

        $this->actingAs($admin)
            ->get('/admin/manuel')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('userManual.available', false)->where('userManual.html', null));

        $this->actingAs($admin)->get('/admin/manuel/telecharger')->assertNotFound();
    }
}
