<?php

namespace App\Domain\Shared\Services;

interface PushNotificationService extends NotificationChannel
{
    public function sendToDevice(string $deviceToken, string $title, string $message, array $data = []): bool;

    public function sendToTopic(string $topic, string $title, string $message, array $data = []): bool;

    public function subscribeToTopic(string $deviceToken, string $topic): bool;

    public function unsubscribeFromTopic(string $deviceToken, string $topic): bool;
}
