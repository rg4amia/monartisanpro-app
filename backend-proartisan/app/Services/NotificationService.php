<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class NotificationService
{
    /**
     * Envoie une notification via plusieurs canaux (Base, Push, SMS).
     */
    public function send(User $user, string $type, string $title, string $body, array $data = []): void
    {
        // 1. Enregistrement en base (toujours)
        Notification::create([
            'user_id'   => $user->id,
            'type'      => $type,
            'title'     => $title,
            'body'      => $body,
            'data_json' => $data,
        ]);

        // 2. Push Notification (FCM)
        if ($user->fcm_token) {
            $this->sendPush($user->fcm_token, $title, $body, $data);
        }

        // 3. SMS pour les types critiques (OTP, Paiement, Alerte fraude)
        $criticalTypes = ['otp', 'payment', 'fraud_alert', 'litige'];
        if (in_array($type, $criticalTypes)) {
            $this->sendSms($user->phone, "{$title}: {$body}");
        }
    }

    /**
     * Envoie une notification aux administrateurs.
     */
    public function sendAdmin(string $type, string $title, string $body, array $data = []): void
    {
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->send($admin, $type, $title, $body, $data);
        }
    }

    private function sendPush(string $token, string $title, string $body, array $data): void
    {
        Log::info("[FCM] Envoi push à {$token}", ['title' => $title, 'body' => $body]);

        // Simuler appel Firebase
        // if (config('services.fcm.key')) { ... }
    }

    private function sendSms(string $phone, string $message): void
    {
        $smsService = app(SmsService::class);
        $smsService->send($phone, $message);
    }
}
