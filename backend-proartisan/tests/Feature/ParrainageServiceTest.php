<?php

use App\Models\Parrainage;
use App\Models\User;
use App\Services\AuthService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un parrain non artisan est refuse', function () {
    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700010001', 'score_prosartisan' => 900]);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => '+2250700010002',
        'filleul_nom' => 'Filleul Test',
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Action non autorisée. Seuls les artisans peuvent être parrains.');
});

test('un artisan avec un score de 800 ou moins est refuse', function () {
    $parrain = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010003', 'score_prosartisan' => 800]);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => '+2250700010004',
        'filleul_nom' => 'Filleul Test',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Score ProsArtisan insuffisant pour parrainer (minimum 800).');
});

test('inviter un numero non inscrit cree une ligne en attente sans filleul_id et envoie un sms', function () {
    $smsMock = $this->mock(SmsService::class);
    $smsMock->shouldReceive('send')
        ->once()
        ->with('+2250700010006', Mockery::pattern('/vous invite à rejoindre ProsArtisan en tant qu\'artisan/'))
        ->andReturn(['status' => 'success']);

    $parrain = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010005', 'score_prosartisan' => 900, 'name' => 'Maître Kouassi']);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => '+2250700010006',
        'filleul_nom' => 'Futur Apprenti',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('parrainages', [
        'parrain_id' => $parrain->id,
        'filleul_id' => null,
        'filleul_phone' => '+2250700010006',
        'filleul_nom' => 'Futur Apprenti',
        'statut' => 'en_attente_inscription',
    ]);
});

test('une invitation accepte aussi un numero local normalise vers le format +225', function () {
    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['status' => 'success']);

    $parrain = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010007', 'score_prosartisan' => 900]);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => '0700010008',
        'filleul_nom' => 'Futur Apprenti',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('parrainages', [
        'parrain_id' => $parrain->id,
        'filleul_phone' => '+2250700010008',
        'statut' => 'en_attente_inscription',
    ]);
});

test('inscription ulterieure avec le meme numero et le role artisan lie automatiquement le parrainage', function () {
    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['status' => 'success']);

    $parrain = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010009', 'score_prosartisan' => 900]);

    $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => '+2250700010010',
        'filleul_nom' => 'Futur Apprenti',
    ])->assertCreated();

    // findOrCreateByPhone() : un compte coquille est créé par téléphone
    // avant que le rôle ne soit connu (flux OTP normal).
    $filleul = app(AuthService::class)->findOrCreateByPhone('+2250700010010');

    app(AuthService::class)->register($filleul, ['name' => 'Nouvel Artisan', 'role' => 'artisan']);

    $parrainage = Parrainage::where('filleul_phone', '+2250700010010')->first();

    expect($parrainage->statut)->toBe('actif');
    expect($parrainage->filleul_id)->toBe($filleul->id);
});

test('inscription avec un mauvais role ne lie pas le parrainage en attente', function () {
    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['status' => 'success']);

    $parrain = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010011', 'score_prosartisan' => 900]);

    $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => '+2250700010012',
        'filleul_nom' => 'Futur Apprenti',
    ])->assertCreated();

    $filleul = app(AuthService::class)->findOrCreateByPhone('+2250700010012');

    app(AuthService::class)->register($filleul, ['name' => 'Un Client', 'role' => 'client']);

    $parrainage = Parrainage::where('filleul_phone', '+2250700010012')->first();

    expect($parrainage->statut)->toBe('en_attente_inscription');
    expect($parrainage->filleul_id)->toBeNull();
});

test('filleul deja inscrit avec le bon role reste parraine directement sans changement de comportement', function () {
    $parrain = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010013', 'score_prosartisan' => 900]);
    $filleul = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010014']);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => $filleul->phone,
        'filleul_nom' => 'Nom Ignoré',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('parrainages', [
        'parrain_id' => $parrain->id,
        'filleul_id' => $filleul->id,
        'filleul_phone' => $filleul->phone,
        'statut' => 'actif',
    ]);
});

test('un filleul deja inscrit mais non artisan est refuse', function () {
    $parrain = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010015', 'score_prosartisan' => 900]);
    $filleul = User::factory()->create(['role' => 'client', 'phone' => '+2250700010016']);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => $filleul->phone,
        'filleul_nom' => 'Nom Ignoré',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Le filleul coopté doit avoir le rôle d\'artisan.');
});

test('un artisan ne peut pas se parrainer lui-meme', function () {
    $parrain = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010017', 'score_prosartisan' => 900]);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages', [
        'filleul_phone' => $parrain->phone,
        'filleul_nom' => 'Moi-même',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Vous ne pouvez pas vous parrainer vous-même.');
});

test('double invitation du meme numero renvoie une erreur claire sans exception sql', function () {
    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['status' => 'success']);

    $parrain1 = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010018', 'score_prosartisan' => 900]);
    $parrain2 = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010019', 'score_prosartisan' => 900]);

    $this->actingAs($parrain1)->postJson('/api/v1/parrainages', [
        'filleul_phone' => '+2250700010020',
        'filleul_nom' => 'Futur Apprenti',
    ])->assertCreated();

    $response = $this->actingAs($parrain2)->postJson('/api/v1/parrainages', [
        'filleul_phone' => '+2250700010020',
        'filleul_nom' => 'Futur Apprenti Bis',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Ce numéro a déjà été parrainé ou invité.');
});

test('parrainer un filleul deja lie a un parrain est refuse', function () {
    $parrain1 = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010021', 'score_prosartisan' => 900]);
    $parrain2 = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010022', 'score_prosartisan' => 900]);
    $filleul = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700010023']);

    $this->actingAs($parrain1)->postJson('/api/v1/parrainages', [
        'filleul_phone' => $filleul->phone,
        'filleul_nom' => 'Nom Ignoré',
    ])->assertCreated();

    $response = $this->actingAs($parrain2)->postJson('/api/v1/parrainages', [
        'filleul_phone' => $filleul->phone,
        'filleul_nom' => 'Nom Ignoré',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Ce numéro a déjà été parrainé ou invité.');
});
