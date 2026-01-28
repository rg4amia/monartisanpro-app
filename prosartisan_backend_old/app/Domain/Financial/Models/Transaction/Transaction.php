<?php

namespace App\Domain\Financial\Models\Transaction;

use App\Domain\Financial\Models\ValueObjects\TransactionId;
use App\Domain\Financial\Models\ValueObjects\TransactionStatus;
use App\Domain\Financial\Models\ValueObjects\TransactionType;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use InvalidArgumentException;

/**
 * Entity representing a financial transaction for audit trail
 *
 * Immutable record of all financial operations in the system.
 * Used for compliance, auditing, and transaction history.
 *
 * Requirements: 4.6, 13.6
 */
final class Transaction
{
    private TransactionId $id;

    private ?UserId $fromUserId;

    private ?UserId $toUserId;

    private MoneyAmount $amount;

    private TransactionType $type;

    private TransactionStatus $status;

    private ?string $mobileMoneyReference;

    private ?string $description;

    private array $metadata; // Additional context data

    private DateTime $createdAt;

    private ?DateTime $completedAt;

    private ?DateTime $failedAt;

    private ?string $failureReason;

    public function __construct(
        TransactionId $id,
        ?UserId $fromUserId,
        ?UserId $toUserId,
        MoneyAmount $amount,
        TransactionType $type,
        ?string $description = null,
        array $metadata = [],
        ?TransactionStatus $status = null,
        ?string $mobileMoneyReference = null,
        ?DateTime $createdAt = null,
        ?DateTime $completedAt = null,
        ?DateTime $failedAt = null,
        ?string $failureReason = null
    ) {
        $this->validateAmount($amount);
        $this->validateUserIds($fromUserId, $toUserId, $type);

        $this->id = $id;
        $this->fromUserId = $fromUserId;
        $this->toUserId = $toUserId;
        $this->amount = $amount;
        $this->type = $type;
        $this->description = $description;
        $this->metadata = $metadata;
        $this->status = $status ?? TransactionStatus::pending();
        $this->mobileMoneyReference = $mobileMoneyReference;
        $this->createdAt = $createdAt ?? new DateTime;
        $this->completedAt = $completedAt;
        $this->failedAt = $failedAt;
        $this->failureReason = $failureReason;
    }

    /**
     * Create a new transaction with generated ID
     *
     * Requirement 4.6: Record all financial transactions
     */
    public static function create(
        ?UserId $fromUserId,
        ?UserId $toUserId,
        MoneyAmount $amount,
        TransactionType $type,
        ?string $description = null,
        array $metadata = []
    ): self {
        return new self(
            TransactionId::generate(),
            $fromUserId,
            $toUserId,
            $amount,
            $type,
            $description,
            $metadata
        );
    }

    /**
     * Mark transaction as completed
     */
    public function complete(?string $mobileMoneyReference = null): void
    {
        if (! $this->status->isPending()) {
            throw new InvalidArgumentException('Only pending transactions can be completed');
        }

        $this->status = TransactionStatus::completed();
        $this->completedAt = new DateTime;

        if ($mobileMoneyReference !== null) {
            $this->mobileMoneyReference = $mobileMoneyReference;
        }
    }

    /**
     * Mark transaction as failed
     */
    public function fail(string $reason): void
    {
        if (! $this->status->isPending()) {
            throw new InvalidArgumentException('Only pending transactions can be failed');
        }

        $this->status = TransactionStatus::failed();
        $this->failedAt = new DateTime;
        $this->failureReason = $reason;
    }

    /**
     * Cancel transaction
     */
    public function cancel(): void
    {
        if (! $this->status->isPending()) {
            throw new InvalidArgumentException('Only pending transactions can be cancelled');
        }

        $this->status = TransactionStatus::cancelled();
    }

    /**
     * Check if transaction involves a specific user
     */
    public function involvesUser(UserId $userId): bool
    {
        return ($this->fromUserId && $this->fromUserId->equals($userId)) ||
            ($this->toUserId && $this->toUserId->equals($userId));
    }

    /**
     * Get transaction direction for a specific user
     */
    public function getDirectionForUser(UserId $userId): ?string
    {
        if ($this->fromUserId && $this->fromUserId->equals($userId)) {
            return 'outgoing';
        }

        if ($this->toUserId && $this->toUserId->equals($userId)) {
            return 'incoming';
        }

        return null;
    }

    // Getters
    public function getId(): TransactionId
    {
        return $this->id;
    }

    public function getFromUserId(): ?UserId
    {
        return $this->fromUserId;
    }

    public function getToUserId(): ?UserId
    {
        return $this->toUserId;
    }

    public function getAmount(): MoneyAmount
    {
        return $this->amount;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function getMobileMoneyReference(): ?string
    {
        return $this->mobileMoneyReference;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?DateTime
    {
        return $this->completedAt;
    }

    public function getFailedAt(): ?DateTime
    {
        return $this->failedAt;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'from_user_id' => $this->fromUserId?->getValue(),
            'to_user_id' => $this->toUserId?->getValue(),
            'amount' => $this->amount->toArray(),
            'type' => $this->type->getValue(),
            'type_label' => $this->type->getFrenchLabel(),
            'status' => $this->status->getValue(),
            'status_label' => $this->status->getFrenchLabel(),
            'mobile_money_reference' => $this->mobileMoneyReference,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
            'failed_at' => $this->failedAt?->format('Y-m-d H:i:s'),
            'failure_reason' => $this->failureReason,
        ];
    }

    private function validateAmount(MoneyAmount $amount): void
    {
        if ($amount->toCentimes() <= 0) {
            throw new InvalidArgumentException('Transaction amount must be positive');
        }
    }

    private function validateUserIds(?UserId $fromUserId, ?UserId $toUserId, TransactionType $type): void
    {
        // Some transaction types (like escrow block) may not have both from/to users
        if ($type->equals(TransactionType::escrowBlock())) {
            // Escrow block only needs fromUserId (client)
            if ($fromUserId === null) {
                throw new InvalidArgumentException('Escrow block transaction requires fromUserId');
            }
        } elseif ($type->equals(TransactionType::serviceFee())) {
            // Service fee only needs fromUserId
            if ($fromUserId === null) {
                throw new InvalidArgumentException('Service fee transaction requires fromUserId');
            }
        } else {
            // Other transaction types need both users
            if ($fromUserId === null || $toUserId === null) {
                throw new InvalidArgumentException('Transaction requires both fromUserId and toUserId');
            }

            if ($fromUserId->equals($toUserId)) {
                throw new InvalidArgumentException('Transaction cannot have same fromUserId and toUserId');
            }
        }
    }
}
