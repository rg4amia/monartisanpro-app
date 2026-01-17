<?php

namespace App\Infrastructure\Services\Security;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\Services\FraudDetectionService;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Shared\ValueObjects\SuspiciousActivityResult;
use DateTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Default implementation of FraudDetectionService
 *
 * Uses pattern matching and statistical analysis to detect fraud
 *
 * Requirements: 13.3, 13.7
 */
class DefaultFraudDetectionService implements FraudDetectionService
{
    private const CACHE_PREFIX = 'fraud_detection:';
    private const CACHE_TTL_MINUTES = 15;

    // Risk score thresholds
    private const LOW_RISK_THRESHOLD = 30;
    private const MEDIUM_RISK_THRESHOLD = 60;
    private const HIGH_RISK_THRESHOLD = 80;

    // Pattern detection constants
    private const MAX_FAILED_LOGINS_PER_HOUR = 10;
    private const MAX_TRANSACTIONS_PER_HOUR = 20;
    private const UNUSUAL_AMOUNT_MULTIPLIER = 5.0; // 5x average transaction
    private const MIN_TRANSACTIONS_FOR_ANALYSIS = 3;

    /**
     * {@inheritDoc}
     */
    public function detectSuspiciousActivity(UserId $userId): SuspiciousActivityResult
    {
        $cacheKey = self::CACHE_PREFIX . 'activity:' . $userId->getValue();

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return unserialize($cached);
        }

        $flags = [];
        $evidence = [];
        $riskScore = 0;

        // Check failed login attempts
        $failedLogins = $this->getRecentFailedLogins($userId);
        if ($failedLogins > self::MAX_FAILED_LOGINS_PER_HOUR) {
            $flags[] = 'EXCESSIVE_FAILED_LOGINS';
            $evidence[] = "Failed login attempts in last hour: {$failedLogins}";
            $riskScore += 25;
        }

        // Check transaction frequency
        $recentTransactions = $this->getRecentTransactionCount($userId);
        if ($recentTransactions > self::MAX_TRANSACTIONS_PER_HOUR) {
            $flags[] = 'HIGH_TRANSACTION_FREQUENCY';
            $evidence[] = "Transactions in last hour: {$recentTransactions}";
            $riskScore += 20;
        }

        // Check for unusual transaction amounts
        $unusualAmounts = $this->detectUnusualTransactionAmounts($userId);
        if ($unusualAmounts['count'] > 0) {
            $flags[] = 'UNUSUAL_TRANSACTION_AMOUNTS';
            $evidence[] = "Unusual transactions: {$unusualAmounts['count']}, avg deviation: {$unusualAmounts['avg_deviation']}%";
            $riskScore += 30;
        }

        // Check for geographic anomalies
        $geoAnomalies = $this->detectGeographicAnomalies($userId);
        if ($geoAnomalies['suspicious']) {
            $flags[] = 'GEOGRAPHIC_ANOMALY';
            $evidence[] = "Suspicious location pattern: {$geoAnomalies['reason']}";
            $riskScore += 15;
        }

        // Check for rapid account creation and activity
        $rapidActivity = $this->detectRapidAccountActivity($userId);
        if ($rapidActivity) {
            $flags[] = 'RAPID_ACCOUNT_ACTIVITY';
            $evidence[] = 'High activity shortly after account creation';
            $riskScore += 20;
        }

        // Cap risk score at 100
        $riskScore = min(100, $riskScore);

        $result = new SuspiciousActivityResult(
            $riskScore >= self::LOW_RISK_THRESHOLD,
            $riskScore,
            $flags,
            $evidence
        );

        // Cache result
        Cache::put($cacheKey, serialize($result), now()->addMinutes(self::CACHE_TTL_MINUTES));

