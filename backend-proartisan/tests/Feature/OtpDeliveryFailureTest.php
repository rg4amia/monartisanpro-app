<?php

use App\Models\Otp;
use App\Services\SmsService;
use App\Services\WhatsAppService;

/**
 * Un code que le fournisseur de SMS a refusé d'envoyer n'est plus annoncé
 * « envoyé » : l'utilisateur attendait un SMS qui n'était jamais parti.
 */
function fakeSmsResult(array $result): void
{
    app()->bind(SmsService::class, function () use ($result) {
        $fake = Mockery::mock(SmsService::class);
        $fake->shouldReceive('sendOtp')->andReturn($result);

        return $fake;
    });
}

test('un envoi refusé par le fournisseur répond une erreur en français, pas « code envoyé »', function () {
    fakeSmsResult(['status' => 'error', 'message' => 'Insufficient balance', 'http_status' => 402]);

    $response = $this->postJson('/api/v1/auth/send-otp', ['phone' => '+2250707000001']);

    $response->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'OTP_DELIVERY_FAILED');

    expect($response->json('message'))->toContain("n'a pas pu être envoyé")
        ->and($response->json('message'))->not->toContain('Insufficient');
});

test('le code d\'un envoi refusé ne reste pas utilisable', function () {
    fakeSmsResult(['status' => 'error', 'message' => 'refus']);

    $this->postJson('/api/v1/auth/send-otp', ['phone' => '+2250707000002'])->assertStatus(503);

    expect(Otp::where('phone', '+2250707000002')->whereNull('used_at')->count())->toBe(0);
});

test('un envoi accepté répond toujours « code envoyé »', function () {
    $this->postJson('/api/v1/auth/send-otp', ['phone' => '+2250707000003'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Otp::where('phone', '+2250707000003')->whereNull('used_at')->count())->toBe(1);
});

test('une réponse de forme inattendue ne ferme pas la connexion', function () {
    fakeSmsResult(['data' => 'queued']);

    $this->postJson('/api/v1/auth/send-otp', ['phone' => '+2250707000004'])->assertOk();
});

test('WhatsApp refusé puis SMS accepté : le code est annoncé envoyé', function () {
    app()->bind(WhatsAppService::class, function () {
        $fake = Mockery::mock(WhatsAppService::class);
        $fake->shouldReceive('sendOtp')->andReturn(['status' => 'error', 'message' => 'token manquant']);

        return $fake;
    });

    $this->postJson('/api/v1/auth/send-otp', ['phone' => '+2250707000005', 'channel' => 'whatsapp'])
        ->assertOk();
});

test('WhatsApp et SMS refusés : erreur', function () {
    fakeSmsResult(['status' => 'error', 'message' => 'refus']);
    app()->bind(WhatsAppService::class, function () {
        $fake = Mockery::mock(WhatsAppService::class);
        $fake->shouldReceive('sendOtp')->andReturn(['status' => 'error', 'message' => 'token manquant']);

        return $fake;
    });

    $this->postJson('/api/v1/auth/send-otp', ['phone' => '+2250707000006', 'channel' => 'whatsapp'])
        ->assertStatus(503);
});
