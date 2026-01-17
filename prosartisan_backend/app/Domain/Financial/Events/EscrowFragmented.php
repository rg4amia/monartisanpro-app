<?php

namespace App\Domain\Financial\Events;

use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Domain event fired when escrow is fragmented into materials and labor
 *
 * Requirement 4.2: Fragment sequestre with 65/35 split
 */
final class EscrowFragmented
{
    public SequestreId $sequestreId;
    public MoneyAmount $materialsAmount;
    public MoneyAmount $laborAmount;
    public DateTime $occurredAt;

    public function __construct(
        SequestreId $sequestreId,
        MoneyAmount $materialsAmount,
        MoneyAmount $laborAmount,
        ?DateTime $occurredAt = null
    ) {
        $this->sequestreId = $sequestreId;
        $this->materialsAmount = $materialsAmount;
        $this->laborAmount = $laborAmount;
        $this->occurredAt = $occurredAt ?? new DateTime();
    }

    public function toArray(): array
    {
        return [
            'sequestre_id' => $this->sequestreId->getValue(),
            'materials_amount' => $this->materialsAmount->toArray(),
            'labor_amount' => $this->laborAmount->toArray(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
