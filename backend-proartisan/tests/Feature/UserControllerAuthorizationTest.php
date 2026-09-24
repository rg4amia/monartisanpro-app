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

    public function test_a_user_cannot_change_their_own_role(): void
    {
        $user = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($user)
            ->putJson("/api/v1/users/{$user->id}/role", [
                'role' => 'fournisseur',
            ])
            ->assertForbidden();

        $this->assertSame('client', $user->fresh()->role);
    }

    public function test_account_deletion_anonymizes_without_deleting_the_user_row(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
            'name' => 'Compte à effacer',
            'email' => 'effacement@example.test',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertNotNull($user->anonymized_at);
        $this->assertSame($user->id, $user->anonymized_by);
        $this->assertSame('suspendu', $user->account_status);
        $this->assertNull($user->email);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_authorized_admin_role_change_resets_kyc_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $user->createToken('mobile');

        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->id}/role", ['role' => 'artisan'])
            ->assertOk();

        $user->refresh();
        $this->assertSame('artisan', $user->role);
        $this->assertSame('en_attente', $user->kyc_status);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $admin->id,
            'action' => 'user.role.updated',
            'subject_id' => $user->id,
        ]);
    }

    public function test_admin_cannot_change_an_admin_role_through_the_mobile_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$otherAdmin->id}/role", ['role' => 'client'])
            ->assertForbidden();

        $this->assertSame('admin', $otherAdmin->fresh()->role);
    }
}
