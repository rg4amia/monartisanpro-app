<?php

namespace App\Domain\Worksite\Services;

use App\Domain\Worksite\Models\ValueObjects\JalonId;
use DateTime;

/**
 * Domain service for auto-validation of milestones
 *
 * Handles scheduling and processing of auto-validation deadlines
 * Requirements: 6.5
 */
interface AutoValidationService
{
 /**
  * Schedule auto-validation for a milestone
  *
  * Requirement 6.5: Set 48-hour deadline for auto-validation
  */
 public function scheduleAutoValidation(JalonId $jalonId, DateTime $deadline): void;

 /**
  * Process all pending auto-validations
  *
  * Requirement 6.5: Cron job to auto-validate expired milestones
  */
 public function processAutoValidations(): void;
}
