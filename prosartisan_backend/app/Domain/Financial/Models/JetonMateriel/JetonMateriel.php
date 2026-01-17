<?php

namespace App\Domain\Financial\Models\JetonMateriel;

use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Financial\Models\ValueObjects\JetonStatus;
use App\Domain\Financial\Models\ValueObjects\JetonCode;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;
use InvalidArgumentException;

/**
 * Entity representing a material token (Jeton Matériel)
 *
 * Allows artisans to purchase materials at suppliers using escrowed funds.
 * Includes GPS proximity validation and expiration logic.
 *
 * Requirements: 5.1, 5.2, 5.3, 5.5, 5.7
 */
final class JetonMateriel
{
    private JetonId $id;
    private SequestreId $sequestreId;
    private UserId $artisanId;
    private JetonCode $code;
    private MoneyAmount $totalAmount;
    private MoneyAmount $usedAmount;
    private array $authorizedSuppliers; // Array of UserId
    private JetonStatus $status;
    private DateTime $createdAt;
    private DateTime $expiresAt;

    private const EXPIRATION_DAYS = 7;
    private const MAX_PROXIMITY_METERS = 100;

    public function __construct(
        JetonId $id,
        SequestreId $sequestreId,
        UserId $artisanId,
        JetonCode $code,
        MoneyAmount $totalAmount,
        array $authorizedSuppliers = [],
        ?MoneyAmount $usedAmount = null,
        ?JetonStatus $status = null,
        ?DateTime $createdAt = null,
        ?DateTime $expiresAt = null
    ) {
        $this->validateTotalAmount($totalAmount);
        $this->validateAuthorizedSuppliers($authorizedSuppliers);

        $this->id = $id;
        $this->sequestreId = $sequestreId;
        $this->artisanId = $artisanId;
        $this->code = $code;
        $this->totalAmount = $totalAmount;
        $this->usedAmount = $usedAmount ?? MoneyAmount::fromCentimes(0);
        $this->authorizedSuppliers = $authorizedSuppliers;
        $this->status = $status ?? JetonStatus::active();
        $this->createdAt = $createdAt ?? new DateTime();
        $this->expiresAt = $expiresAt ?? $this->calculateExpirationDate($this->createdAt);
    }

    /**
     * Create a new jeton with generated ID and code
     *
     * Requirement 5.1: Generate jeton with unique PA-XXXX code
     * Requirement 5.2: Set expiration date of 7 days
     */
    public static function create(
        SequestreId $sequestreId,
        UserId $artisanId,
        MoneyAmount $totalAmount,
        array $authorizedSuppliers = []
    ): self {
        return new self(
            JetonId::generate(),
            $sequestreId,
            $artisanId,
            JetonCode::generate(),
            $totalAmount,
            $authorizedSuppliers
        );
    }

    /**
     * Validate jeton usage at supplier
     *
     * Requirement 5.3: Verify GPS proximity (100m)
     * Requirement 5.5: Allow partial redemption
     */
    public function validate(
        UserId $fournisseurId,
        MoneyAmount $amount,
        GPS_Coordinates $artisanLocation,
        GPS_Coordinates $supplierLocation
    ): void {
        $this->validateCanBeUsed();
        $this->validateSupplierAuthorization($fournisseurId);
        $this->validateAmount($amount);
        $this->validateProximity($artisanLocation, $supplierLocation);

        $this->usedAmount = $this->usedAmount->add($amount);
        $this->updateStatus();
    }

    /**
     * Check if jeton is expired
     *
     * Requirement 5.2: 7-day expiration
     */
    public function isExpired(): bool
    {
        return new DateTime() > $this->expiresAt;
    }

    /**
     * Get remaining amount available for use
     */
    public function getRemainingAmount(): MoneyAmount
    {
        return $this->totalAmount->subtract($this->usedAmount);
    }

    /**
     * Check if jeton can still be used
     */
    public function canBeUsed(): bool
    {
        return !$this->isExpired()
            && !$this->status->isFullyUsed()
            && !$this->status->isExpired()
            && $this->getRemainingAmount()->toCentimes() > 0;
    }

    /**
     * Mark jeton as expired
     *
     * Requirement 5.7: Return unused funds when expired
     */
    public function expire(): void
    {
        $this->status = JetonStatus::expired();
    }

