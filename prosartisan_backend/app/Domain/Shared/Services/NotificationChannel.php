<?php

namespace App\Domain\Shared\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;

interface NotificationChannel
{
 public function send(UserId $userId, string $title, string $message, array $data = []): bool;
 public function getName(): string;
 public function isAvailable(): bool;
}
