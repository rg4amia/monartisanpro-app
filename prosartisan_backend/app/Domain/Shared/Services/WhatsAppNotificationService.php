<?php

namespace App\Domain\Shared\Services;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;

interface WhatsAppNotificationService extends NotificationChannel
{
 public function sendMessage(PhoneNumber $phoneNumber, string $message): bool;
 public function sendTemplate(PhoneNumber $phoneNumber, string $templateName, array $parameters = []): bool;
 public function sendMedia(PhoneNumber $phoneNumber, string $mediaUrl, string $caption = ''): bool;
}
