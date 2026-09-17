<?php

use App\Models\User;
use Illuminate\Database\QueryException;

// ── Unicité du numéro de téléphone à l'inscription ───────────────────────────
// Deux garde-fous coexistent : l'index UNIQUE en base (0001_01_01_000000_
// create_users_table.php) et la closure de RegisterRequest, qui n'autorise le
// passage que si le compte existant n'est pas encore complet (name/role null,
// cas du « stub » créé par verify-otp). Un vrai compte déjà inscrit doit
// bloquer une seconde inscription sur le même numéro.

function registerPayload(string $phone, array $overrides = []): array
{
    return array_merge([
        'phone' => $phone,
        'name' => 'Awa Traoré',
        'role' => 'client',
        'cgu_accepted' => true,
    ], $overrides);
}

test('il rejette l’inscription si le numéro appartient déjà à un compte complet', function () {
    $phone = '+2250700000001';
    User::factory()->create(['phone' => $phone, 'name' => 'Compte existant', 'role' => 'artisan']);

    $response = $this->postJson('/api/v1/auth/register', registerPayload($phone));

    $response->assertStatus(422)
        ->assertJsonValidationErrors('phone');

    expect($response->json('errors.phone.0'))->toBe('Ce numéro de téléphone est déjà associé à un compte.');
    expect(User::where('phone', $phone)->count())->toBe(1);
});

test('il autorise la complétion d’un compte stub créé par verify-otp sur le même numéro', function () {
    $phone = '+2250700000002';
    // Stub tel que créé par AuthService::findOrCreateByPhone lors de verify-otp :
    // seul le téléphone est renseigné, name et role restent null.
    $stub = User::factory()->create(['phone' => $phone, 'name' => null, 'role' => null]);

    $response = $this->postJson('/api/v1/auth/register', registerPayload($phone));

    $response->assertOk()->assertJsonPath('success', true);

    expect(User::where('phone', $phone)->count())->toBe(1);
    $stub->refresh();
    expect($stub->name)->toBe('Awa Traoré');
    expect($stub->role)->toBe('client');
});

test('la contrainte UNIQUE en base empêche tout doublon de numéro, même hors validation applicative', function () {
    $phone = '+2250700000003';
    User::factory()->create(['phone' => $phone]);

    expect(fn () => User::factory()->create(['phone' => $phone]))
        ->toThrow(QueryException::class);

    expect(User::where('phone', $phone)->count())->toBe(1);
});
