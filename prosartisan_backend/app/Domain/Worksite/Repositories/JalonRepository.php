<?php

namespace App\Domain\Worksite\Repositories;

use App\Domain\Worksite\Models\Jalon\Jalon;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use App\Domain\Worksite\Models\ValueObjects\JalonId;

/**
 * Repository interface for Jalon entity
 *
 * Requirements: 6.1, 6.2, 6.5
 */
interface JalonRepository
{
    /**
     * Save a jalon
     */
    public function save(Jalon $jalon): void;

    /**
     * Find jalon by ID
     */
    public function findById(JalonId $id): ?Jalon;

    /**
     * Find all jalons for a chantier
     */
    public function findByChantierId(ChantierId $chantierId): array;

    /**
     * Find jalons that need auto-validation (deadline passed)
     *
     * Requirement 6.5: Support cron job for auto-validation
     */
    public function findPendingAutoValidations(): array;

    /**
     * Find jalons by status
     */
    public function findByStatus(string $status): array;
}
