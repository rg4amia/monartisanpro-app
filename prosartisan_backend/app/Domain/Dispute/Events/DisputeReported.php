<?php

namespace App\Domain\Dispute\Events;

use App\Domain\Dispute\Models\ValueObjects\DisputeType;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use DateTime;

/**
 * Domain event fired when a dispute is reported
 *
 * Requirements: 9.1
 */
final class DisputeReported
{
    public function __construct(
        public readonly LitigeId $litigeId,
        public readonly MissionId $missionId,
        public readonly UserId $reporterId,
        public readonly DisputeType $type,
        public readonly DateTime $occurredAt
    ) {}

    public static function create(
        LitigeId $litigeId,
        MissionId $missionId,
        UserId $reporterId,
        DisputeType $type
    ): self {
        return new self(
            $litigeId,
            $missionId,
            $reporterId,
            $type,
            new DateTime
        );
    }

    public function toArray(): array
    {
        return [
            'litige_id' => $this->litigeId->getValue(),
            'mission_id' => $this->missionId->getValue(),
            'reporter_id' => $this->reporterId->getValue(),
            'type' => $this->type->getValue(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
