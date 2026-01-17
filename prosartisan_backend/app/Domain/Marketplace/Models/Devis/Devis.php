<?php

namespace App\Domain\Marketplace\Models\Devis;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\DevisStatus;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use InvalidArgumentException;

/**
 * Entity representing a devis (quote) submitted by an artisan for a mission
 * Contains itemized costs for materials and labor
 */
final class Devis
{
    private DevisId $id;
    private MissionId $missionId;
    private UserId $artisanId;
    private MoneyAmount $totalAmount;
    private MoneyAmount $materialsAmount;
    private MoneyAmount $laborAmount;
    /** @var DevisLine[] */
    private array $lineItems;
    private DevisStatus $status;
    private DateTime $createdAt;
    private ?DateTime $expiresAt;

    public function __construct(
        DevisId $id,
        MissionId $missionId,
        UserId $artisanId,
        array $lineItems,
        ?DateTime $expiresAt = null,
        ?DevisStatus $status = null,
        ?DateTime $createdAt = null
    ) {
        $this->validateLineItems($lineItems);

        $this->id = $id;
        $this->missionId = $missionId;
        $this->artisanId = $artisanId;
        $this->lineItems = $lineItems;
        $this->status = $status ?? DevisStatus::pending();
        $this->createdAt = $createdAt ?? new DateTime();
        $this->expiresAt = $expiresAt;

        // Calculate amounts from line items
        $this->calculateAmounts();
    }

    /**
     * Create a new devis with generated ID
     */
    public static function create(
        MissionId $missionId,
        UserId $artisanId,
        array $lineItems,
        ?DateTime $expiresAt = null
    ): self {
        return new self(
            DevisId::generate(),
            $missionId,
            $artisanId,
            $lineItems,
            $expiresAt
        );
    }

    /**
     * Accept this devis
     */
    public function accept(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidArgumentException(
                "Cannot accept devis with status {$this->status->getValue()}"
            );
        }

        if ($this->isExpired()) {
            throw new InvalidArgumentException('Cannot accept expired devis');
        }

        $this->status = DevisStatus::accepted();
    }

    /**
     * Reject this devis
     */
    public function reject(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidArgumentException(
                "Cannot reject devis with status {$this->status->getValue()}"
            );
        }

        $this->status = DevisStatus::rejected();
    }

    /**
     * Check if this devis has expired
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return new DateTime() > $this->expiresAt;
    }

    public function getId(): DevisId
    {
        return $this->id;
    }

    public function getMissionId(): MissionId
    {
        return $this->missionId;
    }

    public function getArtisanId(): UserId
    {
        return $this->artisanId;
    }

    public function getTotalAmount(): MoneyAmount
    {
        return $this->totalAmount;
    }

    public function getMaterialsAmount(): MoneyAmount
    {
        return $this->materialsAmount;
    }

    public function getLaborAmount(): MoneyAmount
    {
        return $this->laborAmount;
    }

    /**
     * @return DevisLine[]
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    public function getStatus(): DevisStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'mission_id' => $this->missionId->getValue(),
            'artisan_id' => $this->artisanId->getValue(),
            'total_amount' => $this->totalAmount->toArray(),
            'materials_amount' => $this->materialsAmount->toArray(),
            'labor_amount' => $this->laborAmount->toArray(),
            'line_items' => array_map(fn(DevisLine $line) => $line->toArray(), $this->lineItems),
            'status' => $this->status->getValue(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Calculate total, materials, and labor amounts from line items
     */
    private function calculateAmounts(): void
    {
        $materialsTotal = MoneyAmount::fromCentimes(0);
        $laborTotal = MoneyAmount::fromCentimes(0);

        foreach ($this->lineItems as $line) {
            $lineTotal = $line->getTotal();

            if ($line->isMaterial()) {
                $materialsTotal = $materialsTotal->add($lineTotal);
            } else {
                $laborTotal = $laborTotal->add($lineTotal);
            }
        }

        $this->materialsAmount = $materialsTotal;
        $this->laborAmount = $laborTotal;
        $this->totalAmount = $materialsTotal->add($laborTotal);
    }

    /**
     * Validate that line items array is not empty and contains only DevisLine instances
     */
    private function validateLineItems(array $lineItems): void
    {
        if (empty($lineItems)) {
            throw new InvalidArgumentException('Devis must have at least one line item');
        }

        foreach ($lineItems as $item) {
            if (!$item instanceof DevisLine) {
                throw new InvalidArgumentException('All line items must be instances of DevisLine');
            }
        }
    }
}
