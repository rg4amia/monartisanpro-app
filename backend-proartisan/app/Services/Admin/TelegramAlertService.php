<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chantier C7 (P2-12) — envoi d'alertes d'observabilité vers Telegram.
 *
 * Silencieux (retourne false) si `services.telegram.bot_token` ou
 * `services.telegram.chat_id` ne sont pas configurés : l'observabilité reste
 * consultable dans le backoffice même sans canal Telegram.
 */
class TelegramAlertService
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token'))
            && filled(config('services.telegram.chat_id'));
    }

    public function send(string $message): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = config('services.telegram.bot_token');

        try {
            $response = Http::asJson()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => config('services.telegram.chat_id'),
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                Log::warning('[TelegramAlert] Échec envoi', ['status' => $response->status()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[TelegramAlert] Exception', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
