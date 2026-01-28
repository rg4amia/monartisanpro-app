<?php

namespace App\Domain\Identity\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing a hashed password
 */
final class HashedPassword
{
    private string $hash;

    private function __construct(string $hash)
    {
        if (empty($hash)) {
            throw new InvalidArgumentException('Password hash cannot be empty');
        }

        $this->hash = $hash;
    }

    /**
     * Create from a plain text password (will be hashed)
     */
    public static function fromPlainText(string $plainPassword): self
    {
        if (strlen($plainPassword) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long');
        }

        $hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        if ($hash === false) {
            throw new InvalidArgumentException('Failed to hash password');
        }

        return new self($hash);
    }

    /**
     * Alias for fromPlainText for backward compatibility
     */
    public static function fromPlainPassword(string $plainPassword): self
    {
        return self::fromPlainText($plainPassword);
    }

    /**
     * Create from an already hashed password (e.g., from database)
     */
    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    /**
     * Verify if a plain text password matches this hash
     */
    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->hash);
    }

    /**
     * Check if the hash needs to be rehashed (e.g., algorithm changed)
     */
    public function needsRehash(): bool
    {
        return password_needs_rehash($this->hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function toString(): string
    {
        return $this->getHash();
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}
