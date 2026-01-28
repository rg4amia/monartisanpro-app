<?php

namespace App\Domain\Identity\Models\ReferentZone;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;

/**
 * ReferentZone entity representing a trusted third-party validator
 * Used for high-value project mediation and arbitration
 */
final class ReferentZone extends User
{
    private GPS_Coordinates $coverageArea;

    private string $zone;

    public function __construct(
        UserId $id,
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        GPS_Coordinates $coverageArea,
        string $zone,
        ?AccountStatus $status = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        parent::__construct(
            $id,
            $email,
            $password,
            UserType::REFERENT_ZONE(),
            $status,
            null, // ReferentZone doesn't require KYC documents (verified by admin)
            $phoneNumber, // Pass phoneNumber to parent
            null, // deviceToken
            null, // notificationPreferences
            $createdAt,
            $updatedAt
        );

        $this->coverageArea = $coverageArea;
        $this->zone = $zone;
    }

    /**
     * Create a new referent de zone
     */
    public static function createReferentZone(
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        GPS_Coordinates $coverageArea,
        string $zone
    ): self {
        return new self(
            UserId::generate(),
            $email,
            $password,
            $phoneNumber,
            $coverageArea,
            $zone,
            AccountStatus::PENDING() // Must be activated by admin
        );
    }

    /**
     * Check if referent can mediate disputes
     */
    public function canMediateDisputes(): bool
    {
        return $this->isActive() && ! $this->isLocked();
    }

    /**
     * Update coverage area
     */
    public function updateCoverageArea(GPS_Coordinates $newCoverageArea): void
    {
        $this->coverageArea = $newCoverageArea;
        $this->updatedAt = new DateTime;
    }

    /**
     * Update zone
     */
    public function updateZone(string $newZone): void
    {
        if (empty(trim($newZone))) {
            throw new \InvalidArgumentException('Zone cannot be empty');
        }

        $this->zone = $newZone;
        $this->updatedAt = new DateTime;
    }

    /**
     * Update phone number
     */
    public function updatePhoneNumber(PhoneNumber $newPhoneNumber): void
    {
        $this->phoneNumber = $newPhoneNumber;
        $this->updatedAt = new DateTime;
    }

    // Getters

    public function getPhoneNumber(): PhoneNumber
    {
        // ReferentZone always have a phone number, so we can safely assert it's not null
        if ($this->phoneNumber === null) {
            throw new \LogicException('ReferentZone must have a phone number');
        }

        return $this->phoneNumber;
    }

    public function getCoverageArea(): GPS_Coordinates
    {
        return $this->coverageArea;
    }

    public function getZone(): string
    {
        return $this->zone;
    }
}
