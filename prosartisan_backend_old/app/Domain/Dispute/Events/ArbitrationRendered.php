<?php

namespace App\Domain\Dispute\Events;

use App\Domain\Dispute\Models\ValueObjects\ArbitrationDecision;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use DateTime;

/**
 * Domain event fired when arbitration decision is rendered
 *
 * Requirements: 9.6
 */
final class ArbitrationRendered
{
    public function __construct(
        public readonly LitigeId $litigeId,
        public readonly ArbitrationDecision $decision,
        public readonly DateTime $occurredAt
    ) {}

    public static function create(
        LitigeId $litigeId,
        ArbitrationDecision $decision
    ): self {
        return new self(
            $litigeId,
            $decision,
            new DateTime
        );
    }

    public function toArray(): array
    {
        return [
            'litige_id' => $this->litigeId->getValue(),
            'decision' => $this->decision->toArray(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
