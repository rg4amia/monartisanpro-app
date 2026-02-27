<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Envoie une notification à un utilisateur.
     * Stocke en base + envoie FCM si token disponible (stub log pour dev).
     */
    public function send(User $user, string $type, string $title, string $body, array $data = []): void
    {
        Notification::create([
            'user_id'   => $user->id,
            'type'      => $type,
            'title'     => $title,
            'body'      => $body,
            'data_json' => $data,
        ]);

        if ($user->fcm_token) {
            Log::info("[FCM STUB] To: {$user->fcm_token} | {$title}: {$body}");
            // En production : appel Firebase FCM API
        }
    }

    /**
     * Envoie une notification aux admins.
     */
    public function sendAdmin(string $type, string $title, string $body, array $data = []): void
    {
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $this->send($admin, $type, $title, $body, $data);
        }
    }
}
