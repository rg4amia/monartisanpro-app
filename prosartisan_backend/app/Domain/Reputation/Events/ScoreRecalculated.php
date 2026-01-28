<?php

namespace App\Domain\Reputation\Events;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Reputation\Models\ValueObjects\NZassaScore;
use DateTime;

/**
 * Domain event fired when an artisan's N'Zassa score is recalculated
 */
class ScoreRecalculated
{
    public UserId $artisanId;

    public NZassaScore $oldScore;

    public NZassaScore $newScore;

    public string $reason;

    public DateTime $occurredAt;

    public function __construct(
        UserId $artisanId,
        NZassaScore $oldScore,
        NZassaScore $newScore,
        string $reason
    ) {
        $this->artisanId = $artisanId;
        $this->oldScore = $oldScore;
        $this->newScore = $newScore;
        $this->reason = $reason;
        $this->occurredAt = new DateTime;
    }
}
