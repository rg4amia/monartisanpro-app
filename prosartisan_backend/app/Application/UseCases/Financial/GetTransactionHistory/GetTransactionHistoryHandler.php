<?php

namespace App\Application\UseCases\Financial\GetTransactionHistory;

use App\Domain\Financial\Repositories\TransactionRepository;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Financial\Models\ValueObjects\TransactionType;
use Illuminate\Support\Facades\Log;

/**
 * Handler for getting transaction history
 *
 * Requirements: 4.6, 13.6
 */
final class GetTransactionHistoryHandler
{
 public function __construct(
  private TransactionRepository $transactionRepository
 ) {}

 public function handle(GetTransactionHistoryQuery $query): array
 {
  Log::info('Retrieving transaction history', [
   'user_id' => $query->userId,
   'page' => $query->page,
   'limit' => $query->limit,
   'type_filter' => $query->type
  ]);

  // Create value objects
  $userId = UserId::fromString($query->userId);
  $typeFilter = $query->type ? TransactionType::fromString($query->type) : null;

  // Calculate offset
  $offset = ($query->page - 1) * $query->limit;

  // Get transactions with pagination
  $transactions = $this->transactionRepository->findByUserIdPaginated(
   $userId,
   $query->limit,
   $offset,
   $typeFilter
  );

  // Get total count for pagination
  $total = $this->transactionRepository->countByUserId($userId, $typeFilter);

  Log::info('Transaction history retrieved successfully', [
   'user_id' => $query->userId,
   'transaction_count' => count($transactions),
   'total_count' => $total
  ]);

  return [
   'transactions' => $transactions,
   'total' => $total
  ];
 }
}
