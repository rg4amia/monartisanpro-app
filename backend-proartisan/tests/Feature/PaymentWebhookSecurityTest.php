<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Transaction;
use App\Services\OrangeMoneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Les webhooks de paiement décident si un séquestre est financé : ils sont la
 * frontière entre « le client a payé » et « la plateforme le croit ». Aucun
 * test ne les couvrait.
 */
class PaymentWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function pendingTransaction(array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'type' => 'acompte',
            'montant' => 100000,
            'wallet_source' => 'client_wallet_1',
            'wallet_dest' => 'escrow',
            'provider' => 'orange_money',
            'statut' => PaymentStatus::EN_ATTENTE,
            'orange_order_id' => 'OM-ORDER-1',
            'orange_payment_token' => 'pay-token-1',
        ], $overrides));
    }

    // ── Wave : signature HMAC ────────────────────────────────────────────────

    public function test_wave_webhook_is_refused_when_no_secret_is_configured(): void
    {
        // Un HMAC calculé avec une clé vide est reproductible par n'importe
        // qui : c'est exactement la signature qu'un attaquant produirait si le
        // secret manquait en production.
        config(['services.wave.webhook_secret' => '']);

        $payload = json_encode(['id' => 'checkout-1', 'status' => 'succeeded']);
        $forged = hash_hmac('sha256', $payload, '');

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/wave',
            [],
            [],
            [],
            ['HTTP_X-Wave-Signature' => $forged, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(401);
    }

    public function test_wave_webhook_is_refused_without_a_signature_header(): void
    {
        config(['services.wave.webhook_secret' => 'secret-wave-test']);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/wave',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => 'checkout-1'])
        );

        // Sans en-tête, la requête doit être rejetée proprement — et non
        // provoquer une erreur serveur.
        $response->assertStatus(401);
    }

    public function test_wave_webhook_is_refused_with_a_wrong_signature(): void
    {
        config(['services.wave.webhook_secret' => 'secret-wave-test']);

        $payload = json_encode(['id' => 'checkout-1']);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/wave',
            [],
            [],
            [],
            ['HTTP_X-Wave-Signature' => 'signature-bidon', 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(401);
    }

    public function test_a_correct_signature_passes_the_security_check(): void
    {
        config(['services.wave.webhook_secret' => 'secret-wave-test']);

        $payload = json_encode(['id' => 'checkout-inconnu', 'status' => 'succeeded']);
        $signature = hash_hmac('sha256', $payload, 'secret-wave-test');

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/wave',
            [],
            [],
            [],
            ['HTTP_X-Wave-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        // 404 (transaction inconnue) et non 401 : la signature a été acceptée.
        // C'est ce qui distingue le durcissement d'un blocage systématique.
        $response->assertStatus(404);
    }

    public function test_the_signature_check_is_not_bypassed_by_an_empty_header(): void
    {
        config(['services.wave.webhook_secret' => 'secret-wave-test']);

        $payload = json_encode(['id' => 'checkout-1']);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/wave',
            [],
            [],
            [],
            ['HTTP_X-Wave-Signature' => '', 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(401);
    }

    // ── Orange Money : statut réellement encaissé ────────────────────────────

    public function test_an_initiated_payment_does_not_confirm_the_transaction(): void
    {
        $transaction = $this->pendingTransaction();

        // `INITIATED` = session de paiement ouverte, client n'ayant rien payé.
        $service = Mockery::mock(OrangeMoneyService::class)->makePartial();
        $service->shouldReceive('checkPaymentStatus')
            ->andReturn(['status' => 'INITIATED', 'tx_reference' => 'tx-1', 'data' => []]);

        $service->processNotification(['order_id' => 'OM-ORDER-1']);

        $transaction->refresh();
        $this->assertSame(PaymentStatus::EN_ATTENTE, $transaction->statut);
        $this->assertNull($transaction->paid_at);
    }

    public function test_a_successful_payment_still_confirms_the_transaction(): void
    {
        $transaction = $this->pendingTransaction();

        $service = Mockery::mock(OrangeMoneyService::class)->makePartial();
        $service->shouldReceive('checkPaymentStatus')
            ->andReturn(['status' => 'SUCCESS', 'tx_reference' => 'tx-1', 'data' => []]);

        $service->processNotification(['order_id' => 'OM-ORDER-1']);

        $transaction->refresh();
        $this->assertSame(PaymentStatus::CONFIRME, $transaction->statut);
        $this->assertNotNull($transaction->paid_at);
    }

    public function test_a_notification_without_payment_token_is_rejected(): void
    {
        // Sans jeton, la double vérification auprès d'Orange est impossible :
        // faire confiance au payload seul rouvrirait la faille.
        $transaction = $this->pendingTransaction(['orange_payment_token' => null]);

        $result = app(OrangeMoneyService::class)
            ->processNotification(['order_id' => 'OM-ORDER-1', 'status' => 'SUCCESS']);

        $this->assertNull($result);
        $this->assertSame(PaymentStatus::EN_ATTENTE, $transaction->refresh()->statut);
    }
}
