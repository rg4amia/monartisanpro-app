<?php

namespace App\Domain\Identity\Exceptions;

use Exception;

/**
 * Exception thrown when OTP generation or sending fails
 */
class OTPGenerationException extends Exception
{
    public function __construct(string $message = 'Failed to generate or send OTP', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
