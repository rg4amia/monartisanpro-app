<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $apiToken;
    private string $baseUrl;
    private string $provider;

    public function __construct()
    {
        $this->apiToken = trim(config('services.sms.api_token') ?? '');
        $this->baseUrl = rtrim(trim(config('services.sms.base_url') ?? ''), '/');
        $this->provider = config('services.sms.provider', 'log');
    }

    /**
     * Build HTTP client with auth headers (+ SSL bypass in local env)
     */
    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
        ]);

        // Bypass SSL verification in local/testing (Windows WAMP cURL error 60)
        if (app()->environment('local', 'testing')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Normalise un numéro de téléphone au format international (225XXXXXXXXXX)
     *
     * Formats acceptés :
     *   - 0141498409       → 2250141498409
     *   - 0700000001       → 2250700000001
     *   - +2250141498409   → 2250141498409
     *   - 002250141498409  → 2250141498409
     *   - 2250141498409    → 2250141498409 (déjà correct)
     *
     * @param string $phone Numéro brut
     * @param string $countryCode Indicatif pays (défaut : 225 pour la Côte d'Ivoire)
     * @return string Numéro normalisé
     */
    public function normalizePhone(string $phone, string $countryCode = '225'): string
    {
        // Supprimer espaces, tirets, points, parenthèses
        $phone = preg_replace('/[\s\-\.\(\)]+/', '', $phone);

        // Supprimer le préfixe "+"
        $phone = ltrim($phone, '+');

        // Supprimer le préfixe "00" international
        if (str_starts_with($phone, '00' . $countryCode)) {
            $phone = substr($phone, 2);
        }

        // Si le numéro commence déjà par l'indicatif pays, le retourner tel quel
        if (str_starts_with($phone, $countryCode) && strlen($phone) >= 12) {
            return $phone;
        }

        // Numéro local (commence par 0 ou directement par les chiffres)
        // Formats CI : 01, 05, 07, 27, 21, 25, etc.
        if (str_starts_with($phone, '0')) {
            return $countryCode . $phone;
        }

        // Numéro court sans 0 initial (ex: 700000001) → ajouter indicatif
        return $countryCode . $phone;
    }

    /**
     * Normalise un ou plusieurs numéros de téléphone
     *
     * @param string|array $recipients Numéro(s) brut(s)
     * @return string|array Numéro(s) normalisé(s)
     */
    private function normalizeRecipients(string|array $recipients): string|array
    {
        if (is_array($recipients)) {
            return array_map(fn(string $phone) => $this->normalizePhone($phone), $recipients);
        }

        // Gère le cas d'une chaîne avec plusieurs numéros séparés par des virgules
        if (str_contains($recipients, ',')) {
            $phones = array_map('trim', explode(',', $recipients));
            return implode(',', array_map(fn(string $phone) => $this->normalizePhone($phone), $phones));
        }

        return $this->normalizePhone($recipients);
    }

    /**
     * Send SMS to single or multiple recipients
     *
     * @param string|array $recipient Phone number(s)
     * @param string $message Message content
     * @param string $senderId Sender ID (max 11 chars)
     * @param string|null $scheduleTime Optional schedule time (Y-m-d H:i)
     * @return array
     */
    public function send(
        string|array $recipient,
        string $message,
        string $senderId = 'ProsArtisan',
        ?string $scheduleTime = null
    ): array {
        // Normaliser le(s) numéro(s) au format international
        $recipient = $this->normalizeRecipients($recipient);

        // Log mode for development
        if ($this->provider === 'log') {
            Log::info('SMS (log mode)', [
                'recipient' => $recipient,
                'sender_id' => $senderId,
                'message' => $message,
                'schedule_time' => $scheduleTime,
            ]);

            return [
                'status' => 'success',
                'data' => [
                    'mode' => 'log',
                    'recipient' => $recipient,
                    'message' => $message,
                ],
            ];
        }

        // Prepare recipient string
        $recipientString = is_array($recipient) ? implode(',', $recipient) : $recipient;

        // Build payload
        $payload = [
            'recipient' => $recipientString,
            'sender_id' => $senderId,
            'type' => 'plain',
            'message' => $message,
        ];

        if ($scheduleTime) {
            $payload['schedule_time'] = $scheduleTime;
        }

        try {
            $response = $this->httpClient()->post($this->baseUrl . '/sms/send', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('SMS API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to send SMS',
                'http_status' => $response->status(),
                'api_response' => $response->json() ?? $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('SMS Exception', [
                'message' => $e->getMessage(),
                'recipient' => $recipientString,
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send campaign to contact list
     *
     * @param string|array $contactListId Contact list UID(s)
     * @param string $message Message content
     * @param string $senderId Sender ID
     * @return array
     */
    public function sendCampaign(
        string|array $contactListId,
        string $message,
        string $senderId = 'ProsArtisan'
    ): array {
        if ($this->provider === 'log') {
            Log::info('SMS Campaign (log mode)', [
                'contact_list_id' => $contactListId,
                'sender_id' => $senderId,
                'message' => $message,
            ]);

            return [
                'status' => 'success',
                'data' => ['mode' => 'log', 'contact_list_id' => $contactListId],
            ];
        }

        $contactListString = is_array($contactListId) ? implode(',', $contactListId) : $contactListId;

        try {
            $response = $this->httpClient()->post($this->baseUrl . '/sms/campaign', [
                'contact_list_id' => $contactListString,
                'sender_id' => $senderId,
                'type' => 'plain',
                'message' => $message,
            ]);

            return $response->successful() ? $response->json() : [
                'status' => 'error',
                'message' => 'Failed to send campaign',
            ];
        } catch (\Exception $e) {
            Log::error('SMS Campaign Exception', ['message' => $e->getMessage()]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * View SMS details by UID
     *
     * @param string $uid SMS UID
     * @return array
     */
    public function view(string $uid): array
    {
        if ($this->provider === 'log') {
            return ['status' => 'success', 'data' => ['mode' => 'log', 'uid' => $uid]];
        }

        try {
            $response = $this->httpClient()->get($this->baseUrl . '/sms/' . $uid);

            return $response->successful() ? $response->json() : [
                'status' => 'error',
                'message' => 'SMS not found',
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * View all messages with pagination
     *
     * @return array
     */
    public function viewAll(): array
    {
        if ($this->provider === 'log') {
            return ['status' => 'success', 'data' => ['mode' => 'log', 'messages' => []]];
        }

        try {
            $response = $this->httpClient()->get($this->baseUrl . '/sms');

            return $response->successful() ? $response->json() : [
                'status' => 'error',
                'message' => 'Failed to retrieve messages',
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * View campaign details by UID
     *
     * @param string $uid Campaign UID
     * @return array
     */
    public function viewCampaign(string $uid): array
    {
        if ($this->provider === 'log') {
            return ['status' => 'success', 'data' => ['mode' => 'log', 'uid' => $uid]];
        }

        try {
            $response = $this->httpClient()->get($this->baseUrl . '/campaign/' . $uid);

            return $response->successful() ? $response->json() : [
                'status' => 'error',
                'message' => 'Campaign not found',
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Send OTP code
     *
     * @param string $phone Phone number
     * @param string $code OTP code
     * @return array
     */
    public function sendOtp(string $phone, string $code): array
    {
        $message = "Votre code de vérification ProsArtisan est: {$code}. Valide pendant 10 minutes.";
        return $this->send($phone, $message);
    }
}
