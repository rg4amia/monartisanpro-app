<?php

namespace App\Domain\Financial\Services;

use App\Domain\Shared\ValueObjects\MoneyAmount;

/**
 * Default implementation of escrow fragmentation service
 *
 * Implements the standard 65% materials / 35% labor split
 */
final class DefaultEscrowFragmentationService implements EscrowFragmentationService
{
    private const MATERIALS_PERCENTAGE = 65;
    private const LABOR_PERCENTAGE = 35;

    public function calculateFragmentation(MoneyAmount $total): array
    {
        return [
            'materials' => $total->percentage(self::MATERIALS_PERCENTAGE),
            'labor' => $total->percentage(self::LABOR_PERCENTAGE),
        ];
    }

    public function getMaterialsPercentage(): int
    {
        return self::MATERIALS_PERCENTAGE;
    }

    public function getLaborPercentage(): int
    {
        return self::LABOR_PERCENTAGE;
    }
}
