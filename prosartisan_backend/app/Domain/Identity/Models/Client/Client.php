<?php

namespace App\Domain\Identity\Models\Client;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use DateTime;

/**
 * Client entity representing a user seeking artisan services
 * Extends User with client-specific properties
 */
final class Client extends User
{
    private ?string $preferredPaymentMethod;

    public function __construct(
        UserId $id,
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        ?string $preferredPaymentMethod = null,
        ?AccountStatus $status = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        parent::__construct(
            $id,
            $email,
            $password,
            UserType::CLIENT(),
            $status,
            null, // Clients don't require KYC documents
            $phoneNumber, // Pass phoneNumber to parent
            null, // deviceToken
            null, // notificationPreferences
            $createdAt,
            $updatedAt
        );

        $this->preferredPaymentMethod = $preferredPaymentMethod;
    }

    /**
     * Create a new client
     */
    public static function createClient(
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        ?string $preferredPaymentMethod = null
    ): self {
        $client = new self(
            UserId::generate(),
            $email,
            $password,
            $phoneNumber,
            $preferredPaymentMethod,
            AccountStatus::ACTIVE() // Clients are active immediately
        );

        return $client;
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
     * Set preferred payment method
     */
    public function setPreferredPaymentMethod(string $paymentMethod): void
    {
        $this->preferredPaymentMethod = $paymentMethod;
        $this->updatedAt = new DateTime();
    }

    // Getters

    /**
     * Get phone number (clients always have a phone number)
     */
    public function getPhoneNumber(): PhoneNumber
    {
        // Clients always have a phone number, so we can safely assert it's not null
        if ($this->phoneNumber === null) {
            throw new \LogicException('Client must have a phone number');
        }
        return $this->phoneNumber;
    }

    public function getPreferredPaymentMethod(): ?string
    {
        return $this->preferredPaymentMethod;
    }
}
