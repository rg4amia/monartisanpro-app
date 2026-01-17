<?php

namespace App\Domain\Financial\Repositories;

use App\Domain\Financial\Models\Transaction\Transaction;
use App\Domain\Financial\Models\ValueObjects\TransactionId;
use App\Domain\Financial\Models\ValueObjects\TransactionType;
use App\Domain\Identity\Models\ValueObjects\UserId;

/**
 * Repository interface for Transaction entity
 *
 * Note: Transactions are immutable audit records and should never be updated or deleted
 */
interface TransactionRepository
{
    /**
     * Save transaction to persistence
     *
     * Note: This is append-only - transactions are never updated
     */
    public function save(Transaction $transaction): void;

    /**
     * Find transaction by ID
     */
    public function findById(TransactionId $id): ?Transaction;

    /**
     * Find transactions by user ID (either from or to)
     */
    public function findByUserId(UserId $userId): array;

    /**
     * Find transactions by user ID with pagination
     */
    public function findByUserIdPaginated(UserId $userId, int $limit, int $offset, ?TransactionType $typeFilter = null): array;

    /**
     * Find transactions by type
     */
    public function findByType(TransactionType $type): array;

    /**
     * Find pending transactions
     */
    public function findPending(): array;

    /**
     * Find transactions between two users
     */
    public function findBetweenUsers(UserId $fromUserId, UserId $toUserId): array;

    /**
     * Find transactions by mobile money reference
     */
    public function findByMobileMoneyReference(string $reference): array;

    /**
     * Find single transaction by mobile money reference
     */
    public function findSingleByMobileMoneyReference(string $reference): ?Transaction;

    /**
     * Find transaction by metadata reference
     */
    public function findByMetadataReference(string $reference): ?Transaction;

    /**
     * Find pending transactions older than specified time
     */
    public function findPendingOlderThan(\DateTime $cutoffTime): array;

    /**
     * Get transaction count for user
     */
    public function countByUserId(UserId $userId, ?TransactionType $typeFilter = null): int;

    /**
     * Get total transaction volume for user
     */
    public function getTotalVolumeByUserId(UserId $userId): int; // Returns amount in centimes
}
