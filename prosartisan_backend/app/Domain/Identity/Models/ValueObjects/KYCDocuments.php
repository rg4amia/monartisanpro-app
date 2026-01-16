<?php

namespace App\Domain\Identity\Models\ValueObjects;

use DateTime;
use InvalidArgumentException;

/**
 * Value Object representing KYC (Know Your Customer) documents
 * Required for Artisan and Fournisseur verification
 */
final class KYCDocuments
{
 private const ID_TYPE_CNI = 'CNI';
 private const ID_TYPE_PASSPORT = 'PASSPORT';

 private const VALID_ID_TYPES = [
  self::ID_TYPE_CNI,
  self::ID_TYPE_PASSPORT,
 ];

 private string $idType;
 private string $idNumber;
 private string $idDocumentUrl;
 private string $selfieUrl;
 private DateTime $submittedAt;

 public function __construct(
  string $idType,
  string $idNumber,
  string $idDocumentUrl,
  string $selfieUrl,
  ?DateTime $submittedAt = null
 ) {
  $this->validateIdType($idType);
  $this->validateIdNumber($idNumber);
  $this->validateUrl($idDocumentUrl, 'ID document URL');
  $this->validateUrl($selfieUrl, 'Selfie URL');

  $this->idType = strtoupper($idType);
  $this->idNumber = $idNumber;
  $this->idDocumentUrl = $idDocumentUrl;
  $this->selfieUrl = $selfieUrl;
  $this->submittedAt = $submittedAt ?? new DateTime();
 }

 public static function fromArray(array $data): self
 {
  return new self(
   $data['id_type'] ?? throw new InvalidArgumentException('ID type is required'),
   $data['id_number'] ?? throw new InvalidArgumentException('ID number is required'),
   $data['id_document_url'] ?? throw new InvalidArgumentException('ID document URL is required'),
   $data['selfie_url'] ?? throw new InvalidArgumentException('Selfie URL is required'),
   isset($data['submitted_at']) ? new DateTime($data['submitted_at']) : null
  );
 }

 public function getIdType(): string
 {
  return $this->idType;
 }

 public function getIdNumber(): string
 {
  return $this->idNumber;
 }

 public function getIdDocumentUrl(): string
 {
  return $this->idDocumentUrl;
 }

 public function getSelfieUrl(): string
 {
  return $this->selfieUrl;
 }

 public function getSubmittedAt(): DateTime
 {
  return $this->submittedAt;
 }

 public function isCNI(): bool
 {
  return $this->idType === self::ID_TYPE_CNI;
 }

 public function isPassport(): bool
 {
  return $this->idType === self::ID_TYPE_PASSPORT;
 }

 public function toArray(): array
 {
  return [
   'id_type' => $this->idType,
   'id_number' => $this->idNumber,
   'id_document_url' => $this->idDocumentUrl,
   'selfie_url' => $this->selfieUrl,
   'submitted_at' => $this->submittedAt->format('Y-m-d H:i:s'),
  ];
 }

 private function validateIdType(string $idType): void
 {
  $idType = strtoupper($idType);

  if (!in_array($idType, self::VALID_ID_TYPES, true)) {
   throw new InvalidArgumentException(
    "Invalid ID type: {$idType}. Must be one of: " . implode(', ', self::VALID_ID_TYPES)
   );
  }
 }

 private function validateIdNumber(string $idNumber): void
 {
  if (empty(trim($idNumber))) {
   throw new InvalidArgumentException('ID number cannot be empty');
  }

  if (strlen($idNumber) < 5) {
   throw new InvalidArgumentException('ID number must be at least 5 characters long');
  }
 }

 private function validateUrl(string $url, string $fieldName): void
 {
  if (empty(trim($url))) {
   throw new InvalidArgumentException("{$fieldName} cannot be empty");
  }

  // Basic URL validation - can be enhanced based on storage solution
  if (!filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
   throw new InvalidArgumentException("{$fieldName} must be a valid URL or path");
  }
 }
}
