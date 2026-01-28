<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;

/**
 * Interface for SMS sending service
 *
 * Implementations can use various SMS providers:
 * - Twilio
 * - African SMS providers (Orange, MTN)
 * - Local SMS gateways
 */
interface SMSService
{
    /**
     * Send an SMS message to a phone number
     *
     * @param  PhoneNumber  $phone  Recipient phone number
     * @param  string  $message  SMS message content
     * @return bool True if sent successfully
     *
     * @throws \Exception If sending fails
     */
    public function send(PhoneNumber $phone, string $message): bool;
}
