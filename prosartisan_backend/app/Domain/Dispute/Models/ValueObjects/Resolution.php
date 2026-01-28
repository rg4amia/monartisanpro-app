<?php

namespace App\Domain\Dispute\Models\ValueObjects;

use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Value object representing dispute resolution
 */
final class Resolution
{
    private string $outcome;

    private ?MoneyAmount $amount;

    private string $notes;

    private DateTime $resolvedAt;

    public function __construct(
        string $outcome,
        string $notes,
        ?MoneyAmount $amount = null,
        ?DateTime $resolvedAt = null
    ) {
        $this->outcome = $outcome;
        $this->amount = $amount;
        $this->notes = $notes;
        $this->resolvedAt = $resolvedAt ?? new DateTime;
    }

    public static function fromArbitration(ArbitrationDecision $decision, string $notes): self
    {
        return new self(
            $decision->getType()->getValue(),
            $notes,
            $decision->getAmount()
        );
    }

    public static function fromMediation(string $outcome, string $notes, ?MoneyAmount $amount = null): self
    {
        return new self($outcome, $notes, $amount);
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function getAmount(): ?MoneyAmount
    {
        return $this->amount;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function getResolvedAt(): DateTime
    {
        return $this->resolvedAt;
    }

    public function hasAmount(): bool
    {
        return $this->amount !== null;
    }

    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'amount' => $this->amount?->toArray(),
            'notes' => $this->notes,
            'resolved_at' => $this->resolvedAt->format('Y-m-d H:i:s'),
        ];
    }
}