    /**
     * Add authorized supplier
     */
    public function addAuthorizedSupplier(UserId $supplierId): void
    {
        if (!$this->isSupplierAuthorized($supplierId)) {
            $this->authorizedSuppliers[] = $supplierId;
        }
    }

    /**
     * Check if supplier is authorized
     */
    public function isSupplierAuthorized(UserId $supplierId): bool
    {
        // If no specific suppliers authorized, allow all
        if (empty($this->authorizedSuppliers)) {
            return true;
        }

        foreach ($this->authorizedSuppliers as $authorizedId) {
            if ($authorizedId->equals($supplierId)) {
                return true;
            }
        }

        return false;
    }

    // Getters
    public function getId(): JetonId
    {
        return $this->id;
    }

    public function getSequestreId(): SequestreId
    {
        return $this->sequestreId;
    }

    public function getArtisanId(): UserId
    {
        return $this->artisanId;
    }

    public function getCode(): JetonCode
    {
        return $this->code;
    }

    public function getTotalAmount(): MoneyAmount
    {
        return $this->totalAmount;
    }

    public function getUsedAmount(): MoneyAmount
    {
        return $this->usedAmount;
    }

    public function getAuthorizedSuppliers(): array
    {
        return $this->authorizedSuppliers;
    }

    public function getStatus(): JetonStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'sequestre_id' => $this->sequestreId->getValue(),
            'artisan_id' => $this->artisanId->getValue(),
            'code' => $this->code->getValue(),
            'total_amount' => $this->totalAmount->toArray(),
            'used_amount' => $this->usedAmount->toArray(),
            'remaining_amount' => $this->getRemainingAmount()->toArray(),
            'authorized_suppliers' => array_map(fn($id) => $id->getValue(), $this->authorizedSuppliers),
            'status' => $this->status->getValue(),
            'can_be_used' => $this->canBeUsed(),
            'is_expired' => $this->isExpired(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
        ];
    }

    private function validateTotalAmount(MoneyAmount $amount): void
    {
        if ($amount->toCentimes() <= 0) {
            throw new InvalidArgumentException('Jeton total amount must be positive');
        }
    }

    private function validateAuthorizedSuppliers(array $suppliers): void
    {
        foreach ($suppliers as $supplier) {
            if (!$supplier instanceof UserId) {
                throw new InvalidArgumentException('All authorized suppliers must be UserId instances');
            }
        }
    }

    private function validateCanBeUsed(): void
    {
        if ($this->isExpired()) {
            throw new InvalidArgumentException('Cannot use expired jeton');
        }

        if ($this->status->isFullyUsed()) {
            throw new InvalidArgumentException('Jeton is fully used');
        }

        if ($this->status->isExpired()) {
            throw new InvalidArgumentException('Jeton is expired');
        }
    }

    private function validateSupplierAuthorization(UserId $fournisseurId): void
    {
        if (!$this->isSupplierAuthorized($fournisseurId)) {
            throw new InvalidArgumentException('Supplier is not authorized for this jeton');
        }
    }

    private function validateAmount(MoneyAmount $amount): void
    {
        if ($amount->toCentimes() <= 0) {
            throw new InvalidArgumentException('Usage amount must be positive');
        }

        $remaining = $this->getRemainingAmount();
        if ($amount->isGreaterThan($remaining)) {
            throw new InvalidArgumentException(
                "Cannot use {$amount->format()}, only {$remaining->format()} remaining"
            );
        }
    }

    private function validateProximity(GPS_Coordinates $artisanLocation, GPS_Coordinates $supplierLocation): void
    {
        $distance = $artisanLocation->distanceTo($supplierLocation);

        if ($distance > self::MAX_PROXIMITY_METERS) {
            throw new InvalidArgumentException(
                "Artisan and supplier must be within " . self::MAX_PROXIMITY_METERS . "m. Current distance: {$distance}m"
            );
        }
    }

    private function updateStatus(): void
    {
        if ($this->getRemainingAmount()->toCentimes() === 0) {
            $this->status = JetonStatus::fullyUsed();
        } elseif ($this->usedAmount->toCentimes() > 0) {
            $this->status = JetonStatus::partiallyUsed();
        }
    }

    private function calculateExpirationDate(DateTime $createdAt): DateTime
    {
        return (clone $createdAt)->modify('+' . self::EXPIRATION_DAYS . ' days');
    }
}
