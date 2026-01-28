<?php

namespace App\Domain\Dispute\Repositories;

use App\Domain\Dispute\Models\Litige\Litige;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;

/**
 * Repository interface for Litige aggregate
 *
 * Provides persistence operations for Litige entities
 *
 * Requirements: 9.1
 */
interface LitigeRepository
{
    /**
     * Save a litige entity
     *
     * @param  Litige  $litige  Litige to save
     */
    public function save(Litige $litige): void;

    /**
     * Find a litige by ID
     *
     * @param  LitigeId  $id  Litige ID
     * @return Litige|null Litige entity or null if not found
     */
    public function findById(LitigeId $id): ?Litige;

    /**
     * Find litiges by mission ID
     *
     * @param  MissionId  $missionId  Mission ID
     * @return array Array of Litige entities
     */
    public function findByMissionId(MissionId $missionId): array;

    /**
     * Find all open disputes
     *
     * @return array Array of Litige entities with open status
     */
    public function findOpenDisputes(): array;

    /**
     * Find disputes by status
     *
     * @param  string  $status  Dispute status
     * @return array Array of Litige entities
     */
    public function findByStatus(string $status): array;

    /**
     * Find disputes involving a specific user
     *
     * @param  string  $userId  User ID
     * @return array Array of Litige entities
     */
    public function findByUser(string $userId): array;

    /**
     * Delete a litige
     *
     * @param  LitigeId  $id  Litige ID
     */
    public function delete(LitigeId $id): void;
}
