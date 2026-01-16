<?php

namespace App\Domain\Identity\Models\ValueObjects;

use DateTime;
use InvalidArgumentException;

/**
 * Value object representing an authentication token (JWT)
 */
class AuthToken
{
 private string $token;
 private DateTime $expiresAt;

 public function __construct(string $token, DateTime $expiresAt)
 {
  if (empty($token)) {
   throw new InvalidArgumentException('Token cannot be empty');
  }

  $this->token = $token;
  $this->expiresAt = $expiresAt;
 }

 public function getToken(): string
 {
  return $this->token;
 }

 public function getExpiresAt(): DateTime
 {
  return $this->expiresAt;
 }

 public function isExpired(): bool
 {
  return new DateTime() >= $this->expiresAt;
 }

 public function toString(): string
 {
  return $this->token;
 }

 public function __toString(): string
 {
  return $this->toString();
 }
}
