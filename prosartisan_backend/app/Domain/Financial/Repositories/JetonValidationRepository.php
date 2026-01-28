<?php

namespace App\Domain\Financial\Repositories;

use App\Domain\Financial\Models\JetonValidation\JetonValidation;
use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Identity\Models\ValueObjects\UserId;

/**
 * Repository interface for JetonValidation entity
 *
 * Note: JetonValidations are immutable audit records and should never be updated or deleted
 */
interface JetonValidationRepository
{
    /**
     * Save validation to persistence
     *
     * Note: This is append-only - validations are never updated
     */
    public function save(JetonValidation $validation): void;

    /**
     * Find validation by ID
     */
    public function findById(string $id): ?JetonValidation;

    /**
     * Find validations by jeton ID
     */
    public function findByJetonId(JetonId $jetonId): array;

    /**
     * Find validations by supplier ID
     */
    public function findByFournisseurId(UserId $fournisseurId): array;

    /**
     * Find validations by artisan ID
     */
    public function findByArtisanId(UserId $artisanId): array;
}
