<?php

namespace App\Domain\Identity\Models\ValueObjects;

use DateTime;
use InvalidArgumentException;

/**
 * Value object representing a One-Time Password
 */
class OTP
{
    private string $code;

    private PhoneNumber $phoneNumber;

    private DateTime $expiresAt;

    private DateTime $createdAt;

    public function __construct(
        string $code,
        PhoneNumber $phoneNumber,
        DateTime $expiresAt,
        ?DateTime $createdAt = null
    ) {
        if (empty($code)) {
            throw new InvalidArgumentException('OTP code cannot be empty');
        }

        if (! preg_match('/^\d{6}$/', $code)) {
            throw new InvalidArgumentException('OTP code must be exactly 6 digits');
        }

        $this->code = $code;
        $this->phoneNumber = $phoneNumber;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt ?? new DateTime;
    }

    /**
     * Generate a new OTP with 5-minute expiration
     */
    public static function generate(PhoneNumber $phoneNumber): self
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = new DateTime('+5 minutes');

        return new self($code, $phoneNumber, $expiresAt);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return new DateTime >= $this->expiresAt;
    }

    public function verify(string $code): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return $this->code === $code;
    }

    public function toString(): string
    {
        return $this->code;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
