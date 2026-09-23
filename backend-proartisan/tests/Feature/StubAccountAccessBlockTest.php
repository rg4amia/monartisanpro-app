<?php

use App\Models\Setting;
use App\Services\AuthService;

/**
 * Blocage d'accès par rôle (« nouveaux » / « anciens ») face à un compte
 * « coquille » : numéro ayant validé son OTP sans terminer l'inscription.
 *
 * En production (MariaDB), `users.role` est un ENUM NOT NULL sans défaut : le
 * compte coquille reçoit implicitement le rôle 'client', et non NULL. La
 * détection « nouvel utilisateur » qui exigeait `role === null` le classait
 * donc à tort parmi les anciens. Ce test ne révèle le défaut que sous MariaDB
 * (job CI `tests-mariadb`) : SQLite, lui, laisse le rôle à NULL.
 */
beforeEach(function () {
    $this->phone = '+2250700099001';
    app(AuthService::class)->findOrCreateByPhone($this->phone);
});

test('un compte coquille est bloqué quand les nouveaux inscrits sont bloqués', function () {
    Setting::updateOrCreate(['key' => 'block_client'], ['value' => 'new']);

    $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone, 'role' => 'client'])
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('un compte coquille n\'est pas bloqué quand seuls les anciens le sont', function () {
    Setting::updateOrCreate(['key' => 'block_client'], ['value' => 'old']);

    $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone, 'role' => 'client'])
        ->assertStatus(200);
});
