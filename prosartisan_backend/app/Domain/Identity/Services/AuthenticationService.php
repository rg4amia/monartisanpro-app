<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AuthToken;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\OTP;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;

/**
 * Domain service for authentication operations
 *
 * Handles:
 * - User authentication with JWT token generation
 * - OTP generation and verification for two-factor authentication
 * - Account lockout logic after failed attempts
 *
 * Requirements: 1.3, 1.5, 1.6
 */
interface AuthenticationService
{
    /**
     * Authenticate a user with email and password
     *
     * Returns an AuthToken containing a JWT token with 24-hour expiration
     * Throws exception if credentials are invalid or account is locked
     *
     * @param  Email  $email  User's email address
     * @param  string  $password  Plain text password
     * @return AuthToken JWT authentication token
     *
     * @throws \App\Domain\Identity\Exceptions\InvalidCredentialsException
     * @throws \App\Domain\Identity\Exceptions\AccountLockedException
     * @throws \App\Domain\Identity\Exceptions\AccountSuspendedException
     */
    public function authenticate(Email $email, string $password): AuthToken;

    /**
     * Generate an OTP for a phone number
     *
     * Creates a 6-digit OTP code with 5-minute expiration
     * Sends the OTP via SMS to the phone number
     *
     * @param  PhoneNumber  $phone  Phone number to send OTP to
     * @return OTP Generated OTP object
     *
     * @throws \App\Domain\Identity\Exceptions\OTPGenerationException
     */
    public function generateOTP(PhoneNumber $phone): OTP;

    /**
     * Verify an OTP code for a phone number
     *
     * Checks if the provided code matches the stored OTP
     * Returns false if OTP is expired or doesn't match
     *
     * @param  PhoneNumber  $phone  Phone number to verify
     * @param  string  $code  6-digit OTP code
     * @return bool True if OTP is valid, false otherwise
     */
    public function verifyOTP(PhoneNumber $phone, string $code): bool;

    /**
     * Generate a JWT token for a user
     *
     * Creates a JWT token with 24-hour expiration
     * Token includes user ID, email, and user type in payload
     *
     * @param  User  $user  User to generate token for
     * @return AuthToken JWT authentication token
     */
    public function generateToken(User $user): AuthToken;

    /**
     * Verify and decode a JWT token
     *
     * Validates the token signature and expiration
     * Returns the user ID from the token payload
     *
     * @param  string  $token  JWT token string
     * @return string User ID from token
     *
     * @throws \App\Domain\Identity\Exceptions\InvalidTokenException
     */
    public function verifyToken(string $token): string;

    /**
     * Refresh an authentication token
     *
     * Generates a new token with extended expiration
     *
     * @param  string  $token  Current JWT token
     * @return AuthToken New JWT authentication token
     *
     * @throws \App\Domain\Identity\Exceptions\InvalidTokenException
     */
    public function refreshToken(string $token): AuthToken;
}
