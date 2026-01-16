<?php

namespace App\Domain\Identity\Models\ValueObjects;

use DateTime;

/**
 * Value Object representing the result of KYC verification
 */
final class KYCVerificationResult
{
 private bool $isVerified;
 private array $validationErrors;
 private ?DateTime $verifiedAt;

 private function __construct(
  bool $isVerified,
  array $validationErrors = [],
  ?DateTime $verifiedAt = null
 ) {
  $this->isVerified = $isVerified;
  $this->validationErrors = $validationErrors;
  $this->verifiedAt = $verifiedAt;
 }

 public static function success(): self
 {
  return new self(
   isVerified: true,
   validationErrors: [],
   verifiedAt: new DateTime()
  );
 }

 public static function failure(array $validationErrors): self
 {
  if (empty($validationErrors)) {
   throw new \InvalidArgumentException('Validation errors cannot be empty for a failed verification');
  }

  return new self(
   isVerified: false,
   validationErrors: $validationErrors,
   verifiedAt: null
  );
 }

 public function isVerified(): bool
 {
  return $this->isVerified;
 }

 public function getValidationErrors(): array
 {
  return $this->validationErrors;
 }

 public function getVerifiedAt(): ?DateTime
 {
  return $this->verifiedAt;
 }

 public function hasErrors(): bool
 {
  return !empty($this->validationErrors);
 }

 public function toArray(): array
 {
  return [
   'is_verified' => $this->isVerified,
   'validation_errors' => $this->validationErrors,
   'verified_at' => $this->verifiedAt?->format('Y-m-d H:i:s'),
  ];
 }
}
