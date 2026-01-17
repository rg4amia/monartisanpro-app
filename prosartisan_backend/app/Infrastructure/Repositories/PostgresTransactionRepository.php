<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Financial\Models\Transaction\Transaction;
use App\Domain\Financial\Models\ValueObjects\TransactionId;
use App\Domain\Financial\Models\ValueObjects\TransactionType;
use App\Domain\Financial\Models\ValueObjects\TransactionStatus;
use App\Domain\Financial\Repositories\TransactionRepository;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use Illuminate\Support\Facades\DB;
use DateTime;

/**
 * PostgreSQL implementation of TransactionRepository
 *
 * Note: Transactions are immutable audit records - only INSERT operations allowed
 */
final class PostgresTransactionRepository implements TransactionRepository
{
    private const TABLE = 'transactions';

    public function save(Transaction $transaction): void
    {
        $data = [
            'id' => $transaction->getId()->getValue(),
            'from_user_id' => $transaction->getFromUserId()?->getValue(),
            'to_user_id' => $transaction->getToUserId()?->getValue(),
            'amount_centimes' => $transaction->getAmount()->toCentimes(),
            'type' => $transaction->getType()->getValue(),
            'status' => $transaction->getStatus()->getValue(),
            'mobile_money_reference' => $transaction->getMobileMoneyReference(),
            'description' => $transaction->getDescription(),
            'metadata' => json_encode($transaction->getMetadata()),
            'created_at' => $transaction->getCreatedAt()->format('Y-m-d H:i:s'),
            'completed_at' => $transaction->getCompletedAt()?->format('Y-m-d H:i:s'),
            'failed_at' => $transaction->getFailedAt()?->format('Y-m-d H:i:s'),
            'failure_reason' => $transaction->getFailureReason(),
        ];

        // Only INSERT - never UPDATE (immutable audit log)
        DB::table(self::TABLE)->insert($data);
    }

    public function findById(TransactionId $id): ?Transaction
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id->getValue())
            ->first();

        return $row ? $this->mapRowToTransaction($row) : null;
    }

    public function findByUserId(UserId $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where(function ($query) use ($userId) {
                $query->where('from_user_id', $userId->getValue())
                    ->orWhere('to_user_id', $userId->getValue());
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToTransaction($row))->toArray();
    }

    public function findByUserIdPaginated(UserId $userId, int $limit, int $offset, ?TransactionType $typeFilter = null): array
    {
        $query = DB::table(self::TABLE)
            ->where(function ($query) use ($userId) {
                $query->where('from_user_id', $userId->getValue())
                    ->orWhere('to_user_id', $userId->getValue());
            });

        if ($typeFilter) {
            $query->where('type', $typeFilter->getValue());
        }

        $rows = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $rows->map(fn($row) => $this->mapRowToTransaction($row))->toArray();
    }

    public function findByType(TransactionType $type): array
    {
        $rows = DB::table(self::TABLE)
            ->where('type', $type->getValue())
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToTransaction($row))->toArray();
    }

    public function findPending(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('status', TransactionStatus::PENDING)
            ->orderBy('created_at', 'asc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToTransaction($row))->toArray();
    }

    public function findBetweenUsers(UserId $fromUserId, UserId $toUserId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('from_user_id', $fromUserId->getValue())
            ->where('to_user_id', $toUserId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToTransaction($row))->toArray();
    }

    public function findByMobileMoneyReference(string $reference): array
    {
        $rows = DB::table(self::TABLE)
            ->where('mobile_money_reference', $reference)
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToTransaction($row))->toArray();
    }

    public function findSingleByMobileMoneyReference(string $reference): ?Transaction
    {
        $row = DB::table(self::TABLE)
            ->where('mobile_money_reference', $reference)
            ->orderBy('created_at', 'desc')
            ->first();

        return $row ? $this->mapRowToTransaction($row) : null;
    }

    public function findByMetadataReference(string $reference): ?Transaction
    {
        $row = DB::table(self::TABLE)
            ->whereRaw("JSON_EXTRACT(metadata, '$.reference') = ?", [$reference])
            ->orderBy('created_at', 'desc')
            ->first();

        return $row ? $this->mapRowToTransaction($row) : null;
    }

    public function findPendingOlderThan(\DateTime $cutoffTime): array
    {
        $rows = DB::table(self::TABLE)
            ->where('status', TransactionStatus::PENDING)
            ->where('created_at', '<', $cutoffTime->format('Y-m-d H:i:s'))
            ->orderBy('created_at', 'asc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToTransaction($row))->toArray();
    }

    public function countByUserId(UserId $userId, ?TransactionType $typeFilter = null): int
    {
        $query = DB::table(self::TABLE)
            ->where(function ($query) use ($userId) {
                $query->where('from_user_id', $userId->getValue())
                    ->orWhere('to_user_id', $userId->getValue());
            });

        if ($typeFilter) {
            $query->where('type', $typeFilter->getValue());
        }

        return $query->count();
    }

    public function getTotalVolumeByUserId(UserId $userId): int
    {
        $result = DB::table(self::TABLE)
            ->where(function ($query) use ($userId) {
                $query->where('from_user_id', $userId->getValue())
                    ->orWhere('to_user_id', $userId->getValue());
            })
            ->where('status', TransactionStatus::COMPLETED)
            ->sum('amount_centimes');

        return (int) $result;
    }

    private function mapRowToTransaction($row): Transaction
    {
        $metadata = json_decode($row->metadata, true) ?? [];

        return new Transaction(
            TransactionId::fromString($row->id),
            $row->from_user_id ? UserId::fromString($row->from_user_id) : null,
            $row->to_user_id ? UserId::fromString($row->to_user_id) : null,
            MoneyAmount::fromCentimes($row->amount_centimes),
            TransactionType::fromString($row->type),
            $row->description,
            $metadata,
            TransactionStatus::fromString($row->status),
            $row->mobile_money_reference,
            new DateTime($row->created_at),
            $row->completed_at ? new DateTime($row->completed_at) : null,
            $row->failed_at ? new DateTime($row->failed_at) : null,
            $row->failure_reason
        );
    }
}
