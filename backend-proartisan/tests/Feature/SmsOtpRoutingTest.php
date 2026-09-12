<?php

namespace Tests\Feature;

use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SMSpro distingue le type `otp` du type `plain` : le premier emprunte la
 * route transactionnelle prioritaire, le second la voie des campagnes, plus
 * filtrée par les opérateurs. Les codes de vérification partaient en `plain`.
 *
 * L'OTP étant le mécanisme de connexion, sa livraison relève de la sécurité
 * autant que de l'expérience utilisateur.
 */
class SmsOtpRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // On sort du mode `log` pour observer la requête réellement émise.
        config([
            'services.sms.provider' => 'smspro',
            'services.sms.api_token' => 'token-test',
            'services.sms.base_url' => 'https://app.smspro.africa/api/v3',
        ]);
    }

    public function test_an_otp_is_sent_on_the_dedicated_otp_route(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        app(SmsService::class)->sendOtp('+2250700000001', '1234');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://app.smspro.africa/api/v3/sms/send'
                && $request['type'] === 'otp'
                && str_contains($request['message'], '1234');
        });
    }

    public function test_a_regular_sms_still_uses_the_plain_type(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        app(SmsService::class)->send('+2250700000001', 'Votre commande est prête.');

        Http::assertSent(fn (Request $request) => $request['type'] === 'plain');
    }

    public function test_the_otp_message_carries_the_anti_phishing_warning(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        app(SmsService::class)->sendOtp('+2250700000001', '5678');

        Http::assertSent(function (Request $request) {
            // Seule parade au hameçonnage par téléphone, où un faux support
            // réclame le code reçu.
            return str_contains($request['message'], 'Ne le communiquez jamais');
        });
    }

    public function test_the_announced_validity_matches_the_configured_ttl(): void
    {
        config(['prosartisan.otp.ttl' => 7]);

        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        app(SmsService::class)->sendOtp('+2250700000001', '4321');

        // Le message annonçait « 10 minutes » alors que le code expirait en 5.
        Http::assertSent(fn (Request $request) => str_contains($request['message'], 'Valide 7 minutes'));
    }

    public function test_the_recipient_is_normalised_to_the_international_format(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        app(SmsService::class)->sendOtp('0700000001', '1234');

        Http::assertSent(fn (Request $request) => $request['recipient'] === '2250700000001');
    }
}
