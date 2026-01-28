<?php

namespace App\Domain\Identity\Exceptions;

use Exception;

/**
 * Exception thrown when attempting to authenticate a suspended account
 */
class AccountSuspendedException extends Exception
{
    public function __construct(string $message = 'Account has been suspended. Please contact support.')
    {
        parent::__construct($message);
    }
}
