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
 public function findByUserIdPaginated(UserId $userId, int $page = 1, int $perPage = 20): array;

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
  * Get transaction count for user
  */
 public function countByUserId(UserId $userId): int;

 /**
  * Get total transaction volume for user
  */
 public function getTotalVolumeByUserId(UserId $userId): int; // Returns amount in centimes
}
