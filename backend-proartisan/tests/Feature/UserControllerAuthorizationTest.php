<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UserController::update()/setRole() appelaient `$this->authorize('update', $user)`
 * en commentaire (le contrôleur de base n'inclut pas AuthorizesRequests) : la
 * vérification n'a donc jamais été exécutée, permettant à n'importe quel compte
 * connecté de modifier le profil — ou le rôle — de n'importe quel autre utilisateur.
 */
class UserControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_update_another_users_profile(): void
    {
        $attacker = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $victim = User::factory()->create(['role' => 'client', 'name' => 'Victime']);

        $this->actingAs($attacker)
            ->putJson("/api/v1/users/{$victim->id}", [
                'name' => 'Profil modifié par un tiers',
            ])
            ->assertStatus(403);

        $this->assertSame('Victime', $victim->fresh()->name);
    }

    public function test_a_user_can_update_their_own_profile(): void
    {
        $user = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($user)
            ->putJson("/api/v1/users/{$user->id}", [
                'name' => 'Nouveau nom',
            ])
            ->assertOk();

        $this->assertSame('Nouveau nom', $user->fresh()->name);
    }

    public function test_an_admin_can_update_another_users_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'client', 'name' => 'Ancien nom']);

        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->id}", [
                'name' => 'Corrigé par un admin',
            ])
            ->assertOk();

        $this->assertSame('Corrigé par un admin', $user->fresh()->name);
    }

    public function test_a_user_cannot_change_another_users_role(): void
    {
        $attacker = User::factory()->create(['role' => 'client']);
        $victim = User::factory()->create(['role' => 'client']);

        $this->actingAs($attacker)
            ->putJson("/api/v1/users/{$victim->id}/role", [
                'role' => 'artisan',
            ])
            ->assertStatus(403);

        $this->assertSame('client', $victim->fresh()->role);
    }
}
