<?php

namespace App\Domain\Financial\Repositories;

use App\Domain\Financial\Models\Sequestre\Sequestre;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;

/**
 * Repository interface for Sequestre aggregate
 */
interface SequestreRepository
{
    /**
     * Save sequestre to persistence
     */
    public function save(Sequestre $sequestre): void;

    /**
     * Find sequestre by ID
     */
    public function findById(SequestreId $id): ?Sequestre;

    /**
     * Find sequestre by mission ID
     */
    public function findByMissionId(MissionId $missionId): ?Sequestre;

    /**
     * Find sequestre by chantier ID
     */
    public function findByChantierId(\App\Domain\Worksite\Models\ValueObjects\ChantierId $chantierId): ?Sequestre;

    /**
     * Find sequestres by client ID
     */
    public function findByClientId(UserId $clientId): array;

    /**
     * Find sequestres by artisan ID
     */
    public function findByArtisanId(UserId $artisanId): array;

    /**
     * Find active sequestres (not fully released or refunded)
     */
    public function findActive(): array;

    /**
     * Find sequestre by reference
     */
    public function findByReference(string $reference): ?Sequestre;

    /**
     * Delete sequestre (for testing purposes only)
     */
    public function delete(SequestreId $id): void;
}
