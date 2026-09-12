<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SmsDeliveryReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Réception des accusés de livraison (DLR) de SMSpro.
 *
 * SMSpro relance jusqu'à trois fois (10 s, 60 s, 300 s) tant que la réponse
 * n'est pas 2xx, et demande une réponse en moins de 10 secondes. Cela dicte
 * trois règles :
 *
 *  - le traitement reste léger (une seule écriture) ;
 *  - il est idempotent, car la même notification peut arriver plusieurs fois ;
 *  - on ne renvoie une erreur que lorsque réessayer a un sens. Un corps
 *    inexploitable ne le deviendra pas à la troisième tentative : on
 *    l'acquitte en le journalisant, plutôt que de provoquer des relances
 *    inutiles. Une panne de base, elle, mérite le 500 : la relance sauvera
 *    l'accusé.
 */
class SmsWebhookController extends Controller
{
    public function deliveryReceipt(Request $request): JsonResponse
    {
        $data = $request->json()->all();

        $event = (string) ($data['event'] ?? $request->header('X-Webhook-Event', ''));

        if ($event !== 'dlr.status_updated') {
            // Événement inconnu : acquitté pour rester compatible si SMSpro en
            // introduit d'autres, sans prétendre l'avoir traité.
            Log::info('Webhook SMSpro : événement non géré.', ['event' => $event]);

            return response()->json(['success' => true, 'handled' => false]);
        }

        $uid = $this->nonEmptyString($data['uid'] ?? null);
        $recipient = $this->nonEmptyString($data['to'] ?? null);
        $status = $this->nonEmptyString($data['status'] ?? null);

        if ($uid === null || $recipient === null || $status === null) {
            Log::warning('Webhook SMSpro : accusé incomplet, ignoré.', [
                'has_uid' => $uid !== null,
                'has_to' => $recipient !== null,
                'has_status' => $status !== null,
            ]);

            return response()->json(['success' => true, 'handled' => false]);
        }

        $statusAt = $this->parseTimestamp($data['timestamp'] ?? null);

        try {
            $stored = $this->record($data, $uid, $recipient, $status, $statusAt);
        } catch (QueryException $e) {
            // Course entre deux relances simultanées : l'unicité de `uid` a
            // tranché, l'accusé est déjà enregistré. Rien à réessayer.
            if ($this->isDuplicate($e)) {
                return response()->json(['success' => true, 'duplicate' => true]);
            }

            throw $e;
        }

        return response()->json(['success' => true, 'recorded' => $stored]);
    }

    /**
     * Enregistre l'accusé, sans laisser une relance tardive écraser un statut
     * plus récent (une relance d'`Enroute` peut arriver après `Delivered`).
     *
     * @param  array<string, mixed>  $data
     */
    private function record(
        array $data,
        string $uid,
        string $recipient,
        string $status,
        CarbonImmutable $statusAt
    ): bool {
        $receipt = SmsDeliveryReceipt::firstOrNew(['uid' => $uid]);

        if ($receipt->exists && $receipt->status_at !== null && $receipt->status_at->gt($statusAt)) {
            return false;
        }

        $receipt->fill([
            'message_id' => $this->nonEmptyString($data['message_id'] ?? null),
            'recipient' => $recipient,
            'sender_id' => $this->nonEmptyString($data['from'] ?? null),
            'sms_type' => $this->nonEmptyString($data['sms_type'] ?? null),
            'status' => $status,
            'campaign_id' => $this->nonEmptyString($data['campaign_id'] ?? null),
            'status_at' => $statusAt,
            'payload' => $data,
        ])->save();

        if ($receipt->isFailure() && $receipt->sms_type === 'otp') {
            // Un OTP non délivré empêche une connexion et pousse à multiplier
            // les renvois : on le remonte explicitement.
            Log::warning('OTP non délivré.', [
                'uid' => $uid,
                'recipient' => $recipient,
                'status' => $status,
            ]);
        }

        return true;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseTimestamp(mixed $value): CarbonImmutable
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (Throwable) {
                Log::warning('Webhook SMSpro : horodatage illisible.', ['timestamp' => $value]);
            }
        }

        return CarbonImmutable::now();
    }

    private function isDuplicate(QueryException $e): bool
    {
        // 23000 / 23505 : violation de contrainte d'intégrité (MySQL, SQLite).
        return in_array((string) $e->getCode(), ['23000', '23505'], true);
    }
}
