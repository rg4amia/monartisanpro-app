<?php

use App\Models\AdminActivityLog;
use App\Models\Otp;
use App\Models\User;
use App\Services\Admin\AdminGdprService;
use App\Services\Admin\AdminPermissionService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Chantier 27, lot A — inscription réservée au détenteur du numéro, compte
 * supprimé annoncé sans erreur interne, récupération ouverte aux livreurs,
 * profil d'un tiers modifié par un administrateur habilité seulement, carte
 * CNMCI sur disque privé.
 */

function c27Register(string $phone, array $overrides = []): array
{
    return $overrides + ['phone' => $phone, 'name' => 'Awa Traoré', 'role' => 'client', 'cgu_accepted' => true];
}

/** Valide un code pour ce numéro par la vraie route, comme l'application. */
function c27VerifyCode(string $phone): void
{
    app(OtpService::class)->sendOtp($phone);

    test()->postJson('/api/v1/auth/verify-otp', [
        'phone' => $phone,
        'otp' => Otp::where('phone', $phone)->latest('id')->value('code'),
    ])->assertOk();
}

function c27Admin(?array $capabilities = null): User
{
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

    if ($capabilities !== null) {
        app(AdminPermissionService::class)->sync(
            $admin,
            $capabilities,
            User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']),
        );
    }

    return $admin->fresh();
}

function c27Artisan(array $attributes = []): User
{
    return User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif'] + $attributes);
}

// ── Inscription ─────────────────────────────────────────────────────────────

