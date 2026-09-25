<?php

use App\Models\User;
use App\Services\AntiBotService;
use Illuminate\Support\Facades\Hash;

test('admin can authenticate using admin@prosartisan.com alias when registered as admin@prosartisan.ci', function () {
    $admin = User::factory()->create([
        'name' => 'Administrateur Test',
        'email' => 'admin@prosartisan.ci',
        'phone' => '+2250000000000',
        'password' => Hash::make('MonMotDePasse123'),
        'role' => 'admin',
        'kyc_status' => 'actif',
    ]);

    $antiBot = app(AntiBotService::class);
    $challenge = $antiBot->generateChallenge('admin_login');

    $response = $this->post('/admin/login', [
        'identifier' => 'admin@prosartisan.com',
        'password' => 'MonMotDePasse123',
        '_bot_token' => $challenge['token'],
        '_bot_answer' => (string) $challenge['answer'],
    ]);

    $response->assertRedirect(route('admin.login.verify-2fa'));
    expect(session('admin_2fa_user_id'))->toBe($admin->id);
});

test('admin can authenticate directly with admin@prosartisan.com email', function () {
    $admin = User::factory()->create([
        'name' => 'Administrateur Test .com',
        'email' => 'admin@prosartisan.com',
        'phone' => '+2250000000001',
        'password' => Hash::make('AutrePasse456'),
        'role' => 'admin',
        'kyc_status' => 'actif',
    ]);

    $antiBot = app(AntiBotService::class);
    $challenge = $antiBot->generateChallenge('admin_login');

    $response = $this->post('/admin/login', [
        'identifier' => 'admin@prosartisan.com',
        'password' => 'AutrePasse456',
        '_bot_token' => $challenge['token'],
        '_bot_answer' => (string) $challenge['answer'],
    ]);

    $response->assertRedirect(route('admin.login.verify-2fa'));
    expect(session('admin_2fa_user_id'))->toBe($admin->id);
});

test('admin reset password command updates password and resets 2fa', function () {
    $admin = User::factory()->create([
        'email' => 'admin@prosartisan.ci',
        'password' => Hash::make('ancienPasse'),
        'role' => 'admin',
        'google_2fa_secret' => 'ANCIEN_SECRET_2FA',
    ]);

    $this->artisan('admin:reset-password', [
        'identifier' => 'admin@prosartisan.com',
        'password' => 'NouveauPasse789',
        '--reset-2fa' => true,
    ])->assertSuccessful();

    $admin->refresh();
    expect(Hash::check('NouveauPasse789', $admin->password))->toBeTrue();
    expect($admin->google_2fa_secret)->toBeNull();
});
