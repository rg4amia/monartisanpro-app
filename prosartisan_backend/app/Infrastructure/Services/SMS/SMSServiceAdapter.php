<?php

namespace App\Infrastructure\Services\SMS;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Services\SMSService;
use App\Domain\Shared\Services\SMSNotificationService;

/**
 * Adapter to bridge SMSNotificationService to SMSService interface
 * This allows reusing existing SMS notification services for the GPS utility service
 */
class SMSServiceAdapter implements SMSService
{
    private SMSNotificationService $smsNotificationService;

    public function __construct(SMSNotificationService $smsNotificationService)
    {
        $this->smsNotificationService = $smsNotificationService;
    }

    /**
     * Send an SMS message to a phone number
     *
     * @param  PhoneNumber  $phone  Recipient phone number
     * @param  string  $message  SMS message content
     * @return bool True if sent successfully
     *
     * @throws \Exception If sending fails
     */
    public function send(PhoneNumber $phone, string $message): bool
    {
        return $this->smsNotificationService->sendSMS($phone, $message);
    }
}
