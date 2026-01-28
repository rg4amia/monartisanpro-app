<?php

namespace App\Domain\Financial\Services;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;

/**
 * Interface for Mobile Money payment gateway integration
 *
 * Provides abstraction for different mobile money providers
 * (Wave, Orange Money, MTN) with unified payment operations.
 *
 * Requirements: 4.4, 4.5, 15.1, 15.4, 15.5
 */
interface MobileMoneyGateway
{
    /**
     * Block funds from a user's mobile money account for escrow
     *
     * @param  UserId  $userId  User whose funds to block
     * @param  PhoneNumber  $phoneNumber  User's mobile money phone number
     * @param  MoneyAmount  $amount  Amount to block
     * @param  string  $reference  Internal transaction reference
     */
    public function blockFunds(
        UserId $userId,
        PhoneNumber $phoneNumber,
        MoneyAmount $amount,
        string $reference
    ): MobileMoneyTransactionResult;

    /**
     * Transfer funds from one user to another
     *
     * @param  UserId  $fromUserId  Source user
     * @param  PhoneNumber  $fromPhone  Source phone number
     * @param  UserId  $toUserId  Destination user
     * @param  PhoneNumber  $toPhone  Destination phone number
     * @param  MoneyAmount  $amount  Amount to transfer
     * @param  string  $reference  Internal transaction reference
     */
    public function transferFunds(
        UserId $fromUserId,
        PhoneNumber $fromPhone,
        UserId $toUserId,
        PhoneNumber $toPhone,
        MoneyAmount $amount,
        string $reference
    ): MobileMoneyTransactionResult;

    /**
     * Refund funds to a user's mobile money account
     *
     * @param  UserId  $userId  User to refund
     * @param  PhoneNumber  $phoneNumber  User's mobile money phone number
     * @param  MoneyAmount  $amount  Amount to refund
     * @param  string  $reference  Internal transaction reference
     */
    public function refundFunds(
        UserId $userId,
        PhoneNumber $phoneNumber,
        MoneyAmount $amount,
        string $reference
    ): MobileMoneyTransactionResult;

    /**
     * Check the status of a mobile money transaction
     *
     * @param  string  $providerTransactionId  Provider's transaction ID
     */
    public function checkTransactionStatus(string $providerTransactionId): MobileMoneyTransactionStatus;

    /**
     * Get the provider name (Wave, Orange Money, MTN)
     */
    public function getProviderName(): string;

    /**
     * Check if this gateway supports the given phone number
     */
    public function supportsPhoneNumber(PhoneNumber $phoneNumber): bool;

    /**
     * Verify webhook signature for security
     *
     * @param  string  $payload  Raw webhook payload
     * @param  string  $signature  Signature from webhook headers
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;
}
