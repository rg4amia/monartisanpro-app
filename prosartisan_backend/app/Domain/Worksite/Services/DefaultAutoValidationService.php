<?php

namespace App\Domain\Worksite\Services;

use App\Domain\Worksite\Models\ValueObjects\JalonId;
use App\Domain\Worksite\Repositories\JalonRepository;
use DateTime;
use Illuminate\Support\Facades\Log;

/**
 * Default implementation of auto-validation service
 *
 * Requirements: 6.5
 */
final class DefaultAutoValidationService implements AutoValidationService
{
 private JalonRepository $jalonRepository;

 public function __construct(JalonRepository $jalonRepository)
 {
  $this->jalonRepository = $jalonRepository;
 }

 /**
  * Schedule auto-validation for a milestone
  *
  * Requirement 6.5: Set 48-hour deadline for auto-validation
  */
 public function scheduleAutoValidation(JalonId $jalonId, DateTime $deadline): void
 {
  // The scheduling is handled by the Jalon entity itself when proof is submitted
  // This method could be used for external scheduling systems if needed
  Log::info('Auto-validation scheduled', [
   'jalon_id' => $jalonId->getValue(),
   'deadline' => $deadline->format('Y-m-d H:i:s')
  ]);
 }

 /**
  * Process all pending auto-validations
  *
  * Requirement 6.5: Cron job to auto-validate expired milestones
  */
 public function processAutoValidations(): void
 {
  try {
   $pendingJalons = $this->jalonRepository->findPendingAutoValidations();

   $processedCount = 0;
   $errorCount = 0;

   foreach ($pendingJalons as $jalon) {
    try {
     if ($jalon->isAutoValidationDue()) {
      $jalon->autoValidate();
      $this->jalonRepository->save($jalon);
      $processedCount++;

      Log::info('Jalon auto-validated', [
       'jalon_id' => $jalon->getId()->getValue(),
       'chantier_id' => $jalon->getChantierId()->getValue()
      ]);
     }
    } catch (\Exception $e) {
     $errorCount++;
     Log::error('Failed to auto-validate jalon', [
      'jalon_id' => $jalon->getId()->getValue(),
      'error' => $e->getMessage()
     ]);
    }
   }

   Log::info('Auto-validation process completed', [
    'total_pending' => count($pendingJalons),
    'processed' => $processedCount,
    'errors' => $errorCount
   ]);
  } catch (\Exception $e) {
   Log::error('Auto-validation process failed', [
    'error' => $e->getMessage()
   ]);
  }
 }
}
