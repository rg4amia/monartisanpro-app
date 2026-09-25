<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AntiBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AntiBotSecurityTest extends TestCase
{
    use RefreshDatabase;

    private AntiBotService $antiBotService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->antiBotService = app(AntiBotService::class);
    }

    public function test_service_generates_valid_hmac_challenge(): void
    {
        $challenge = $this->antiBotService->generateChallenge('admin_login');

        $this->assertArrayHasKey('token', $challenge);
        $this->assertArrayHasKey('question', $challenge);
        $this->assertArrayHasKey('a', $challenge);
        $this->assertArrayHasKey('b', $challenge);
        $this->assertEquals($challenge['a'] + $challenge['b'], $challenge['answer']);
    }

    public function test_service_blocks_honeypot_trap(): void
    {
        $challenge = $this->antiBotService->generateChallenge('test_action');
        $request = request()->merge([
            '_bot_token' => $challenge['token'],
            '_bot_answer' => (string) $challenge['answer'],
            'bot_trap' => 'I am a spambot',
        ]);

        $this->assertFalse($this->antiBotService->verify($request, 'test_action'));
    }

    public function test_service_blocks_tampered_token(): void
    {
        $challenge = $this->antiBotService->generateChallenge('test_action');
        $tamperedToken = $challenge['token'] . 'bad';
        $request = request()->merge([
            '_bot_token' => $tamperedToken,
            '_bot_answer' => (string) $challenge['answer'],
        ]);

        $this->assertFalse($this->antiBotService->verify($request, 'test_action'));
    }

    public function test_service_blocks_incorrect_arithmetic_answer(): void
    {
        $challenge = $this->antiBotService->generateChallenge('test_action');
        $request = request()->merge([
            '_bot_token' => $challenge['token'],
            '_bot_answer' => (string) ($challenge['answer'] + 99),
        ]);

        $this->assertFalse($this->antiBotService->verify($request, 'test_action'));
    }

    public function test_service_prevents_replay_attacks_with_nonce(): void
    {
        $challenge = $this->antiBotService->generateChallenge('test_action');
        $request = request()->merge([
            '_bot_token' => $challenge['token'],
            '_bot_answer' => (string) $challenge['answer'],
        ]);

        // Première vérification : doit réussir
        $this->assertTrue($this->antiBotService->verify($request, 'test_action'));

        // Seconde vérification avec le même nonce : doit être bloquée (rejeu)
        $this->assertFalse($this->antiBotService->verify($request, 'test_action'));
    }

    public function test_admin_login_requires_and_validates_anti_bot_challenge(): void
    {
        // 1. Échec si bot_trap est renseigné
        $responseTrap = $this->post('/admin/login', [
            'email' => 'admin@prosartisan.ci',
            'password' => 'secret123',
            'bot_trap' => 'automated_script',
        ]);
        $responseTrap->assertSessionHasErrors(['bot_trap']);

        // 2. Échec si challenge absent ou invalide
        $responseNoChallenge = $this->post('/admin/login', [
            'email' => 'admin@prosartisan.ci',
            'password' => 'secret123',
            '_bot_token' => 'invalid_token',
            '_bot_answer' => '99',
        ]);
        $responseNoChallenge->assertSessionHasErrors(['_bot_answer']);

        // 3. Succès de validation anti-bot avec un challenge valide
        $challenge = $this->antiBotService->generateChallenge('admin_login');
        $responseValidChallenge = $this->post('/admin/login', [
            'email' => 'unknown@prosartisan.ci',
            'password' => 'secret123',
            '_bot_token' => $challenge['token'],
            '_bot_answer' => (string) $challenge['answer'],
        ]);

        // La validation anti-bot est passée (pas d'erreur _bot_answer ni bot_trap),
        // l'erreur concerne maintenant les identifiants ou la session d'auth
        $responseValidChallenge->assertSessionDoesntHaveErrors(['_bot_answer', 'bot_trap']);
    }

    public function test_api_security_challenge_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/v1/auth/security-challenge?action=send_otp');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'question',
                    'a',
                    'b',
                ],
            ]);
    }

    public function test_api_send_otp_blocks_bot_trap_and_bad_challenge(): void
    {
        // 1. Échec si honeypot rempli
        $responseTrap = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '0700000001',
            'bot_trap' => 'bot_value',
        ]);
        $responseTrap->assertStatus(422)
            ->assertJsonPath('error_code', 'BOT_DETECTED');

        // 2. Échec si challenge fourni avec mauvaise réponse
        $challenge = $this->antiBotService->generateChallenge('send_otp');
        $responseBadAnswer = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '0700000001',
            '_bot_token' => $challenge['token'],
            '_bot_answer' => (string) ($challenge['answer'] + 10),
        ]);
        $responseBadAnswer->assertStatus(422)
            ->assertJsonPath('error_code', 'BOT_CHALLENGE_FAILED');

        // 3. Succès si challenge valide
        $challengeValid = $this->antiBotService->generateChallenge('send_otp');
        $responseValid = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '0700000001',
            '_bot_token' => $challengeValid['token'],
            '_bot_answer' => (string) $challengeValid['answer'],
        ]);
        $responseValid->assertOk();
    }
}
