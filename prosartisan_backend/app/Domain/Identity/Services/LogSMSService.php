<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * SMS service implementation that logs messages instead of sending
 *
 * Useful for development and testing environments
 */
class LogSMSService implements SMSService
{
    /**
     * {@inheritDoc}
     */
    public function send(PhoneNumber $phone, string $message): bool
    {
        Log::info('SMS sent', [
            'phone' => $phone->getValue(),
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ]);

        return true;
    }
}
