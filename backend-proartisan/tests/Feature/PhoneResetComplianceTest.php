<?php

use App\Models\Otp;
use App\Models\User;

test('la récupération « SIM perdue » est fermée, même avec le nom, le rôle et l\'ancien numéro exacts', function () {
    $user = User::factory()->create([
        'phone' => '+2250707262811',
        'name' => 'Jean Dupont',
        'role' => 'artisan',
    ]);

    $body = [
        'old_phone' => '+2250707262811',
        'new_phone' => '+2250707262812',
        'name' => 'Jean Dupont',
        'role' => 'artisan',
    ];

    $this->postJson('/api/v1/auth/reset-phone-request', $body)
        ->assertStatus(410)
        ->assertJsonPath('success', false);
    $this->postJson('/api/v1/auth/reset-phone-confirm', $body + ['otp' => '1234'])
        ->assertStatus(410);

    // Aucun code n'est envoyé au numéro de l'appelant, et le compte garde son numéro.
    expect(Otp::where('phone', '+2250707262812')->count())->toBe(0)
        ->and($user->fresh()->phone)->toBe('+2250707262811');
});

test('logged in user can change phone number via OTP confirmation', function () {
    $user = User::factory()->create([
        'phone' => '+2250707262811',
        'name' => 'Jean Dupont',
        'role' => 'client',
    ]);

    // Étape 1 : Demander le changement de numéro (envoyer OTP)
    $response = $this->actingAs($user)
        ->postJson('/api/v1/auth/change-phone', [
            'new_phone' => '+2250707262813',
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('otps', [
        'phone' => '+2250707262813',
    ]);

    $otp = Otp::where('phone', '+2250707262813')->first();

    // Étape 2 : Confirmer le changement de numéro avec l'OTP
    $confirmResponse = $this->actingAs($user)
        ->postJson('/api/v1/auth/change-phone', [
            'new_phone' => '+2250707262813',
            'otp' => $otp->code,
        ]);

    $confirmResponse->assertStatus(200);

    $user->refresh();
    expect($user->phone)->toBe('+2250707262813');
});
