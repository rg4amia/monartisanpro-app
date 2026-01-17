<?php

namespace App\Domain\Worksite\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Chantier status
 */
final class ChantierStatus
{
 public const IN_PROGRESS = 'IN_PROGRESS';
 public const COMPLETED = 'COMPLETED';
 public const DISPUTED = 'DISPUTED';

 private const VALID_STATUSES = [
  self::IN_PROGRESS,
  self::COMPLETED,
  self::DISPUTED,
 ];

 private const FRENCH_LABELS = [
  self::IN_PROGRESS => 'En cours',
  self::COMPLETED => 'Terminé',
  self::DISPUTED => 'En litige',
 ];

 private string $value;

 public function __construct(string $value)
 {
  $this->validateStatus($value);
  $this->value = $value;
 }

 public static function inProgress(): self
 {
  return new self(self::IN_PROGRESS);
 }

 public static function completed(): self
 {
  return new self(self::COMPLETED);
 }

 public static function disputed(): self
 {
  return new self(self::DISPUTED);
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

 public function isInProgress(): bool
 {
  return $this->value === self::IN_PROGRESS;
 }

 public function isCompleted(): bool
 {
  return $this->value === self::COMPLETED;
 }

 public function isDisputed(): bool
 {
  return $this->value === self::DISPUTED;
 }

 public function equals(ChantierStatus $other): bool
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
    "Invalid chantier status: {$value}. Valid statuses are: " . implode(', ', self::VALID_STATUSES)
   );
  }
 }
}
