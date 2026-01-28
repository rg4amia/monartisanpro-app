<?php

namespace App\Domain\Identity\Exceptions;

use Exception;

/**
 * Exception thrown when authentication credentials are invalid
 */
class InvalidCredentialsException extends Exception
{
    public function __construct(string $message = 'Invalid email or password')
    {
        parent::__construct($message);
    }
}
