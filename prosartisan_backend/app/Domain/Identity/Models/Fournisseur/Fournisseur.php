<?php

namespace App\Domain\Identity\Models\Fournisseur;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;

/**
 * Fournisseur (Supplier) entity representing a construction materials supplier
 * Extends User with supplier-specific properties
 */
final class Fournisseur extends User
{
    private string $businessName;
    private GPS_Coordinates $shopLocation;
    private PhoneNumber $phoneNumber;
    private bool $isKYCVerified;

    public function __construct(
        UserId $id,
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        string $businessName,
        GPS_Coordinates $shopLocation,
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
            UserType::FOURNISSEUR(),
            $status,
            $kycDocuments,
            $createdAt,
            $updatedAt
        );

        $this->phoneNumber = $phoneNumber;
        $this->businessName = $businessName;
        $this->shopLocation = $shopLocation;
        $this->isKYCVerified = $isKYCVerified;
    }

    /**
     * Create a new fournisseur
     */
    public static function createFournisseur(
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        string $businessName,
        GPS_Coordinates $shopLocation,
        ?KYCDocuments $kycDocuments = null
    ): self {
        return new self(
            UserId::generate(),
            $email,
            $password,
            $phoneNumber,
            $businessName,
            $shopLocation,
            false,
            AccountStatus::PENDING(),
            $kycDocuments
        );
    }

    /**
     * Verify KYC documents and mark fournisseur as verified
     * Overrides parent method to also set isKYCVerified flag
     */
    public function verifyKYC(KYCDocuments $documents): void
    {
        parent::verifyKYC($documents);
        $this->isKYCVerified = true;
        $this->updatedAt = new DateTime();
    }

    /**
     * Check if fournisseur can validate jetons
     */
    public function canValidateJetons(): bool
    {
        return $this->isKYCVerified && $this->isActive() && !$this->isLocked();
    }

    /**
     * Update business name
     */
    public function updateBusinessName(string $newBusinessName): void
    {
        if (empty(trim($newBusinessName))) {
            throw new \InvalidArgumentException('Business name cannot be empty');
        }

        $this->businessName = $newBusinessName;
        $this->updatedAt = new DateTime();
    }

    /**
     * Update shop location
     */
    public function updateShopLocation(GPS_Coordinates $newLocation): void
    {
        $this->shopLocation = $newLocation;
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

    // Getters

    public function getBusinessName(): string
    {
        return $this->businessName;
    }

    public function getShopLocation(): GPS_Coordinates
    {
        return $this->shopLocation;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function isKYCVerified(): bool
    {
        return $this->isKYCVerified;
    }
}