test('l\'inscription est refusée sans code validé pour ce numéro', function () {
    $this->postJson('/api/v1/auth/register', c27Register('+2250102030405'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone')
        ->assertJsonMissingPath('token');

    expect(User::where('phone', '+2250102030405')->exists())->toBeFalse();
});

test('un code seulement envoyé, ou saisi faux, n\'ouvre pas l\'inscription', function () {
    $phone = '+2250102030406';
    app(OtpService::class)->sendOtp($phone);

    $this->postJson('/api/v1/auth/register', c27Register($phone))->assertStatus(422);

    // Cinq mauvais codes brûlent le code : il est « utilisé », pas validé.
    foreach (range(1, 5) as $n) {
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'otp' => '0000'])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/register', c27Register($phone))->assertStatus(422);
    expect(User::where('phone', $phone)->exists())->toBeFalse();
});

test('le compte inachevé d\'un tiers ne se termine pas sans son code', function () {
    $phone = '+2250102030407';
    c27VerifyCode($phone);
    // Le titulaire a validé son code il y a plus de 30 minutes sans s'inscrire.
    $this->travel(31)->minutes();

    $this->postJson('/api/v1/auth/register', c27Register($phone, ['name' => 'Pirate']))->assertStatus(422);

    expect(User::where('phone', $phone)->value('name'))->toBeNull();
});

test('après validation du code, l\'inscription aboutit et la preuve ne sert qu\'une fois', function () {
    $phone = '+2250102030408';
    c27VerifyCode($phone);

    $this->postJson('/api/v1/auth/register', c27Register($phone, ['role' => 'livreur']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['token']);

    expect(User::where('phone', $phone)->value('role'))->toBe('livreur')
        ->and(app(OtpService::class)->hasRecentVerification($phone))->toBeFalse();
});

test('le code d\'un numéro n\'ouvre pas l\'inscription d\'un autre numéro', function () {
    c27VerifyCode('+2250102030409');

    $this->postJson('/api/v1/auth/register', c27Register('+2250102030410'))->assertStatus(422);
});

// ── Compte supprimé ─────────────────────────────────────────────────────────

test('le titulaire d\'un compte supprimé reçoit un message clair, pas une erreur interne', function () {
    $phone = '+2250700000003';
    User::factory()->create(['role' => 'client', 'phone' => $phone])->delete();
    app(OtpService::class)->sendOtp($phone);

    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => $phone,
        'otp' => Otp::where('phone', $phone)->latest('id')->value('code'),
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.phone.0'))->toContain('compte supprimé')
        ->and($response->getContent())->not->toContain('SQLSTATE');
});

// ── Récupération d'un compte ────────────────────────────────────────────────

test('un livreur récupère son compte, sous le rôle « livreur » comme « driver »', function (string $role) {
    $livreur = User::factory()->create(['role' => 'livreur', 'name' => 'Ali Livreur', 'phone' => '+2250700000002']);
    $body = ['old_phone' => '+2250700000002', 'new_phone' => '+2250500000010', 'name' => 'Ali Livreur', 'role' => $role];

    $this->postJson('/api/v1/auth/reset-phone-request', $body)->assertOk();

    $this->postJson('/api/v1/auth/reset-phone-confirm', $body + [
        'otp' => Otp::where('phone', '+2250500000010')->latest('id')->value('code'),
    ])->assertOk();

    expect($livreur->fresh()->phone)->toBe('+2250500000010');
})->with(['livreur', 'driver']);

// ── Profil modifié par un administrateur ────────────────────────────────────

test('un administrateur sans la gestion des utilisateurs ne modifie pas le profil d\'un tiers', function () {
    $artisan = c27Artisan(['name' => 'Koffi Yao', 'payment_phone' => '+2250700000004']);
    $admin = c27Admin(['admin.faq.manage']);

    $this->actingAs($admin)->putJson("/api/v1/users/{$artisan->id}", ['name' => 'Renommé'])->assertForbidden();
    $this->actingAs($admin)->putJson("/api/v1/users/{$artisan->id}/location", ['lat' => 5.3, 'lng' => -4.0])->assertForbidden();
    $this->actingAs($admin)->postJson("/api/v1/users/{$artisan->id}/cnmci", ['cnmci_number' => 'CI-1'])->assertForbidden();

    expect($artisan->fresh()->name)->toBe('Koffi Yao')
        ->and($artisan->fresh()->cnmci_number)->toBeNull();
});

test('un administrateur habilité modifie le nom d\'un tiers, et la modification est auditée', function () {
    $artisan = c27Artisan(['name' => 'Koffi Yao']);
    $admin = c27Admin(['admin.users.view', 'admin.users.manage']);

    $this->actingAs($admin)->putJson("/api/v1/users/{$artisan->id}", ['name' => 'Koffi Yao Ange'])->assertOk();

    $log = AdminActivityLog::where('action', 'user.profile.updated_by_admin')->first();
    expect($artisan->fresh()->name)->toBe('Koffi Yao Ange')
        ->and($log)->not->toBeNull()
        ->and($log->admin_id)->toBe($admin->id)
        ->and($log->context['before']['name'])->toBe('Koffi Yao')
        ->and($log->context['after']['name'])->toBe('Koffi Yao Ange');
});

test('même habilité, un administrateur ne change pas le moyen de paiement d\'un tiers', function () {
    $artisan = c27Artisan(['payment_phone' => '+2250700000004']);

    $this->actingAs(c27Admin())
        ->putJson("/api/v1/users/{$artisan->id}", ['payment_phone' => '+2250511111111'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_phone');

    expect($artisan->fresh()->payment_phone)->toBe('+2250700000004');
});

test('le titulaire modifie toujours son profil et son moyen de paiement, sans ligne d\'audit', function () {
    $artisan = c27Artisan();

    $this->actingAs($artisan)
        ->putJson("/api/v1/users/{$artisan->id}", ['name' => 'Nouveau Nom', 'payment_phone' => '+2250511111111', 'preferred_payment_provider' => 'wave'])
        ->assertOk();

    expect($artisan->fresh()->payment_phone)->toBe('+2250511111111')
        ->and(AdminActivityLog::where('action', 'like', 'user.%')->count())->toBe(0);
});

test('un utilisateur ne modifie pas le profil d\'un autre utilisateur', function () {
    $artisan = c27Artisan(['name' => 'Koffi Yao']);

    $this->actingAs(c27Artisan())->putJson("/api/v1/users/{$artisan->id}", ['name' => 'Autre'])->assertForbidden();
});

// ── Carte CNMCI ─────────────────────────────────────────────────────────────

test('la carte CNMCI est stockée sur le disque privé et servie par un lien signé', function () {
    Storage::fake('local');
    Storage::fake('public');
    $artisan = c27Artisan();

    $response = $this->actingAs($artisan)->post("/api/v1/users/{$artisan->id}/cnmci", [
        'cnmci_number' => 'CI-2026-0001',
        'cnmci_card' => UploadedFile::fake()->image('carte.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();

    $path = $artisan->fresh()->cnmciCardPath();
    $url = $response->json('data.cnmciCardUrl');

    expect($path)->toStartWith('cnmci/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($url)->toContain('/users/'.$artisan->id.'/cnmci-card')
        ->and($url)->toContain('signature=')
        ->and($artisan->fresh()->cnmci_status)->toBe('en_attente');

    $this->get($url)->assertOk();
    // Sans signature, la carte n'est pas servie.
    $this->get("/users/{$artisan->id}/cnmci-card")->assertForbidden();
});

test('remplacer la carte supprime l\'ancienne, et l\'anonymisation supprime la carte', function () {
    Storage::fake('local');
    $artisan = c27Artisan();

    foreach (['a.jpg', 'b.jpg'] as $name) {
        $this->actingAs($artisan)->post("/api/v1/users/{$artisan->id}/cnmci", [
            'cnmci_card' => UploadedFile::fake()->image($name),
        ], ['Accept' => 'application/json'])->assertOk();
    }

    expect(Storage::disk('local')->allFiles('cnmci'))->toHaveCount(1);

    app(AdminGdprService::class)->anonymize($artisan->fresh(), c27Admin());

    expect(Storage::disk('local')->allFiles('cnmci'))->toBe([])
        ->and($artisan->fresh()->cnmci_card_url)->toBeNull();
});

test('une carte CNMCI qui n\'est pas une image est refusée', function () {
    Storage::fake('local');
    $artisan = c27Artisan();

    $this->actingAs($artisan)->post("/api/v1/users/{$artisan->id}/cnmci", [
        'cnmci_card' => UploadedFile::fake()->create('carte.svg', 10, 'image/svg+xml'),
    ], ['Accept' => 'application/json'])->assertStatus(422);

    expect(Storage::disk('local')->allFiles('cnmci'))->toBe([]);
});

test('la commande déplace les cartes du disque public vers le disque privé', function () {
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('public')->put('cnmci/ancienne.jpg', 'contenu');
    $artisan = c27Artisan();
    User::where('id', $artisan->id)->update(['cnmci_card_url' => '/storage/cnmci/ancienne.jpg']);

    // Tant qu'elle n'est pas déplacée, l'ancienne adresse reste servie telle quelle.
    expect($artisan->fresh()->cnmci_card_url)->toBe('/storage/cnmci/ancienne.jpg');

    $this->artisan('cnmci:migrate-to-private', ['--dry-run' => true])->assertExitCode(0);
    expect(Storage::disk('public')->exists('cnmci/ancienne.jpg'))->toBeTrue();

    $this->artisan('cnmci:migrate-to-private')->assertExitCode(0);

    expect($artisan->fresh()->cnmciCardPath())->toBe('cnmci/ancienne.jpg')
        ->and(Storage::disk('local')->get('cnmci/ancienne.jpg'))->toBe('contenu')
        ->and(Storage::disk('public')->exists('cnmci/ancienne.jpg'))->toBeFalse()
        ->and($artisan->fresh()->cnmci_card_url)->toContain('signature=');

    $this->artisan('cnmci:migrate-to-private')->expectsOutputToContain('rien à migrer')->assertExitCode(0);
});
