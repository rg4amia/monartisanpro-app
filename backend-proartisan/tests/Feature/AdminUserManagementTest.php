<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $response = $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Nouveau Client',
                'phone' => '+2250102030405',
                'email' => 'client@test.com',
                'role' => 'client',
                'password' => 'secret123',
                'kyc_status' => 'en_attente',
                'account_status' => 'actif',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'name' => 'Nouveau Client',
            'phone' => '+2250102030405',
            'email' => 'client@test.com',
            'role' => 'client',
            'kyc_status' => 'en_attente',
            'account_status' => 'actif',
        ]);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $user = User::factory()->create([
            'name' => 'Ancien Nom',
            'phone' => '+225000000000',
            'email' => 'ancien@test.com',
            'role' => 'client',
            'kyc_status' => 'en_attente',
            'account_status' => 'actif',
        ]);

        $response = $this->actingAs($admin)
            ->put("/admin/users/{$user->id}", [
                'name' => 'Nom Modifie',
                'phone' => '+225111111111',
                'email' => 'modifie@test.com',
                'role' => 'artisan',
                'kyc_status' => 'actif',
                'account_status' => 'suspendu',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nom Modifie',
            'phone' => '+225111111111',
            'email' => 'modifie@test.com',
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'account_status' => 'suspendu',
        ]);
    }

    public function test_admin_can_toggle_user_status_with_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $user = User::factory()->create([
            'role' => 'client',
            'account_status' => 'actif',
        ]);

        $response = $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/toggle-status", [
                'account_status' => 'suspendu',
                'account_status_reason' => 'Comportement suspect',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_status' => 'suspendu',
            'account_status_reason' => 'Comportement suspect',
        ]);
        $this->assertNotNull($user->fresh()->blocked_at);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $user = User::factory()->create([
            'role' => 'client',
        ]);

        $response = $this->actingAs($admin)
            ->delete("/admin/users/{$user->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted($user);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $response = $this->actingAs($admin)
            ->delete("/admin/users/{$admin->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }
}
