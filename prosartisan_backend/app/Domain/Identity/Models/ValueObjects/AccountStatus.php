<?php

namespace App\Domain\Identity\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing the status of a user account
 */
final class AccountStatus
{
 private const PENDING = 'PENDING';
 private const ACTIVE = 'ACTIVE';
 private const SUSPENDED = 'SUSPENDED';

 private const VALID_STATUSES = [
  self::PENDING,
  self::ACTIVE,
  self::SUSPENDED,
 ];

 private string $value;

 private function __construct(string $value)
 {
  if (!in_array($value, self::VALID_STATUSES, true)) {
   throw new InvalidArgumentException("Invalid account status: {$value}");
  }

  $this->value = $value;
 }

 public static function PENDING(): self
 {
  return new self(self::PENDING);
 }

 public static function ACTIVE(): self
 {
  return new self(self::ACTIVE);
 }

 public static function SUSPENDED(): self
 {
  return new self(self::SUSPENDED);
 }

 public static function fromString(string $value): self
 {
  return new self(strtoupper($value));
 }

 public function getValue(): string
 {
  return $this->value;
 }

 public function isPending(): bool
 {
  return $this->value === self::PENDING;
 }

 public function isActive(): bool
 {
  return $this->value === self::ACTIVE;
 }

 public function isSuspended(): bool
 {
  return $this->value === self::SUSPENDED;
 }

 public function equals(AccountStatus $other): bool
 {
  return $this->value === $other->value;
 }

 public function __toString(): string
 {
  return $this->value;
 }
}
