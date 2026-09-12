<?php

namespace Tests\Feature;

use App\Models\SmsDeliveryReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Webhook DLR de SMSpro.
 *
 * SMSpro relance jusqu'à trois fois (10 s / 60 s / 300 s) tant que la réponse
 * n'est pas 2xx : le traitement doit donc être idempotent, insensible à
 * l'ordre d'arrivée, et ne renvoyer une erreur que lorsque réessayer sert à
 * quelque chose.
 */
class SmsDeliveryReceiptWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-signature-smspro';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gateway.signing_secret' => self::SECRET]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postDlr(array $payload, ?string $signature = null, ?string $secret = null)
    {
        $body = json_encode($payload);
        $signature ??= 'sha256='.hash_hmac('sha256', $body, $secret ?? self::SECRET);

        return $this->call(
            'POST',
            '/api/v1/webhooks/sms-dlr',
            [],
            [],
            [],
            [
                'HTTP_X-Webhook-Signature' => $signature,
                'HTTP_X-Webhook-Event' => 'dlr.status_updated',
                'CONTENT_TYPE' => 'application/json',
            ],
            $body
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePayload(array $overrides = []): array
    {
        // Charge utile exactement telle que documentée par SMSpro.
        return array_merge([
            'event' => 'dlr.status_updated',
            'message_id' => '1746b2f9a0c31',
            'uid' => '606812e63f78b',
            'to' => '22890000000',
            'from' => 'MyBrand',
            'sms_type' => 'plain',
            'status' => 'Delivered',
            'campaign_id' => null,
            'timestamp' => '2026-07-09T12:34:56+00:00',
        ], $overrides);
    }

    public function test_a_signed_receipt_is_recorded(): void
    {
        $this->postDlr($this->samplePayload())->assertOk();

        $receipt = SmsDeliveryReceipt::firstOrFail();

        $this->assertSame('606812e63f78b', $receipt->uid);
        $this->assertSame('1746b2f9a0c31', $receipt->message_id);
        $this->assertSame('22890000000', $receipt->recipient);
        $this->assertSame('MyBrand', $receipt->sender_id);
        $this->assertSame('plain', $receipt->sms_type);
        $this->assertSame('Delivered', $receipt->status);
        $this->assertNull($receipt->campaign_id);
        $this->assertSame('2026-07-09 12:34:56', $receipt->status_at->utc()->format('Y-m-d H:i:s'));
    }

    // ── Authenticité ─────────────────────────────────────────────────────────

    public function test_an_unsigned_receipt_is_refused(): void
    {
        $this->postDlr($this->samplePayload(), signature: '')->assertStatus(401);

        $this->assertSame(0, SmsDeliveryReceipt::count());
    }

    public function test_a_receipt_signed_with_the_wrong_secret_is_refused(): void
    {
        $this->postDlr($this->samplePayload(), secret: 'mauvais-secret')->assertStatus(401);

        $this->assertSame(0, SmsDeliveryReceipt::count());
    }

    public function test_a_body_altered_after_signing_is_refused(): void
    {
        $signature = 'sha256='.hash_hmac(
            'sha256',
            json_encode($this->samplePayload(['status' => 'Failed'])),
            self::SECRET
        );

        // Signature valide pour un contenu, corps remplacé par un autre.
        $this->postDlr($this->samplePayload(['status' => 'Delivered']), signature: $signature)
            ->assertStatus(401);

        $this->assertSame(0, SmsDeliveryReceipt::count());
    }

    public function test_the_endpoint_stays_closed_without_a_configured_secret(): void
    {
        config(['services.gateway.signing_secret' => '']);

        $this->postDlr($this->samplePayload())->assertStatus(503);
    }

    // ── Relances et idempotence ──────────────────────────────────────────────

    public function test_a_replayed_receipt_does_not_duplicate_the_row(): void
    {
        $payload = $this->samplePayload();

        // SMSpro relance jusqu'à trois fois : rejouer doit être sans effet.
        $this->postDlr($payload)->assertOk();
        $this->postDlr($payload)->assertOk();
        $this->postDlr($payload)->assertOk();

        $this->assertSame(1, SmsDeliveryReceipt::count());
    }

    public function test_a_newer_status_replaces_the_previous_one(): void
    {
        $this->postDlr($this->samplePayload([
            'status' => 'Enroute',
            'timestamp' => '2026-07-09T12:34:56+00:00',
        ]))->assertOk();

        $this->postDlr($this->samplePayload([
            'status' => 'Delivered',
            'timestamp' => '2026-07-09T12:35:30+00:00',
        ]))->assertOk();

        $this->assertSame('Delivered', SmsDeliveryReceipt::firstOrFail()->status);
        $this->assertSame(1, SmsDeliveryReceipt::count());
    }

    public function test_a_late_retry_cannot_overwrite_a_newer_status(): void
    {
        $this->postDlr($this->samplePayload([
            'status' => 'Delivered',
            'timestamp' => '2026-07-09T12:35:30+00:00',
        ]))->assertOk();

        // Relance tardive d'un statut antérieur : elle ne doit pas faire
        // régresser la commande à « en route ».
        $this->postDlr($this->samplePayload([
            'status' => 'Enroute',
            'timestamp' => '2026-07-09T12:34:56+00:00',
        ]))->assertOk();

        $this->assertSame('Delivered', SmsDeliveryReceipt::firstOrFail()->status);
    }

    // ── Politique de réponse ─────────────────────────────────────────────────

    public function test_an_incomplete_payload_is_acknowledged_rather_than_retried(): void
    {
        // Un corps inexploitable ne le deviendra pas à la troisième tentative :
        // provoquer des relances n'apporterait rien.
        $this->postDlr($this->samplePayload(['uid' => null]))
            ->assertOk()
            ->assertJsonPath('handled', false);

        $this->assertSame(0, SmsDeliveryReceipt::count());
    }

    public function test_an_unknown_event_is_acknowledged_without_being_stored(): void
    {
        $this->postDlr($this->samplePayload(['event' => 'sms.something_else']))
            ->assertOk()
            ->assertJsonPath('handled', false);

        $this->assertSame(0, SmsDeliveryReceipt::count());
    }

    // ── Valeur opérationnelle : fiabilité des OTP ────────────────────────────

    public function test_a_failed_otp_is_flagged_in_the_logs(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('OTP non délivré.', \Mockery::on(
                fn (array $ctx) => $ctx['status'] === 'Rejected' && $ctx['recipient'] === '22890000000'
            ));

        $this->postDlr($this->samplePayload([
            'sms_type' => 'otp',
            'status' => 'Rejected',
        ]))->assertOk();
    }

    public function test_failed_otp_receipts_can_be_isolated(): void
    {
        $this->postDlr($this->samplePayload(['uid' => 'a', 'sms_type' => 'otp', 'status' => 'Delivered']));
        $this->postDlr($this->samplePayload(['uid' => 'b', 'sms_type' => 'otp', 'status' => 'Failed']));
        $this->postDlr($this->samplePayload(['uid' => 'c', 'sms_type' => 'otp', 'status' => 'Undelivered']));
        $this->postDlr($this->samplePayload(['uid' => 'd', 'sms_type' => 'plain', 'status' => 'Failed']));

        // La requête que l'observabilité posera : « OTP non délivrés ».
        $this->assertSame(2, SmsDeliveryReceipt::query()->otp()->failed()->count());
    }

    public function test_transitional_statuses_are_not_counted_as_failures(): void
    {
        foreach (['Enroute', 'Accepted', 'Delivered'] as $index => $status) {
            $this->postDlr($this->samplePayload([
                'uid' => 'uid-'.$index,
                'sms_type' => 'otp',
                'status' => $status,
            ]))->assertOk();
        }

        $this->assertSame(0, SmsDeliveryReceipt::query()->otp()->failed()->count());
    }
}