        // Log high-risk activities
        if ($riskScore >= self::HIGH_RISK_THRESHOLD) {
            Log::warning('High-risk activity detected', [
                'user_id' => $userId->getValue(),
                'risk_score' => $riskScore,
                'flags' => $flags,
                'evidence' => $evidence,
            ]);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function detectEscrowCircumvention(UserId $artisanId, UserId $clientId): bool
    {
        // Check for patterns indicating escrow circumvention:
        // 1. Multiple missions between same parties without escrow
        // 2. Missions with very low amounts (to avoid escrow)
        // 3. Rapid mission creation and completion without proper workflow

        $suspiciousPatterns = 0;

        // Check for multiple direct interactions
        $directInteractions = DB::table('missions')
            ->where('client_id', $clientId->getValue())
            ->whereIn('id', function ($query) use ($artisanId) {
                $query->select('mission_id')
                    ->from('devis')
                    ->where('artisan_id', $artisanId->getValue())
                    ->where('status', 'ACCEPTED');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('sequestres')
                    ->whereColumn('sequestres.mission_id', 'missions.id');
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($directInteractions >= 3) {
            $suspiciousPatterns++;
        }

        // Check for unusually low transaction amounts
        $lowAmountMissions = DB::table('missions')
            ->join('devis', 'missions.id', '=', 'devis.mission_id')
            ->where('missions.client_id', $clientId->getValue())
            ->where('devis.artisan_id', $artisanId->getValue())
            ->where('devis.status', 'ACCEPTED')
            ->where('devis.total_amount_centimes', '<', 50000) // Less than 500 XOF
            ->where('missions.created_at', '>=', now()->subDays(30))
            ->count();

        if ($lowAmountMissions >= 5) {
            $suspiciousPatterns++;
        }

        // Check for rapid completion patterns
        $rapidCompletions = DB::table('chantiers')
            ->join('missions', 'chantiers.mission_id', '=', 'missions.id')
            ->where('missions.client_id', $clientId->getValue())
            ->where('chantiers.artisan_id', $artisanId->getValue())
            ->where('chantiers.status', 'COMPLETED')
            ->whereRaw('TIMESTAMPDIFF(HOUR, chantiers.started_at, chantiers.completed_at) < 1')
            ->where('chantiers.created_at', '>=', now()->subDays(30))
            ->count();

        if ($rapidCompletions >= 3) {
            $suspiciousPatterns++;
        }

        $isCircumventing = $suspiciousPatterns >= 2;

        if ($isCircumventing) {
            Log::warning('Escrow circumvention detected', [
                'artisan_id' => $artisanId->getValue(),
                'client_id' => $clientId->getValue(),
                'suspicious_patterns' => $suspiciousPatterns,
                'direct_interactions' => $directInteractions,
                'low_amount_missions' => $lowAmountMissions,
                'rapid_completions' => $rapidCompletions,
            ]);

            // Automatically flag both accounts
            $this->flagAccountForReview($artisanId, 'Escrow circumvention detected', [
                'partner_user_id' => $clientId->getValue(),
                'suspicious_patterns' => $suspiciousPatterns,
            ]);

            $this->flagAccountForReview($clientId, 'Escrow circumvention detected', [
                'partner_user_id' => $artisanId->getValue(),
                'suspicious_patterns' => $suspiciousPatterns,
            ]);
        }

        return $isCircumventing;
    }

    /**
     * {@inheritDoc}
     */
    public function flagAccountForReview(UserId $userId, string $reason, array $evidence = []): void
    {
        DB::table('user_activity_logs')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'user_id' => $userId->getValue(),
            'action' => 'account_flagged_for_review',
            'details' => json_encode([
                'reason' => $reason,
                'evidence' => $evidence,
                'flagged_at' => now(),
                'requires_admin_review' => true,
            ]),
            'created_at' => now(),
        ]);

        // Set flag in cache for quick access
        Cache::put(
            self::CACHE_PREFIX . 'flagged:' . $userId->getValue(),
            true,
            now()->addDays(7) // Flags expire after 7 days if not reviewed
        );

        Log::warning('Account flagged for review', [
            'user_id' => $userId->getValue(),
            'reason' => $reason,
            'evidence' => $evidence,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function isAccountFlagged(UserId $userId): bool
    {
        // Check cache first
        $cached = Cache::get(self::CACHE_PREFIX . 'flagged:' . $userId->getValue());
        if ($cached !== null) {
            return $cached;
        }

        // Check database for recent flags
        $flagged = DB::table('user_activity_logs')
            ->where('user_id', $userId->getValue())
            ->where('action', 'account_flagged_for_review')
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();

        // Cache result
        Cache::put(
            self::CACHE_PREFIX . 'flagged:' . $userId->getValue(),
            $flagged,
            now()->addMinutes(self::CACHE_TTL_MINUTES)
        );

        return $flagged;
    }

    /**
     * {@inheritDoc}
     */
    public function isTransactionSuspicious(UserId $userId, MoneyAmount $amount): bool
    {
        // Get user's transaction history
        $avgAmount = $this->getUserAverageTransactionAmount($userId);

        if ($avgAmount === null) {
            // No history, check against system-wide averages
            $avgAmount = $this->getSystemAverageTransactionAmount();
        }

        // Check if amount is significantly higher than average
        $amountCentimes = $amount->getCentimes();
        $threshold = $avgAmount * self::UNUSUAL_AMOUNT_MULTIPLIER;

        $isSuspicious = $amountCentimes > $threshold;

        if ($isSuspicious) {
            Log::info('Suspicious transaction amount detected', [
                'user_id' => $userId->getValue(),
                'amount_centimes' => $amountCentimes,
                'user_avg_centimes' => $avgAmount,
                'threshold_centimes' => $threshold,
            ]);
        }

        return $isSuspicious;
    }

    /**
     * {@inheritDoc}
     */
    public function recordFailedLoginAttempt(UserId $userId, string $ipAddress, string $userAgent): void
    {
        DB::table('user_activity_logs')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'user_id' => $userId->getValue(),
            'action' => 'failed_login_attempt',
            'details' => json_encode([
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'timestamp' => now(),
            ]),
            'created_at' => now(),
        ]);

        // Clear cached activity analysis to force re-evaluation
        Cache::forget(self::CACHE_PREFIX . 'activity:' . $userId->getValue());
    }

    /**
     * {@inheritDoc}
     */
    public function getFraudScore(UserId $userId): int
    {
        $result = $this->detectSuspiciousActivity($userId);
        return $result->getRiskScore();
    }

    /**
     * Get recent failed login attempts count
     */
    private function getRecentFailedLogins(UserId $userId): int
    {
        return DB::table('user_activity_logs')
            ->where('user_id', $userId->getValue())
            ->where('action', 'failed_login_attempt')
            ->where('created_at', '>=', now()->subHour())
            ->count();
    }

    /**
     * Get recent transaction count
     */
    private function getRecentTransactionCount(UserId $userId): int
    {
        return DB::table('transactions')
            ->where(function ($query) use ($userId) {
                $query->where('from_user_id', $userId->getValue())
                    ->orWhere('to_user_id', $userId->getValue());
            })
            ->where('created_at', '>=', now()->subHour())
            ->count();
    }

    /**
     * Detect unusual transaction amounts
     */
    private function detectUnusualTransactionAmounts(UserId $userId): array
    {
        $avgAmount = $this->getUserAverageTransactionAmount($userId);

        if ($avgAmount === null) {
            return ['count' => 0, 'avg_deviation' => 0];
        }

        $recentTransactions = DB::table('transactions')
            ->where(function ($query) use ($userId) {
                $query->where('from_user_id', $userId->getValue())
                    ->orWhere('to_user_id', $userId->getValue());
            })
            ->where('created_at', '>=', now()->subDays(7))
            ->pluck('amount_centimes');

        $unusualCount = 0;
        $totalDeviation = 0;

        foreach ($recentTransactions as $amount) {
            $deviation = abs($amount - $avgAmount) / $avgAmount * 100;
            if ($deviation > 200) { // More than 200% deviation
                $unusualCount++;
                $totalDeviation += $deviation;
            }
        }

        return [
            'count' => $unusualCount,
            'avg_deviation' => $unusualCount > 0 ? $totalDeviation / $unusualCount : 0,
        ];
    }

    /**
     * Detect geographic anomalies
     */
    private function detectGeographicAnomalies(UserId $userId): array
    {
        // This is a simplified implementation
        // In a real system, you'd analyze GPS patterns, IP geolocation, etc.

        return ['suspicious' => false, 'reason' => ''];
    }

    /**
     * Detect rapid account activity
     */
    private function detectRapidAccountActivity(UserId $userId): bool
    {
        $user = DB::table('users')->where('id', $userId->getValue())->first();

        if (!$user) {
            return false;
        }

        $accountAge = now()->diffInDays($user->created_at);

        // If account is less than 7days old, check activity level
        if ($accountAge < 7) {
            $activityCount = DB::table('transactions')
                ->where(function ($query) use ($userId) {
                    $query->where('from_user_id', $userId->getValue())
                        ->orWhere('to_user_id', $userId->getValue());
                })
                ->count();

            // More than 10 transactions in first week is suspicious
            return $activityCount > 10;
        }

        return false;
    }

    /**
     * Get user's average transaction amount
     */
    private function getUserAverageTransactionAmount(UserId $userId): ?float
    {
        $avg = DB::table('transactions')
            ->where(function ($query) use ($userId) {
                $query->where('from_user_id', $userId->getValue())
                    ->orWhere('to_user_id', $userId->getValue());
            })
            ->where('status', 'COMPLETED')
            ->avg('amount_centimes');

        return $avg ? (float) $avg : null;
    }

    /**
     * Get system-wide average transaction amount
     */
    private function getSystemAverageTransactionAmount(): float
    {
        $avg = DB::table('transactions')
            ->where('status', 'COMPLETED')
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('amount_centimes');

        return $avg ? (float) $avg : 100000; // Default to 1000 XOF
    }
}
