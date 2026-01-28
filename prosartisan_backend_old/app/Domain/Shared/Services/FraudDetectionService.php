<?php

namespace App\Domain\Shared\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Shared\ValueObjects\SuspiciousActivityResult;

/**
 * Fraud Detection Service Interface
 *
 * Detects suspicious activity patterns and flags accounts for review
 *
 * Requirements: 13.3, 13.7
 */
interface FraudDetectionService
{
    /**
     * Detect suspicious activity for a user
     *
     * Analyzes patterns like:
     * - Multiple failed login attempts
     * - Unusual transaction amounts
     * - Rapid succession of transactions
     * - Geographic anomalies
     *
     * @param  UserId  $userId  User to analyze
     * @return SuspiciousActivityResult Analysis result
     */
    public function detectSuspiciousActivity(UserId $userId): SuspiciousActivityResult;

    /**
     * Check for escrow circumvention attempts
     *
     * Detects patterns that suggest users are trying to bypass
     * the escrow system for direct payments
     *
     * @param  UserId  $artisanId  Artisan to check
     * @param  UserId  $clientId  Client to check
     * @return bool True if circumvention detected
     */
    public function detectEscrowCircumvention(UserId $artisanId, UserId $clientId): bool;

    /**
     * Flag account for manual review
     *
     * Marks an account as requiring admin review due to
     * suspicious activity patterns
     *
     * @param  UserId  $userId  User to flag
     * @param  string  $reason  Reason for flagging
     * @param  array  $evidence  Supporting evidence
     */
    public function flagAccountForReview(UserId $userId, string $reason, array $evidence = []): void;

    /**
     * Check if account is flagged for review
     *
     * @param  UserId  $userId  User to check
     * @return bool True if account is flagged
     */
    public function isAccountFlagged(UserId $userId): bool;

    /**
     * Analyze transaction patterns for anomalies
     *
     * @param  UserId  $userId  User to analyze
     * @param  MoneyAmount  $amount  Transaction amount
     * @return bool True if transaction appears suspicious
     */
    public function isTransactionSuspicious(UserId $userId, MoneyAmount $amount): bool;

    /**
     * Record failed login attempt for fraud analysis
     *
     * @param  UserId  $userId  User who failed login
     * @param  string  $ipAddress  IP address of attempt
     * @param  string  $userAgent  User agent string
     */
    public function recordFailedLoginAttempt(UserId $userId, string $ipAddress, string $userAgent): void;

    /**
     * Get fraud score for a user (0-100, higher = more suspicious)
     *
     * @param  UserId  $userId  User to score
     * @return int Fraud score
     */
    public function getFraudScore(UserId $userId): int;
}
