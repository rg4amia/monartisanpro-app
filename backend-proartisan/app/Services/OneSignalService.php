<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    protected string $appId;
    protected string $restApiKey;
    protected string $baseUrl = 'https://onesignal.com/api/v1/notifications';

    public function __construct()
    {
        $this->appId = trim(config('services.onesignal.app_id', ''));
        $this->restApiKey = trim(config('services.onesignal.rest_api_key', ''));
    }

    /**
     * Envoie une notification push à un utilisateur spécifique (via external_id).
     *
     * @param string $userId L'ID de l'utilisateur (external_id dans OneSignal)
     * @param string $heading Titre de la notification
     * @param string $content Contenu de la notification
     * @param array $data Données additionnelles invisibles
     * @return bool
     */
    public function sendToUser(string $userId, string $heading, string $content, array $data = []): bool
    {
        if (empty($this->appId) || empty($this->restApiKey)) {
            Log::warning('OneSignal non configuré. Notification ignorée.', ['user_id' => $userId]);
            return false;
        }

        try {
            $payload = [
                'app_id' => $this->appId,
                'include_aliases' => [
                    'external_id' => [(string) $userId]
                ],
                'target_channel' => 'push',
                'include_external_user_ids' => [(string) $userId],
                'channel_for_external_user_ids' => 'push',
                'headings' => ['en' => $heading, 'fr' => $heading],
                'contents' => ['en' => $content, 'fr' => $content],
                'data' => $data,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->restApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl, $payload);

            if ($response->successful()) {
                Log::info("Notification OneSignal envoyée avec succès à l'utilisateur $userId");
                return true;
            }

            Log::error("Erreur d'envoi OneSignal", [
                'user_id' => $userId,
                'response' => $response->json()
            ]);
            
            return false;
        } catch (\Exception $e) {
            Log::error("Exception lors de l'envoi de la notification OneSignal : " . $e->getMessage());
            return false;
        }
    }
}
