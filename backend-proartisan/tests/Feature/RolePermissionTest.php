<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Exécuter le seeder pour chaque test
        $this->seed(PermissionSeeder::class);
    }

    public function test_seeder_populated_permissions_and_roles_mappings(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'mission.create']);
        $this->assertDatabaseHas('permissions', ['name' => 'jcode.scan']);

        $clientPermissionCount = \Illuminate\Support\Facades\DB::table('permission_role')
            ->where('role', 'client')
            ->count();
        $this->assertGreaterThan(0, $clientPermissionCount);
    }

    public function test_has_permissions_trait_resolves_correctly(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $this->assertTrue($client->hasPermissionTo('mission.create'));
        $this->assertFalse($client->hasPermissionTo('jcode.scan'));

        $this->assertFalse($artisan->hasPermissionTo('mission.create'));
        $this->assertTrue($artisan->hasPermissionTo('devis.create'));
    }

    public function test_admin_has_all_permissions(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'kyc_status' => 'actif',
        ]);

        $this->assertTrue($admin->hasPermissionTo('mission.create'));
        $this->assertTrue($admin->hasPermissionTo('jcode.scan'));
        $this->assertTrue($admin->hasPermissionTo('any.random.permission'));
    }

    public function test_admin_can_assign_and_revoke_permissions_dynamically(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'kyc_status' => 'actif',
        ]);

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        // Artisan n'a pas mission.create par défaut
        $this->assertFalse($artisan->hasPermissionTo('mission.create'));

        // Assigner via API
        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/roles-permissions/assign', [
                'role' => 'artisan',
                'permission' => 'mission.create',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Maintenant l'artisan doit l'avoir
        $this->assertTrue($artisan->fresh()->hasPermissionTo('mission.create'));

        // Révoquer via API
        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/roles-permissions/revoke', [
                'role' => 'artisan',
                'permission' => 'mission.create',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // L'artisan ne doit plus l'avoir
        $this->assertFalse($artisan->fresh()->hasPermissionTo('mission.create'));
    }

    public function test_route_restriction_via_can_middleware(): void
    {
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        // Un artisan tente de poster une mission (sécurisé par can:mission.create)
        $response = $this->actingAs($artisan)
            ->postJson('/api/v1/missions', [
                'artisan_id' => 999,
                'category' => 'Maconnerie',
                'description' => 'Test description',
                'lat' => 5.3,
                'lng' => -4.0,
                'location_address' => 'Abidjan',
            ]);

        $response->assertForbidden(); // 403 Action non autorisée
    }

    public function test_admin_can_access_roles_permissions_view(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'kyc_status' => 'actif',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/roles-permissions');

        $response->assertOk();
    }

    public function test_client_cannot_access_roles_permissions_view(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $response = $this->actingAs($client)
            ->get('/admin/roles-permissions');

        $response->assertForbidden();
    }
}
