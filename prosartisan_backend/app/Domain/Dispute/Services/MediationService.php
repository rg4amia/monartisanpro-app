<?php

namespace App\Domain\Dispute\Services;

use App\Domain\Dispute\Models\Litige\Litige;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Identity\Models\ValueObjects\UserId;

/**
 * Service interface for mediation management
 *
 * Requirements: 9.3, 9.5
 */
interface MediationService
{
 /**
  * Assign a mediator to a dispute based on chantier value
  *
  * Requirement 9.3: Assign Référent_de_Zone for high-value disputes (> 2M XOF)
  * or admin-based mediation for lower values
  */
 public function assignMediator(Litige $litige): UserId;

 /**
  * Facilitate dialogue between parties
  *
  * Requirement 9.5: Provide communication channel during mediation
  */
 public function facilitateDialogue(LitigeId $litigeId): void;

 /**
  * Check if a user can serve as mediator for a dispute
  */
 public function canMediate(UserId $mediatorId, Litige $litige): bool;

 /**
  * Get available mediators for a dispute
  */
 public function getAvailableMediators(Litige $litige): array;
}
