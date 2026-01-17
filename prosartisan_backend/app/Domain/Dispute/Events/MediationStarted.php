<?php

namespace App\Domain\Dispute\Events;

use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use DateTime;

/**
 * Domain event fired when mediation is started for a dispute
 *
 * Requirements: 9.3
 */
final class MediationStarted
{
    public function __construct(
        public readonly LitigeId $litigeId,
        public readonly UserId $mediatorId,
        public readonly DateTime $occurredAt
    ) {}

    public static function create(
        LitigeId $litigeId,
        UserId $mediatorId
    ): self {
        return new self(
            $litigeId,
            $mediatorId,
            new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'litige_id' => $this->litigeId->getValue(),
            'mediator_id' => $this->mediatorId->getValue(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
