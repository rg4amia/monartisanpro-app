<?php

namespace App\Application\UseCases\Financial\GetTransactionHistory;

/**
 * Query to get transaction history for a user
 *
 * Requirements: 4.6, 13.6
 */
final class GetTransactionHistoryQuery
{
    public function __construct(
        public readonly string $userId,
        public readonly int $page = 1,
        public readonly int $limit = 20,
        public readonly ?string $type = null
    ) {}
}
