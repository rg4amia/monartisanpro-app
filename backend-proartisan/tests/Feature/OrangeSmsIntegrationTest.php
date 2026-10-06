<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\OrangeSmsService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrangeSmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.orange_sms.base_url' => 'https://api.orange.com',
            'services.orange_sms.api_token' => 'test-bearer-token-12345',
            'services.orange_sms.sender_address' => 'tel:+2250000',
            'services.orange_sms.sender_name' => 'ProsArtisan',
        ]);
    }

    public function test_orange_sms_service_formats_addresses_correctly(): void
    {
        $service = new OrangeSmsService;

        $this->assertEquals('tel:+2250141498409', $service->formatAddress('0141498409'));
        $this->assertEquals('tel:+2250700000001', $service->formatAddress('+2250700000001'));
        $this->assertEquals('tel:+2250700000001', $service->formatAddress('tel:+2250700000001'));
        $this->assertEquals('tel:+2250000', $service->formatSenderAddress('tel:+2250000'));
        $this->assertEquals('tel:+2250000', $service->formatSenderAddress('+2250000'));
    }

    public function test_orange_sms_service_sends_sms_matching_swagger_spec(): void
    {
        Http::fake([
            'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests' => Http::response([
                'outboundSMSMessageRequest' => [
                    'address' => 'tel:+2250700000001',
                    'senderAddress' => 'tel:+2250000',
                    'outboundSMSTextMessage' => [
                        'message' => 'Bonjour ProsArtisan',
                    ],
                    'senderName' => 'ProsArtisan',
                    'resourceURL' => 'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests/msg-uuid-99',
                ],
            ], 201),
        ]);

        $service = new OrangeSmsService;
        $result = $service->send('0700000001', 'Bonjour ProsArtisan');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('orange', $result['provider']);
        $this->assertEquals('https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests/msg-uuid-99', $result['data']['resource_url']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests'
                && $request->hasHeader('Authorization', 'Bearer test-bearer-token-12345')
                && ($data['outboundSMSMessageRequest']['address'] ?? null) === 'tel:+2250700000001'
                && ($data['outboundSMSMessageRequest']['senderAddress'] ?? null) === 'tel:+2250000'
                && ($data['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'] ?? null) === 'Bonjour ProsArtisan'
                && ($data['outboundSMSMessageRequest']['senderName'] ?? null) === 'ProsArtisan';
        });
    }

    public function test_orange_sms_oauth_token_retrieval_when_bearer_token_not_provided(): void
    {
        config([
            'services.orange_sms.api_token' => null,
            'services.orange_sms.client_id' => 'my_client_id',
            'services.orange_sms.client_secret' => 'my_client_secret',
        ]);

        Http::fake([
            'https://api.orange.com/oauth/v3/token' => Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'oauth-generated-token-xyz',
                'expires_in' => 3600,
            ], 200),
            'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests' => Http::response([
                'outboundSMSMessageRequest' => [
                    'resourceURL' => 'https://api.orange.com/req/123',
                ],
            ], 201),
        ]);

        $service = new OrangeSmsService;
        $result = $service->send('0700000001', 'Test OAuth');

        $this->assertEquals('success', $result['status']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.orange.com/oauth/v3/token'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('my_client_id:my_client_secret'));
        });
    }

    public function test_orange_sms_handles_api_errors_gracefully(): void
    {
        Http::fake([
            'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests' => Http::response([
                'requestError' => [
                    'serviceException' => [
                        'messageId' => 'SVC0280',
                        'text' => 'Message too long',
                    ],
                ],
            ], 400),
        ]);

        $service = new OrangeSmsService;
        $result = $service->send('0700000001', 'Trop long');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('orange', $result['provider']);
        $this->assertStringContainsString('SVC0280', $result['message']);
        $this->assertStringContainsString('Message too long', $result['message']);
    }

    public function test_sms_service_routes_to_orange_when_active_provider_is_orange(): void
    {
        Setting::updateOrCreate(
            ['key' => 'sms_provider'],
            ['value' => 'orange', 'type' => 'string', 'group' => 'communication']
        );

        Http::fake([
            'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests' => Http::response([
                'outboundSMSMessageRequest' => [
                    'resourceURL' => 'https://api.orange.com/req/otp-1',
                ],
            ], 201),
        ]);

        $smsService = app(SmsService::class);
        $this->assertEquals('orange', $smsService->getProvider());

        $result = $smsService->send('0700000001', 'Test envoi');
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('orange', $result['provider']);

        $otpResult = $smsService->sendOtp('0700000001', '1234');
        $this->assertEquals('success', $otpResult['status']);
        $this->assertEquals('orange', $otpResult['provider']);
    }

    public function test_sms_service_routes_to_smspro_when_active_provider_is_smspro(): void
    {
        Setting::updateOrCreate(
            ['key' => 'sms_provider'],
            ['value' => 'smspro', 'type' => 'string', 'group' => 'communication']
        );

        config([
            'services.sms.api_token' => 'smspro-token-test',
            'services.sms.base_url' => 'https://app.smspro.africa/api/v3',
        ]);

        Http::fake([
            'https://app.smspro.africa/api/v3/sms/send' => Http::response([
                'status' => 'success',
                'data' => ['uid' => 'smspro-msg-123'],
            ], 200),
        ]);

        $smsService = app(SmsService::class);
        $this->assertEquals('smspro', $smsService->getProvider());

        $result = $smsService->send('0700000001', 'Bonjour SmsPro');
        $this->assertEquals('success', $result['status']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'smspro.africa');
        });
    }

    public function test_artisan_switch_provider_command(): void
    {
        // Basculer vers orange
        $this->artisan('sms:switch-provider', ['provider' => 'orange'])
            ->expectsOutputToContain('La passerelle d\'envoi SMS a été basculée avec succès sur : [orange]')
            ->assertSuccessful();

        $this->assertEquals('orange', Setting::getValueByKey('sms_provider'));

        // Vérifier statut
        $this->artisan('sms:switch-provider', ['--status' => true])
            ->expectsOutputToContain('ORANGE')
            ->assertSuccessful();

        // Basculer vers smspro
        $this->artisan('sms:switch-provider', ['provider' => 'smspro'])
            ->expectsOutputToContain('La passerelle d\'envoi SMS a été basculée avec succès sur : [smspro]')
            ->assertSuccessful();

        $this->assertEquals('smspro', Setting::getValueByKey('sms_provider'));

        // Refus fournisseur invalide
        $this->artisan('sms:switch-provider', ['provider' => 'invalide'])
            ->expectsOutputToContain('Fournisseur invalide')
            ->assertFailed();
    }

    public function test_sms_test_command_with_forced_provider(): void
    {
        $this->artisan('sms:test', [
            'phone' => '0700000001',
            '--provider' => 'log',
            '--message' => 'Message test log',
        ])
            ->expectsOutputToContain('SMS envoyé avec succès !')
            ->assertSuccessful();
    }
}
