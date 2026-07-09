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
        $this->apiToken = config('services.sms.api_token') ?? '';
        $this->baseUrl = config('services.sms.base_url') ?? '';
        $this->provider = config('services.sms.provider', 'log');
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/sms/send', $payload);

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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/sms/campaign', [
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/sms/' . $uid);

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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/sms');

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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/campaign/' . $uid);

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
