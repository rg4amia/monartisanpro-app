<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\OneSignalService;
use App\Services\SmsService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Envoi push et SMS d'une notification, exécuté juste après la réponse HTTP
 * (dispatchAfterResponse) : l'utilisateur n'attend plus OneSignal ni la
 * passerelle SMS, et aucun worker de file d'attente n'est requis
 * (Chantier 14, lot B). Chaque tentative est consignée dans
 * `notification_deliveries`.
 */
class DeliverNotificationJob
{
    use Dispatchable;

    /**
     * @param  array{title: string, body: string, data: array<string, mixed>, sound: ?string}|null  $push
     */
    public function __construct(
        public int $userId,
        public ?int $notificationId,
        public string $event,
        public ?array $push,
        public ?string $sms,
        public ?string $smsPhone = null,
    ) {}

    public function handle(OneSignalService $oneSignal, SmsService $smsService): void
    {
        // La notification in-app a été créée dans une transaction annulée
        // depuis : le message ne correspond plus à rien, on ne l'envoie pas.
        if ($this->notificationId !== null && ! Notification::whereKey($this->notificationId)->exists()) {
            return;
        }

        $user = User::withTrashed()->find($this->userId);

        if ($this->push !== null) {
            $this->deliverPush($oneSignal);
        }

        if ($this->sms !== null) {
            $this->deliverSms($smsService, $user);
        }
    }

    private function deliverPush(OneSignalService $oneSignal): void
    {
        $result = $oneSignal->deliver(
            (string) $this->userId,
            $this->push['title'],
            $this->push['body'],
            $this->push['data'],
            $this->push['sound'],
        );

        $this->record(NotificationDelivery::CHANNEL_PUSH, 'onesignal', $result['status'], $result['reason']);
    }

    private function deliverSms(SmsService $smsService, ?User $user): void
    {
        $provider = $smsService->getProvider();

        $phone = $this->smsPhone ?? $user?->phone;

        if ($user === null || $user->anonymized_at !== null || blank($phone)) {
            $this->record(NotificationDelivery::CHANNEL_SMS, $provider, NotificationDelivery::STATUS_SKIPPED, 'Aucun numéro de téléphone');

            return;
        }

        try {
            $response = $smsService->send($phone, $this->sms);

            if (($response['status'] ?? null) === 'error') {
                $this->record(NotificationDelivery::CHANNEL_SMS, $provider, NotificationDelivery::STATUS_FAILED, (string) ($response['message'] ?? 'Échec de la passerelle SMS'));

                return;
            }

            $this->record(NotificationDelivery::CHANNEL_SMS, $provider, NotificationDelivery::STATUS_SENT, null);
        } catch (\Throwable $e) {
            Log::error('[Notifications] Envoi SMS impossible : '.$e->getMessage(), ['event' => $this->event, 'user_id' => $this->userId]);
            $this->record(NotificationDelivery::CHANNEL_SMS, $provider, NotificationDelivery::STATUS_FAILED, $e->getMessage());
        }
    }

    private function record(string $channel, ?string $provider, string $status, ?string $reason): void
    {
        try {
            NotificationDelivery::create([
                'notification_id' => $this->notificationId,
                'user_id' => $this->userId,
                'event_key' => $this->event,
                'channel' => $channel,
                'provider' => $provider,
                'status' => $status,
                'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            // Le journal ne doit jamais faire échouer un envoi déjà effectué.
            Log::warning('[Notifications] Journal des envois indisponible : '.$e->getMessage());
        }
    }
}
