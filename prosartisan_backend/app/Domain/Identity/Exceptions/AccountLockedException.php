<?php

namespace App\Domain\Identity\Exceptions;

use DateTime;
use Exception;

/**
 * Exception thrown when attempting to authenticate a locked account
 */
class AccountLockedException extends Exception
{
    private ?DateTime $lockedUntil;

    public function __construct(?DateTime $lockedUntil = null, string $message = 'Account is temporarily locked due to multiple failed login attempts')
    {
        $this->lockedUntil = $lockedUntil;

        if ($lockedUntil !== null) {
            $message .= '. Please try again after ' . $lockedUntil->format('Y-m-d H:i:s');
        }

        parent::__construct($message);
    }

    public function getLockedUntil(): ?DateTime
    {
        return $this->lockedUntil;
    }
}
