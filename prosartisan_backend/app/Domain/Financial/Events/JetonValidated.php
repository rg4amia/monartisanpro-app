<?php

namespace App\Domain\Financial\Events;

use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Domain event fired when a jeton is validated at supplier
 *
 * Requirement 5.3: GPS proximity validation for jeton usage
 */
final class JetonValidated
{
    public JetonId $jetonId;

    public UserId $fournisseurId;

    public MoneyAmount $amountUsed;

    public GPS_Coordinates $validationLocation;

    public DateTime $occurredAt;

    public function __construct(
        JetonId $jetonId,
        UserId $fournisseurId,
        MoneyAmount $amountUsed,
        GPS_Coordinates $validationLocation,
        ?DateTime $occurredAt = null
    ) {
        $this->jetonId = $jetonId;
        $this->fournisseurId = $fournisseurId;
        $this->amountUsed = $amountUsed;
        $this->validationLocation = $validationLocation;
        $this->occurredAt = $occurredAt ?? new DateTime;
    }

    public function toArray(): array
    {
        return [
            'jeton_id' => $this->jetonId->getValue(),
            'fournisseur_id' => $this->fournisseurId->getValue(),
            'amount_used' => $this->amountUsed->toArray(),
            'validation_location' => $this->validationLocation->toArray(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
