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
     * @param  string  $userId  L'ID de l'utilisateur (external_id dans OneSignal)
     * @param  string  $heading  Titre de la notification
     * @param  string  $content  Contenu de la notification
     * @param  array  $data  Données additionnelles invisibles
     * @param  string|null  $androidSound  Nom (sans extension) d'un fichier son dans android/app/src/main/res/raw,
     *                                     pour une sonnerie de notification distincte. Passer 'null' (chaîne littérale, convention OneSignal)
     *                                     pour couper le son. Omis = son système par défaut.
     */
    public function sendToUser(string $userId, string $heading, string $content, array $data = [], ?string $androidSound = null): bool
    {
        return $this->deliver($userId, $heading, $content, $data, $androidSound)['status'] === 'envoye';
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->restApiKey !== '';
    }

    /**
     * Même envoi que {@see sendToUser()}, avec l'issue détaillée pour le
     * journal des envois : `envoye`, `echoue` (motif) ou `ignore` (OneSignal
     * non configuré).
     *
     * @return array{status: 'envoye'|'echoue'|'ignore', reason: ?string}
     */
    public function deliver(string $userId, string $heading, string $content, array $data = [], ?string $androidSound = null): array
    {
        if (! $this->isConfigured()) {
            Log::warning('OneSignal non configuré. Notification ignorée.', ['user_id' => $userId]);

            return ['status' => 'ignore', 'reason' => 'OneSignal non configuré'];
        }

        try {
            $payload = [
                'app_id' => $this->appId,
                'include_aliases' => [
                    'external_id' => [(string) $userId],
                ],
                'target_channel' => 'push',
                'include_external_user_ids' => [(string) $userId],
                'channel_for_external_user_ids' => 'push',
                'headings' => ['en' => $heading, 'fr' => $heading],
                'contents' => ['en' => $content, 'fr' => $content],
                // Objet JSON attendu : un tableau vide se sérialiserait en [].
                'data' => (object) $data,
            ];

            if ($androidSound !== null) {
                $payload['android_sound'] = $androidSound;
            }

            $response = Http::timeout(3)
                ->connectTimeout(2)
                ->withHeaders([
                    'Authorization' => 'Basic '.$this->restApiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl, $payload);

            if ($response->successful()) {
                Log::info("Notification OneSignal envoyée avec succès à l'utilisateur $userId");

                return ['status' => 'envoye', 'reason' => null];
            }

            Log::error("Erreur d'envoi OneSignal", [
                'user_id' => $userId,
                'response' => $response->json(),
            ]);

            $errors = $response->json('errors');

            return [
                'status' => 'echoue',
                'reason' => mb_substr('HTTP '.$response->status().(is_array($errors) ? ' : '.implode(' ; ', array_map('strval', $errors)) : ''), 0, 500),
            ];
        } catch (\Throwable $e) {
            Log::error("Exception lors de l'envoi de la notification OneSignal : ".$e->getMessage());

            return ['status' => 'echoue', 'reason' => mb_substr($e->getMessage(), 0, 500)];
        }
    }
}
