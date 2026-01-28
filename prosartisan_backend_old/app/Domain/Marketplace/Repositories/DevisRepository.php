<?php

namespace App\Domain\Marketplace\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\Devis\Devis;
use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;

/**
 * Repository interface for Devis aggregate
 */
interface DevisRepository
{
    /**
     * Save a devis (create or update)
     */
    public function save(Devis $devis): void;

    /**
     * Find a devis by its ID
     */
    public function findById(DevisId $id): ?Devis;

    /**
     * Find all devis for a specific mission
     *
     * @return Devis[]
     */
    public function findByMissionId(MissionId $missionId): array;

    /**
     * Find all devis submitted by a specific artisan
     *
     * @return Devis[]
     */
    public function findByArtisanId(UserId $artisanId): array;

    /**
     * Find pending devis that have expired
     *
     * @return Devis[]
     */
    public function findExpiredPendingDevis(): array;
}
