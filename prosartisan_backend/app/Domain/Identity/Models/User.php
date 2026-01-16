<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use DateTime;
use InvalidArgumentException;

/**
 * Base User entity representing a user in the system
 * This is the base class for all user types (Client, Artisan, Fournisseur, etc.)
 */
class User
{
 protected UserId $id;
 protected Email $email;
 protected HashedPassword $password;
 protected UserType $type;
 protected AccountStatus $status;
 protected ?KYCDocuments $kycDocuments;
 protected DateTime $createdAt;
 protected DateTime $updatedAt;
 protected int $failedLoginAttempts;
 protected ?DateTime $lockedUntil;

 public function __construct(
  UserId $id,
  Email $email,
  HashedPassword $password,
  UserType $type,
  ?AccountStatus $status = null,
  ?KYCDocuments $kycDocuments = null,
  ?DateTime $createdAt = null,
  ?DateTime $updatedAt = null
 ) {
  $this->id = $id;
  $this->email = $email;
  $this->password = $password;
  $this->type = $type;
  $this->status = $status ?? AccountStatus::PENDING();
  $this->kycDocuments = $kycDocuments;
  $this->createdAt = $createdAt ?? new DateTime();
  $this->updatedAt = $updatedAt ?? new DateTime();
  $this->failedLoginAttempts = 0;
  $this->lockedUntil = null;
 }

 /**
  * Create a new user with generated ID
  */
 public static function create(
  Email $email,
  HashedPassword $password,
  UserType $type,
  ?KYCDocuments $kycDocuments = null
 ): self {
  return new self(
   UserId::generate(),
   $email,
   $password,
   $type,
   AccountStatus::PENDING(),
   $kycDocuments
  );
 }

 /**
  * Verify KYC documents and activate account
  */
 public function verifyKYC(KYCDocuments $documents): void
 {
  $this->kycDocuments = $documents;
  $this->activate();
  $this->updatedAt = new DateTime();
 }

 /**
  * Activate the user account
  */
 public function activate(): void
 {
  $this->status = AccountStatus::ACTIVE();
  $this->updatedAt = new DateTime();
 }

 /**
  * Suspend the user account
  */
 public function suspend(string $reason): void
 {
  if ($this->status->isSuspended()) {
   throw new InvalidArgumentException('Account is already suspended');
  }

  $this->status = AccountStatus::SUSPENDED();
  $this->updatedAt = new DateTime();
 }

 /**
  * Check if account is locked due to failed login attempts
  */
 public function isLocked(): bool
 {
  if ($this->lockedUntil === null) {
   return false;
  }

  $now = new DateTime();
  if ($now >= $this->lockedUntil) {
   // Lock period has expired, unlock the account
   $this->unlock();
   return false;
  }

  return true;
 }

 /**
  * Record a failed login attempt
  * Lock account for 15 minutes after 3 failed attempts
  */
 public function recordFailedLoginAttempt(): void
 {
  $this->failedLoginAttempts++;
  $this->updatedAt = new DateTime();

  if ($this->failedLoginAttempts >= 3) {
   $this->lockAccount();
  }
 }

 /**
  * Reset failed login attempts after successful login
  */
 public function resetFailedLoginAttempts(): void
 {
  $this->failedLoginAttempts = 0;
  $this->updatedAt = new DateTime();
 }

 /**
  * Lock account for 15 minutes
  */
 private function lockAccount(): void
 {
  $this->lockedUntil = new DateTime('+15 minutes');
  $this->updatedAt = new DateTime();
 }

 /**
  * Unlock the account
  */
 private function unlock(): void
 {
  $this->lockedUntil = null;
  $this->failedLoginAttempts = 0;
  $this->updatedAt = new DateTime();
 }

 /**
  * Verify password
  */
 public function verifyPassword(string $plainPassword): bool
 {
  return $this->password->verify($plainPassword);
 }

 /**
  * Change password
  */
 public function changePassword(HashedPassword $newPassword): void
 {
  $this->password = $newPassword;
  $this->updatedAt = new DateTime();
 }

 /**
  * Check if user has KYC documents
  */
 public function hasKYCDocuments(): bool
 {
  return $this->kycDocuments !== null;
 }

 /**
  * Check if account is active
  */
 public function isActive(): bool
 {
  return $this->status->isActive();
 }

 /**
  * Check if account is suspended
  */
 public function isSuspended(): bool
 {
  return $this->status->isSuspended();
 }

 /**
  * Check if account is pending
  */
 public function isPending(): bool
 {
  return $this->status->isPending();
 }

 // Getters

 public function getId(): UserId
 {
  return $this->id;
 }

 public function getEmail(): Email
 {
  return $this->email;
 }

 public function getPassword(): HashedPassword
 {
  return $this->password;
 }

 public function getType(): UserType
 {
  return $this->type;
 }

 public function getStatus(): AccountStatus
 {
  return $this->status;
 }

 public function getKYCDocuments(): ?KYCDocuments
 {
  return $this->kycDocuments;
 }

 public function getCreatedAt(): DateTime
 {
  return $this->createdAt;
 }

 public function getUpdatedAt(): DateTime
 {
  return $this->updatedAt;
 }

 public function getFailedLoginAttempts(): int
 {
  return $this->failedLoginAttempts;
 }

 public function getLockedUntil(): ?DateTime
 {
  return $this->lockedUntil;
 }
}
