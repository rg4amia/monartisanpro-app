<?php

namespace App\Domain\Financial\Services;

use DateTime;

/**
 * Value object representing the status of a mobile money transaction
 *
 * Used when querying the status of an existing transaction
 * from mobile money providers.
 */
final class MobileMoneyTransactionStatus
{
    private string $status; // PENDING, COMPLETED, FAILED, CANCELLED

    private ?string $providerTransactionId;

    private ?string $providerReference;

    private ?string $errorMessage;

    private ?DateTime $completedAt;

    private array $metadata;

    public function __construct(
        string $status,
        ?string $providerTransactionId = null,
        ?string $providerReference = null,
        ?string $errorMessage = null,
        ?DateTime $completedAt = null,
        array $metadata = []
    ) {
        $this->status = $status;
        $this->providerTransactionId = $providerTransactionId;
        $this->providerReference = $providerReference;
        $this->errorMessage = $errorMessage;
        $this->completedAt = $completedAt;
        $this->metadata = $metadata;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProviderTransactionId(): ?string
    {
        return $this->providerTransactionId;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCompletedAt(): ?DateTime
    {
        return $this->completedAt;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'provider_transaction_id' => $this->providerTransactionId,
            'provider_reference' => $this->providerReference,
            'error_message' => $this->errorMessage,
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }
}
