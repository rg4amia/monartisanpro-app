<?php

namespace App\Domain\Financial\Models\Sequestre;

use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Financial\Models\ValueObjects\SequestreStatus;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use InvalidArgumentException;

/**
 * Aggregate root representing an escrow account (Séquestre)
 *
 * Manages fund blocking, fragmentation (65% materials, 35% labor),
 * and progressive release during project execution
 *
 * Requirements: 4.1, 4.2, 4.6
 */
final class Sequestre
{
    private SequestreId $id;
    private MissionId $missionId;
    private UserId $clientId;
    private UserId $artisanId;
    private MoneyAmount $totalAmount;
    private MoneyAmount $materialsAmount;
    private MoneyAmount $laborAmount;
    private MoneyAmount $materialsReleased;
    private MoneyAmount $laborReleased;
    private SequestreStatus $status;
    private DateTime $createdAt;

    private const MATERIALS_PERCENTAGE = 65;
    private const LABOR_PERCENTAGE = 35;

    public function __construct(
        SequestreId $id,
        MissionId $missionId,
        UserId $clientId,
        UserId $artisanId,
        MoneyAmount $totalAmount,
        ?MoneyAmount $materialsAmount = null,
        ?MoneyAmount $laborAmount = null,
        ?MoneyAmount $materialsReleased = null,
        ?MoneyAmount $laborReleased = null,
        ?SequestreStatus $status = null,
        ?DateTime $createdAt = null
    ) {
        $this->validateTotalAmount($totalAmount);

        $this->id = $id;
        $this->missionId = $missionId;
        $this->clientId = $clientId;
        $this->artisanId = $artisanId;
        $this->totalAmount = $totalAmount;
        $this->materialsReleased = $materialsReleased ?? MoneyAmount::fromCentimes(0);
        $this->laborReleased = $laborReleased ?? MoneyAmount::fromCentimes(0);
        $this->status = $status ?? SequestreStatus::blocked();
        $this->createdAt = $createdAt ?? new DateTime();

        // Calculate fragmentation if not provided
        if ($materialsAmount === null || $laborAmount === null) {
            $this->fragment();
        } else {
            $this->materialsAmount = $materialsAmount;
            $this->laborAmount = $laborAmount;
        }
    }

    /**
     * Create a new sequestre with generated ID
     *
     * Requirement 4.1: Block funds in escrow after quote acceptance
     */
    public static function create(
        MissionId $missionId,
        UserId $clientId,
        UserId $artisanId,
        MoneyAmount $totalAmount
    ): self {
        return new self(
            SequestreId::generate(),
            $missionId,
            $clientId,
            $artisanId,
            $totalAmount
        );
    }

    /**
     * Fragment the escrow into materials (65%) and labor (35%)
     *
     * Requirement 4.2: Fragment sequestre with 65/35 split
     */
    public function fragment(): void
    {
        $this->materialsAmount = $this->totalAmount->percentage(self::MATERIALS_PERCENTAGE);
        $this->laborAmount = $this->totalAmount->percentage(self::LABOR_PERCENTAGE);
    }

    /**
     * Release materials funds
     *
     * Used when jeton is validated at supplier
     */
    public function releaseMaterials(MoneyAmount $amount): void
    {
        $this->validateMaterialsRelease($amount);

        $this->materialsReleased = $this->materialsReleased->add($amount);
        $this->updateStatus();
    }

    /**
     * Release labor funds
     *
     * Used when milestone is validated
     */
    public function releaseLabor(MoneyAmount $amount): void
    {
        $this->validateLaborRelease($amount);

        $this->laborReleased = $this->laborReleased->add($amount);
        $this->updateStatus();
    }

    /**
     * Refund funds to client
     *
     * Used in dispute resolution or project cancellation
     */
    public function refund(MoneyAmount $amount): void
    {
        $availableForRefund = $this->getRemainingTotal();

        if ($amount->isGreaterThan($availableForRefund)) {
            throw new InvalidArgumentException(
                "Cannot refund {$amount->format()}, only {$availableForRefund->format()} available"
            );
        }

        // Refund proportionally from materials and labor
        $materialsRefund = $amount->percentage(self::MATERIALS_PERCENTAGE);
        $laborRefund = $amount->percentage(self::LABOR_PERCENTAGE);

        $this->materialsReleased = $this->materialsReleased->add($materialsRefund);
        $this->laborReleased = $this->laborReleased->add($laborRefund);

        $this->status = SequestreStatus::refunded();
    }

    /**
     * Get remaining materials amount
     */
    public function getRemainingMaterials(): MoneyAmount
    {
        return $this->materialsAmount->subtract($this->materialsReleased);
    }

    /**
     * Get remaining labor amount
     */
    public function getRemainingLabor(): MoneyAmount
    {
        return $this->laborAmount->subtract($this->laborReleased);
    }

    /**
     * Get total remaining amount
     */
    public function getRemainingTotal(): MoneyAmount
    {
        return $this->getRemainingMaterials()->add($this->getRemainingLabor());
    }

    /**
     * Check if sequestre is fully released
     */
    public function isFullyReleased(): bool
    {
        return $this->getRemainingTotal()->toCentimes() === 0;
    }

    // Getters
    public function getId(): SequestreId
    {
        return $this->id;
    }

    public function getMissionId(): MissionId
    {
        return $this->missionId;
    }

    public function getClientId(): UserId
    {
        return $this->clientId;
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

    public function getMaterialsReleased(): MoneyAmount
    {
        return $this->materialsReleased;
    }

    public function getLaborReleased(): MoneyAmount
    {
        return $this->laborReleased;
    }

    public function getStatus(): SequestreStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'mission_id' => $this->missionId->getValue(),
            'client_id' => $this->clientId->getValue(),
            'artisan_id' => $this->artisanId->getValue(),
            'total_amount' => $this->totalAmount->toArray(),
            'materials_amount' => $this->materialsAmount->toArray(),
            'labor_amount' => $this->laborAmount->toArray(),
            'materials_released' => $this->materialsReleased->toArray(),
            'labor_released' => $this->laborReleased->toArray(),
            'remaining_materials' => $this->getRemainingMaterials()->toArray(),
            'remaining_labor' => $this->getRemainingLabor()->toArray(),
            'remaining_total' => $this->getRemainingTotal()->toArray(),
            'status' => $this->status->getValue(),
            'is_fully_released' => $this->isFullyReleased(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    private function validateTotalAmount(MoneyAmount $amount): void
    {
        if ($amount->toCentimes() <= 0) {
            throw new InvalidArgumentException('Sequestre total amount must be positive');
        }
    }

    private function validateMaterialsRelease(MoneyAmount $amount): void
    {
        $remaining = $this->getRemainingMaterials();

        if ($amount->isGreaterThan($remaining)) {
            throw new InvalidArgumentException(
                "Cannot release {$amount->format()} from materials, only {$remaining->format()} remaining"
            );
        }
    }

    private function validateLaborRelease(MoneyAmount $amount): void
    {
        $remaining = $this->getRemainingLabor();

        if ($amount->isGreaterThan($remaining)) {
            throw new InvalidArgumentException(
                "Cannot release {$amount->format()} from labor, only {$remaining->format()} remaining"
            );
        }
    }

    private function updateStatus(): void
    {
        if ($this->isFullyReleased()) {
            $this->status = SequestreStatus::released();
        } elseif ($this->materialsReleased->toCentimes() > 0 || $this->laborReleased->toCentimes() > 0) {
            $this->status = SequestreStatus::partial();
        }
    }
}
