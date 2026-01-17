<?php

namespace App\Domain\Financial\Services;

use DateTime;

/**
 * Value object representing the result of a mobile money transaction
 *
 * Contains the response from mobile money providers after initiating
 * a payment, transfer, or refund operation.
 */
final class MobileMoneyTransactionResult
{
 private bool $success;
 private ?string $providerTransactionId;
 private ?string $providerReference;
 private string $status; // PENDING, COMPLETED, FAILED
 private ?string $errorMessage;
 private ?string $errorCode;
 private array $metadata;
 private DateTime $timestamp;

 public function __construct(
  bool $success,
  string $status,
  ?string $providerTransactionId = null,
  ?string $providerReference = null,
  ?string $errorMessage = null,
  ?string $errorCode = null,
  array $metadata = [],
  ?DateTime $timestamp = null
 ) {
  $this->success = $success;
  $this->status = $status;
  $this->providerTransactionId = $providerTransactionId;
  $this->providerReference = $providerReference;
  $this->errorMessage = $errorMessage;
  $this->errorCode = $errorCode;
  $this->metadata = $metadata;
  $this->timestamp = $timestamp ?? new DateTime();
 }

 public static function success(
  string $providerTransactionId,
  string $status = 'PENDING',
  ?string $providerReference = null,
  array $metadata = []
 ): self {
  return new self(
   true,
   $status,
   $providerTransactionId,
   $providerReference,
   null,
   null,
   $metadata
  );
 }

 public static function failure(
  string $errorMessage,
  ?string $errorCode = null,
  array $metadata = []
 ): self {
  return new self(
   false,
   'FAILED',
   null,
   null,
   $errorMessage,
   $errorCode,
   $metadata
  );
 }

 public function isSuccess(): bool
 {
  return $this->success;
 }

 public function isFailure(): bool
 {
  return !$this->success;
 }

 public function getProviderTransactionId(): ?string
 {
  return $this->providerTransactionId;
 }

 public function getProviderReference(): ?string
 {
  return $this->providerReference;
 }

 public function getStatus(): string
 {
  return $this->status;
 }

 public function getErrorMessage(): ?string
 {
  return $this->errorMessage;
 }

 public function getErrorCode(): ?string
 {
  return $this->errorCode;
 }

 public function getMetadata(): array
 {
  return $this->metadata;
 }

 public function getTimestamp(): DateTime
 {
  return $this->timestamp;
 }

 public function toArray(): array
 {
  return [
   'success' => $this->success,
   'status' => $this->status,
   'provider_transaction_id' => $this->providerTransactionId,
   'provider_reference' => $this->providerReference,
   'error_message' => $this->errorMessage,
   'error_code' => $this->errorCode,
   'metadata' => $this->metadata,
   'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
  ];
 }
}
