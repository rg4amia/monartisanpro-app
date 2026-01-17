<?php

namespace App\Domain\Worksite\Services\DTOs;

/**
 * DTO representing the result of proof validation
 */
final class ValidationResult
{
 private bool $isValid;
 private array $errors;
 private array $warnings;

 public function __construct(bool $isValid, array $errors = [], array $warnings = [])
 {
  $this->isValid = $isValid;
  $this->errors = $errors;
  $this->warnings = $warnings;
 }

 public static function valid(array $warnings = []): self
 {
  return new self(true, [], $warnings);
 }

 public static function invalid(array $errors, array $warnings = []): self
 {
  return new self(false, $errors, $warnings);
 }

 public function isValid(): bool
 {
  return $this->isValid;
 }

 public function getErrors(): array
 {
  return $this->errors;
 }

 public function getWarnings(): array
 {
  return $this->warnings;
 }

 public function hasErrors(): bool
 {
  return !empty($this->errors);
 }

 public function hasWarnings(): bool
 {
  return !empty($this->warnings);
 }

 public function toArray(): array
 {
  return [
   'is_valid' => $this->isValid,
   'errors' => $this->errors,
   'warnings' => $this->warnings,
  ];
 }
}
