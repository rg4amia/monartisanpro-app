<?php

use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    // Nettoyer les utilisateurs avant chaque test
    User::truncate();
    Otp::truncate();
});

test('request reset phone lost fails with invalid credentials', function () {
    $user = User::factory()->create([
        'phone' => '+2250707262811',
        'name' => 'Jean Dupont',
        'role' => 'artisan',
    ]);

    $response = $this->postJson('/api/v1/auth/reset-phone-request', [
        'old_phone' => '+2250707262811',
        'new_phone' => '+2250707262812',
        'name' => 'Jean Martin', // Nom erroné
        'role' => 'artisan',
    ]);

    $response->assertStatus(404);
});

test('request reset phone lost sends OTP when credentials match', function () {
    $user = User::factory()->create([
        'phone' => '+2250707262811',
        'name' => 'Jean Dupont',
        'role' => 'artisan',
    ]);

    $response = $this->postJson('/api/v1/auth/reset-phone-request', [
        'old_phone' => '+2250707262811',
        'new_phone' => '+2250707262812',
        'name' => 'Jean Dupont',
        'role' => 'artisan',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('otps', [
        'phone' => '+2250707262812',
    ]);
});

test('confirm reset phone lost updates phone number and logs in', function () {
    $user = User::factory()->create([
        'phone' => '+2250707262811',
        'name' => 'Jean Dupont',
        'role' => 'artisan',
    ]);

    // Générer OTP pour le nouveau numéro
    $otpCode = '1234';
    Otp::create([
        'phone' => '+2250707262812',
        'code' => $otpCode,
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->postJson('/api/v1/auth/reset-phone-confirm', [
        'old_phone' => '+2250707262811',
        'new_phone' => '+2250707262812',
        'name' => 'Jean Dupont',
        'role' => 'artisan',
        'otp' => $otpCode,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['success', 'token', 'user']);
    
    // Vérifier en base
    $user->refresh();
    expect($user->phone)->toBe('+2250707262812');
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
