<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\AccountLockedException;
use App\Domain\Identity\Exceptions\AccountSuspendedException;
use App\Domain\Identity\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Exceptions\InvalidTokenException;
use App\Domain\Identity\Exceptions\OTPGenerationException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AuthToken;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\OTP;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Laravel implementation of AuthenticationService
 *
 * Uses:
 * - Laravel Sanctum for JWT token generation
 * - Laravel Cache for OTP storage
 * - External SMS service for OTP delivery
 *
 * Requirements: 1.3, 1.5, 1.6
 */
class LaravelAuthenticationService implements AuthenticationService
{
    private const TOKEN_EXPIRATION_HOURS = 24;

    private const OTP_CACHE_PREFIX = 'otp:';

    private const OTP_EXPIRATION_MINUTES = 5;

    public function __construct(
        private UserRepository $userRepository,
        private ?SMSService $smsService = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function authenticate(Email $email, string $password): AuthToken
    {
        // Find user by email
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            throw new InvalidCredentialsException;
        }

        // Check if account is locked
        if ($user->isLocked()) {
            throw new AccountLockedException($user->getLockedUntil());
        }

        // Check if account is suspended
        if ($user->isSuspended()) {
            throw new AccountSuspendedException;
        }

        // Verify password
        if (! $user->verifyPassword($password)) {
            // Record failed attempt
            $user->recordFailedLoginAttempt();
            $this->userRepository->save($user);

            throw new InvalidCredentialsException;
        }

        // Reset failed attempts on successful login
        $user->resetFailedLoginAttempts();
        $this->userRepository->save($user);

        // Generate and return token
        return $this->generateToken($user);
    }

    /**
     * {@inheritDoc}
     */
    public function generateOTP(PhoneNumber $phone): OTP
    {
        try {
            // Generate OTP
            $otp = OTP::generate($phone);

            // Store OTP in cache with expiration
            $cacheKey = self::OTP_CACHE_PREFIX.$phone->getValue();
            Cache::put($cacheKey, $otp->getCode(), now()->addMinutes(self::OTP_EXPIRATION_MINUTES));

            // Send OTP via SMS if service is available
            if ($this->smsService !== null) {
                $message = "Votre code de vérification ProSartisan est: {$otp->getCode()}. Valide pendant 5 minutes.";
                $this->smsService->send($phone, $message);
            } else {
                // Log OTP for development/testing
                Log::info("OTP generated for {$phone->getValue()}: {$otp->getCode()}");
            }

            return $otp;
        } catch (\Exception $e) {
            throw new OTPGenerationException('Failed to generate or send OTP', $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function verifyOTP(PhoneNumber $phone, string $code): bool
    {
        $cacheKey = self::OTP_CACHE_PREFIX.$phone->getValue();
        $storedCode = Cache::get($cacheKey);

        if ($storedCode === null) {
            return false;
        }

        // Verify code matches
        if ($storedCode !== $code) {
            return false;
        }

        // Remove OTP from cache after successful verification
        Cache::forget($cacheKey);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function generateToken(User $user): AuthToken
    {
        // Create token payload
        $payload = [
            'user_id' => $user->getId()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'user_type' => $user->getType()->getValue(),
            'issued_at' => time(),
        ];

        // Calculate expiration
        $expiresAt = new DateTime('+'.self::TOKEN_EXPIRATION_HOURS.' hours');

        // Generate JWT token using simple encoding
        // In production, use a proper JWT library like firebase/php-jwt
        $token = $this->encodeJWT($payload, $expiresAt);

        return new AuthToken($token, $expiresAt);
    }

    /**
     * {@inheritDoc}
     */
    public function verifyToken(string $token): string
    {
        try {
            $payload = $this->decodeJWT($token);

            // Check expiration
            if (isset($payload['expires_at']) && time() > $payload['expires_at']) {
                throw new InvalidTokenException('Token has expired');
            }

            // Return user ID
            if (! isset($payload['user_id'])) {
                throw new InvalidTokenException('Invalid token payload');
            }

            return $payload['user_id'];
        } catch (\Exception $e) {
            throw new InvalidTokenException('Invalid or expired token', 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function refreshToken(string $token): AuthToken
    {
        // Verify current token
        $userId = $this->verifyToken($token);

        // Find user
        $user = $this->userRepository->findById(UserId::fromString($userId));

        if ($user === null) {
            throw new InvalidTokenException('User not found');
        }

        // Generate new token
        return $this->generateToken($user);
    }

    /**
     * Encode payload into JWT token
     *
     * @param  array  $payload  Token payload
     * @param  DateTime  $expiresAt  Expiration time
     * @return string JWT token
     */
    private function encodeJWT(array $payload, DateTime $expiresAt): string
    {
        // Add expiration to payload
        $payload['expires_at'] = $expiresAt->getTimestamp();

        // Get secret key from config
        $secret = config('app.key');

        // Create header
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        // Encode header and payload
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        // Create signature
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        // Return complete token
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Decode JWT token and verify signature
     *
     * @param  string  $token  JWT token
     * @return array Token payload
     *
     * @throws InvalidTokenException
     */
    private function decodeJWT(string $token): array
    {
        // Split token into parts
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidTokenException('Invalid token format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Get secret key
        $secret = config('app.key');

        // Verify signature
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
        $expectedSignatureEncoded = $this->base64UrlEncode($expectedSignature);

        if ($signatureEncoded !== $expectedSignatureEncoded) {
            throw new InvalidTokenException('Invalid token signature');
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if ($payload === null) {
            throw new InvalidTokenException('Invalid token payload');
        }

        return $payload;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
