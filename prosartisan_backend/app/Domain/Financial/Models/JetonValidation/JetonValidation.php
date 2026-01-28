<?php

namespace App\Domain\Financial\Models\JetonValidation;

use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Entity representing a jeton validation record
 *
 * Tracks each validation of a jeton for audit purposes.
 * This is an immutable audit record.
 *
 * Requirements: 5.3, 5.5
 */
final class JetonValidation
{
    private string $id;

    private JetonId $jetonId;

    private UserId $fournisseurId;

    private UserId $artisanId;

    private MoneyAmount $amountUsed;

    private GPS_Coordinates $artisanLocation;

    private GPS_Coordinates $supplierLocation;

    private float $distanceMeters;

    private string $validationStatus;

    private ?string $validationNotes;

    private DateTime $validatedAt;

    private DateTime $createdAt;

    public function __construct(
        string $id,
        JetonId $jetonId,
        UserId $fournisseurId,
        UserId $artisanId,
        MoneyAmount $amountUsed,
        GPS_Coordinates $artisanLocation,
        GPS_Coordinates $supplierLocation,
        float $distanceMeters,
        string $validationStatus = 'SUCCESS',
        ?string $validationNotes = null,
        ?DateTime $validatedAt = null,
        ?DateTime $createdAt = null
    ) {
        $this->id = $id;
        $this->jetonId = $jetonId;
        $this->fournisseurId = $fournisseurId;
        $this->artisanId = $artisanId;
        $this->amountUsed = $amountUsed;
        $this->artisanLocation = $artisanLocation;
        $this->supplierLocation = $supplierLocation;
        $this->distanceMeters = $distanceMeters;
        $this->validationStatus = $validationStatus;
        $this->validationNotes = $validationNotes;
        $this->validatedAt = $validatedAt ?? new DateTime;
        $this->createdAt = $createdAt ?? new DateTime;
    }

    /**
     * Create a new validation record
     */
    public static function create(
        JetonId $jetonId,
        UserId $fournisseurId,
        UserId $artisanId,
        MoneyAmount $amountUsed,
        GPS_Coordinates $artisanLocation,
        GPS_Coordinates $supplierLocation
    ): self {
        $distanceMeters = $artisanLocation->distanceTo($supplierLocation);

        return new self(
            self::generateId(),
            $jetonId,
            $fournisseurId,
            $artisanId,
            $amountUsed,
            $artisanLocation,
            $supplierLocation,
            $distanceMeters
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getJetonId(): JetonId
    {
        return $this->jetonId;
    }

    public function getFournisseurId(): UserId
    {
        return $this->fournisseurId;
    }

    public function getArtisanId(): UserId
    {
        return $this->artisanId;
    }

    public function getAmountUsed(): MoneyAmount
    {
        return $this->amountUsed;
    }

    public function getArtisanLocation(): GPS_Coordinates
    {
        return $this->artisanLocation;
    }

    public function getSupplierLocation(): GPS_Coordinates
    {
        return $this->supplierLocation;
    }

    public function getDistanceMeters(): float
    {
        return $this->distanceMeters;
    }

    public function getValidationStatus(): string
    {
        return $this->validationStatus;
    }

    public function getValidationNotes(): ?string
    {
        return $this->validationNotes;
    }

    public function getValidatedAt(): DateTime
    {
        return $this->validatedAt;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    private static function generateId(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}
