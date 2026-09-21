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
            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept' => 'application/json',
            ]);

            // Bypass SSL verification in local/testing (Windows WAMP cURL error 60) —
            // même contournement que SmsService::httpClient(), jamais porté ici.
            if (app()->environment('local', 'testing')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($this->baseUrl . '/whatsapp/send', [
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
        $ttl = (int) config('prosartisan.otp.ttl', 5);

        $message = "Votre code de vérification ProsArtisan est: {$code}. "
            ."Valide {$ttl} minutes. Ne le communiquez jamais : ProsArtisan ne vous le demandera pas.";

        return $this->send($phone, $message);
    }
}
