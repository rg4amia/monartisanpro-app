<?php

namespace App\Domain\Financial\Services;

use App\Domain\Shared\ValueObjects\MoneyAmount;

/**
 * Domain service for escrow fragmentation calculations
 *
 * Implements the 65/35 split between materials and labor
 * as per business requirements.
 *
 * Requirement 4.2: Fragment sequestre with 65/35 split
 */
interface EscrowFragmentationService
{
 /**
  * Calculate fragmentation amounts
  *
  * @param MoneyAmount $total
  * @return array{materials: MoneyAmount, labor: MoneyAmount}
  */
 public function calculateFragmentation(MoneyAmount $total): array;

 /**
  * Get materials percentage
  */
 public function getMaterialsPercentage(): int;

 /**
  * Get labor percentage
  */
 public function getLaborPercentage(): int;
}
