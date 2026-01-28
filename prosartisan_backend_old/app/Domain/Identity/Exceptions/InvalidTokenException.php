<?php

namespace App\Domain\Identity\Exceptions;

use Exception;

/**
 * Exception thrown when JWT token is invalid or expired
 */
class InvalidTokenException extends Exception
{
    public function __construct(string $message = 'Invalid or expired authentication token')
    {
        parent::__construct($message);
    }
}
