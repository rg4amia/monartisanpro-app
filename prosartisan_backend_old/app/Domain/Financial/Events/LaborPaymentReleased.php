<?php

namespace App\Domain\Financial\Events;

use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Domain event fired when labor payment is released to artisan
 *
 * Requirement 6.6: Release labor payment when milestone validated
 */
final class LaborPaymentReleased
{
    public SequestreId $sequestreId;

    public UserId $artisanId;

    public MoneyAmount $amount;

    public DateTime $occurredAt;

    public function __construct(
        SequestreId $sequestreId,
        UserId $artisanId,
        MoneyAmount $amount,
        ?DateTime $occurredAt = null
    ) {
        $this->sequestreId = $sequestreId;
        $this->artisanId = $artisanId;
        $this->amount = $amount;
        $this->occurredAt = $occurredAt ?? new DateTime;
    }

    public function toArray(): array
    {
        return [
            'sequestre_id' => $this->sequestreId->getValue(),
            'artisan_id' => $this->artisanId->getValue(),
            'amount' => $this->amount->toArray(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
