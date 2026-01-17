<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Services\EncryptionService;
use InvalidArgumentException;

/**
 * Value Object for encrypted payment information
 *
 * Stores sensitive payment data in encrypted format
 *
 * Requirements: 13.1
 */
final class EncryptedPaymentInfo
{
 private string $encryptedData;
 private string $paymentMethod; // Not encrypted - used for routing
 private \DateTime $createdAt;

 public function __construct(
  string $encryptedData,
  string $paymentMethod,
  ?\DateTime $createdAt = null
 ) {
  if (empty($encryptedData)) {
   throw new InvalidArgumentException('Encrypted data cannot be empty');
  }

  if (empty($paymentMethod)) {
   throw new InvalidArgumentException('Payment method cannot be empty');
  }

  $this->encryptedData = $encryptedData;
  $this->paymentMethod = $paymentMethod;
  $this->createdAt = $createdAt ?? new \DateTime();
 }

 /**
  * Create from plain text payment data
  */
 public static function fromPlainText(
  array $paymentData,
  string $paymentMethod,
  EncryptionService $encryptionService
 ): self {
  $jsonData = json_encode($paymentData);
  if ($jsonData === false) {
   throw new InvalidArgumentException('Invalid payment data');
  }

  $encryptedData = $encryptionService->encrypt($jsonData);

  return new self($encryptedData, $paymentMethod);
 }

 /**
  * Decrypt and return payment data
  */
 public function decrypt(EncryptionService $encryptionService): array
 {
  $decryptedJson = $encryptionService->decrypt($this->encryptedData);
  $data = json_decode($decryptedJson, true);

  if ($data === null) {
   throw new \Exception('Failed to decrypt payment data');
  }

  return $data;
 }

 public function getEncryptedData(): string
 {
  return $this->encryptedData;
 }

 public function getPaymentMethod(): string
 {
  return $this->paymentMethod;
 }

 public function getCreatedAt(): \DateTime
 {
  return $this->createdAt;
 }

 /**
  * Check if payment info is expired (older than 1 hour)
  */
 public function isExpired(): bool
 {
  $expiryTime = clone $this->createdAt;
  $expiryTime->add(new \DateInterval('PT1H')); // Add 1 hour

  return new \DateTime() > $expiryTime;
 }

 public function toArray(): array
 {
  return [
   'encrypted_data' => $this->encryptedData,
   'payment_method' => $this->paymentMethod,
   'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
  ];
 }
}
