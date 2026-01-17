<?php

namespace App\Domain\Shared\Services;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;

interface SMSNotificationService extends NotificationChannel
{
 public function sendSMS(PhoneNumber $phoneNumber, string $message): bool;
 public function sendOTP(PhoneNumber $phoneNumber, string $code): bool;
}
