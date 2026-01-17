<?php

namespace App\Domain\Marketplace\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Devis status
 */
final class DevisStatus
{
 public const PENDING = 'PENDING';
 public const ACCEPTED = 'ACCEPTED';
 public const REJECTED = 'REJECTED';

 private const VALID_STATUSES = [
  self::PENDING,
  self::ACCEPTED,
  self::REJECTED,
 ];

 private const FRENCH_LABELS = [
  self::PENDING => 'En attente',
  self::ACCEPTED => 'Accepté',
  self::REJECTED => 'Rejeté',
 ];

 private string $value;

 public function __construct(string $value)
 {
  $this->validateStatus($value);
  $this->value = $value;
 }

 public static function pending(): self
 {
  return new self(self::PENDING);
 }

 public static function accepted(): self
 {
  return new self(self::ACCEPTED);
 }

 public static function rejected(): self
 {
  return new self(self::REJECTED);
 }

 public static function fromString(string $value): self
 {
  return new self($value);
 }

 public function getValue(): string
 {
  return $this->value;
 }

 public function getFrenchLabel(): string
 {
  return self::FRENCH_LABELS[$this->value];
 }

 public function isPending(): bool
 {
  return $this->value === self::PENDING;
 }

 public function isAccepted(): bool
 {
  return $this->value === self::ACCEPTED;
 }

 public function isRejected(): bool
 {
  return $this->value === self::REJECTED;
 }

 public function equals(DevisStatus $other): bool
 {
  return $this->value === $other->value;
 }

 public function __toString(): string
 {
  return $this->value;
 }

 private function validateStatus(string $value): void
 {
  if (!in_array($value, self::VALID_STATUSES, true)) {
   throw new InvalidArgumentException(
    "Invalid devis status: {$value}. Valid statuses are: " . implode(', ', self::VALID_STATUSES)
   );
  }
 }
}
