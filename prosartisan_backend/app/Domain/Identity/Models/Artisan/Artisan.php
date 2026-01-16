<?php

namespace App\Domain\Identity\Models\Artisan;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;
use InvalidArgumentException;

/**
 * Artisan entity representing a skilled tradesperson
 * Extends User with artisan-specific properties and behaviors
 */
final class Artisan extends User
{
    private TradeCategory $category;
    private PhoneNumber $phoneNumber;
    private GPS_Coordinates $location;
    private bool $isKYCVerified;

    public function __construct(
        UserId $id,
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        TradeCategory $category,
        GPS_Coordinates $location,
        bool $isKYCVerified = false,
        ?AccountStatus $status = null,
        ?KYCDocuments $kycDocuments = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        parent::__construct(
            $id,
            $email,
            $password,
            UserType::ARTISAN(),
            $status,
            $kycDocuments,
            $createdAt,
            $updatedAt
        );

        $this->phoneNumber = $phoneNumber;
        $this->category = $category;
        $this->location = $location;
        $this->isKYCVerified = $isKYCVerified;
    }

    /**
     * Create a new artisan
     */
    public static function createArtisan(
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        TradeCategory $category,
        GPS_Coordinates $location,
        ?KYCDocuments $kycDocuments = null
    ): self {
        return new self(
            UserId::generate(),
            $email,
            $password,
            $phoneNumber,
            $category,
            $location,
            false,
            AccountStatus::PENDING(),
            $kycDocuments
        );
    }

    /**
     * Verify KYC documents and mark artisan as verified
     * Overrides parent method to also set isKYCVerified flag
     */
    public function verifyKYC(KYCDocuments $documents): void
    {
        parent::verifyKYC($documents);
        $this->isKYCVerified = true;
        $this->updatedAt = new DateTime();
    }

    /**
     * Check if artisan can accept missions
     * Requirement 1.4: Unverified artisans cannot accept missions
     */
    public function canAcceptMissions(): bool
    {
        return $this->isKYCVerified && $this->isActive() && !$this->isLocked();
    }

    /**
     * Update artisan location
     */
    public function updateLocation(GPS_Coordinates $newLocation): void
    {
        $this->location = $newLocation;
        $this->updatedAt = new DateTime();
    }

    /**
     * Change trade category
     */
    public function changeCategory(TradeCategory $newCategory): void
    {
        $this->category = $newCategory;
        $this->updatedAt = new DateTime();
    }

    /**
     * Update phone number
     */
    public function updatePhoneNumber(PhoneNumber $newPhoneNumber): void
    {
        $this->phoneNumber = $newPhoneNumber;
        $this->updatedAt = new DateTime();
    }

    /**
     * Get blurred location for privacy (50m radius)
     */
    public function getBlurredLocation(): GPS_Coordinates
    {
        return $this->location->blur(50);
    }

    // Getters

    public function getCategory(): TradeCategory
    {
        return $this->category;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getLocation(): GPS_Coordinates
    {
        return $this->location;
    }

    public function isKYCVerified(): bool
    {
        return $this->isKYCVerified;
    }
}
