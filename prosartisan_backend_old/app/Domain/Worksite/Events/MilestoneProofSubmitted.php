<?php

namespace App\Domain\Worksite\Events;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use App\Domain\Worksite\Models\ValueObjects\JalonId;
use DateTime;

/**
 * Domain event fired when an artisan submits milestone proof
 * Requirements: 11.3 - Notify client when milestone proof is submitted
 */
final class MilestoneProofSubmitted
{
    public function __construct(
        public readonly JalonId $jalonId,
        public readonly ChantierId $chantierId,
        public readonly UserId $artisanId,
        public readonly UserId $clientId,
        public readonly string $photoUrl,
        public readonly GPS_Coordinates $location,
        public readonly DateTime $occurredAt
    ) {}
}
