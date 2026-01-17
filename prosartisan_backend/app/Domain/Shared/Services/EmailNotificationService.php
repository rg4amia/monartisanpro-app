<?php

namespace App\Domain\Shared\Services;

use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\UserId;

interface EmailNotificationService extends NotificationChannel
{
 public function sendEmail(Email $email, string $subject, string $message, array $attachments = []): bool;
 public function sendTemplate(Email $email, string $templateName, array $data = []): bool;
}
