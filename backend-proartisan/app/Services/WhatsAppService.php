<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiToken;
    private string $baseUrl;
    private string $provider;

    public function __construct()
    {
        $this->apiToken = config('services.whatsapp.api_token') ?? '';
        $this->baseUrl = config('services.whatsapp.base_url') ?? '';
        $this->provider = config('services.whatsapp.provider', 'log');
    }

    /**
     * Send WhatsApp message to recipient
     *
     * @param string $recipient Phone number
     * @param string $message Message content
     * @return array
     */
    public function send(string $recipient, string $message): array
    {
        if ($this->provider === 'log') {
            Log::info('WhatsApp OTP (log mode)', [
                'recipient' => $recipient,
                'message' => $message,
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

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/whatsapp/send', [
                'recipient' => $recipient,
                'message' => $message,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('WhatsApp API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to send WhatsApp message',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Exception', [
                'message' => $e->getMessage(),
                'recipient' => $recipient,
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send OTP code via WhatsApp
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
