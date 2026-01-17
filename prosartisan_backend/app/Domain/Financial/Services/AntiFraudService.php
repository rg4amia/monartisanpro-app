<?php

namespace App\Domain\Financial\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;

/**
 * Domain service for fraud detection and prevention
 *
 * Implements GPS proximity validation and suspicious activity detection
 *
 * Requirements: 5.3, 13.3, 13.7
 */
interface AntiFraudService
{
 /**
  * Verify GPS proximity between two locations
  *
  * Requirement 5.3: Verify 100m proximity for jeton validation
  *
  * @param GPS_Coordinates $location1
  * @param GPS_Coordinates $location2
  * @param float $maxDistanceMeters
  * @return bool
  */
 public function verifyProximity(GPS_Coordinates $location1, GPS_Coordinates $location2, float $maxDistanceMeters): bool;

 /**
  * Detect suspicious activity patterns
  *
  * Requirement 13.3: Flag suspicious activity for review
  *
  * @param UserId $userId
  * @return bool
  */
 public function detectSuspiciousActivity(UserId $userId): bool;

 /**
  * Check for escrow circumvention attempts
  *
  * Requirement 13.7: Detect escrow circumvention
  *
  * @param UserId $userId
  * @return bool
  */
 public function detectEscrowCircumvention(UserId $userId): bool;

 /**
  * Flag user account for review
  *
  * @param UserId $userId
  * @param string $reason
  * @return void
  */
 public function flagAccountForReview(UserId $userId, string $reason): void;
}
