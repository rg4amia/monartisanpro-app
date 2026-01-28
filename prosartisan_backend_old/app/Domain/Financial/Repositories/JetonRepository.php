<?php

namespace App\Domain\Financial\Repositories;

use App\Domain\Financial\Models\JetonMateriel\JetonMateriel;
use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Identity\Models\ValueObjects\UserId;

/**
 * Repository interface for JetonMateriel entity
 */
interface JetonRepository
{
    /**
     * Save jeton to persistence
     */
    public function save(JetonMateriel $jeton): void;

    /**
     * Find jeton by ID
     */
    public function findById(JetonId $id): ?JetonMateriel;

    /**
     * Find jeton by code
     */
    public function findByCode(string $code): ?JetonMateriel;

    /**
     * Find active jetons by artisan
     */
    public function findActiveByArtisan(UserId $artisanId): array;

    /**
     * Find jetons by sequestre
     */
    public function findBySequestre(SequestreId $sequestreId): array;

    /**
     * Find expired jetons that need processing
     */
    public function findExpiredJetons(): array;

    /**
     * Find jetons authorized for a specific supplier
     */
    public function findAuthorizedForSupplier(UserId $supplierId): array;

    /**
     * Delete jeton (for testing purposes only)
     */
    public function delete(JetonId $id): void;
}
