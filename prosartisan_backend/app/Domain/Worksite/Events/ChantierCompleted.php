<?php

namespace App\Domain\Worksite\Events;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use DateTime;

/**
 * Domain event fired when all milestones are validated and chantier is completed
 *
 * Requirements: 7.1 - Recalculate artisan score when chantier is completed
 */
final class ChantierCompleted
{
    public function __construct(
        public readonly ChantierId $chantierId,
        public readonly MissionId $missionId,
        public readonly UserId $artisanId,
        public readonly UserId $clientId,
        public readonly DateTime $occurredAt
    ) {}
}
