<?php

namespace App\Domain\Worksite\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Worksite\Models\Chantier\Chantier;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;

/**
 * Repository interface for Chantier aggregate
 *
 * Requirements: 6.1, 6.2
 */
interface ChantierRepository
{
    /**
     * Save a chantier
     */
    public function save(Chantier $chantier): void;

    /**
     * Find chantier by ID
     */
    public function findById(ChantierId $id): ?Chantier;

    /**
     * Find chantier by mission ID
     */
    public function findByMissionId(MissionId $missionId): ?Chantier;

    /**
     * Find active chantiers by artisan
     */
    public function findActiveByArtisan(UserId $artisanId): array;

    /**
     * Find all chantiers by client
     */
    public function findByClient(UserId $clientId): array;

    /**
     * Find all chantiers by artisan
     */
    public function findByArtisan(UserId $artisanId): array;
}
