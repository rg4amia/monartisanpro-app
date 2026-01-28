<?php

namespace App\Domain\Worksite\Services;

use App\Domain\Identity\Models\ValueObjects\OTP;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Worksite\Models\ValueObjects\ProofOfDelivery;
use App\Domain\Worksite\Services\DTOs\ValidationResult;

/**
 * Domain service for validating milestone proofs
 *
 * Handles GPS verification and OTP fallback for milestone validation
 * Requirements: 6.2, 6.4, 6.5
 */
interface MilestoneValidationService
{
    /**
     * Validate proof of delivery
     *
     * Requirement 6.2: Validate GPS-tagged photo with timestamp
     */
    public function validateProof(ProofOfDelivery $proof): ValidationResult;

    /**
     * Generate OTP for client validation
     *
     * Requirement 6.4: OTP fallback when GPS is unavailable
     */
    public function generateOTP(PhoneNumber $clientPhone): OTP;

    /**
     * Verify OTP code
     *
     * Requirement 6.4: Verify OTP for milestone validation
     */
    public function verifyOTP(PhoneNumber $phone, string $code): bool;
}
