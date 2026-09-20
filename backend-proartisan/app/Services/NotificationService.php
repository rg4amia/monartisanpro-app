<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class NotificationService
{
    /**
     * Nom (sans extension) de la sonnerie distincte, dans
     * frontend_flutter/android/app/src/main/res/raw/notif_artisan.wav.
     */
    private const DISTINCT_SOUND = 'notif_artisan';

    /**
     * Rôles bénéficiant de la sonnerie distincte (métiers de terrain : artisan
     * intervenant sur chantier, livreur en tournée).
     */
    private const ROLES_WITH_DISTINCT_SOUND = ['artisan', 'livreur'];

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

        // 2. Push Notification via OneSignal (basé sur l'ID utilisateur)
        try {
            $oneSignal = app(\App\Services\OneSignalService::class);
            $androidSound = in_array($user->role, self::ROLES_WITH_DISTINCT_SOUND, true) ? self::DISTINCT_SOUND : null;
            $oneSignal->sendToUser((string) $user->id, $title, $body, $data, $androidSound);
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'appel OneSignal dans NotificationService : " . $e->getMessage());
        }

        // 3. SMS pour les types critiques (OTP, Paiement, Alerte fraude)
        $criticalTypes = ['otp', 'payment', 'fraud_alert', 'litige'];
        if (in_array($type, $criticalTypes)) {
            try {
                $this->sendSms($user->phone, "{$title}: {$body}");
            } catch (\Throwable $e) {
                Log::error("Erreur lors de l'envoi SMS dans NotificationService : " . $e->getMessage());
            }
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
