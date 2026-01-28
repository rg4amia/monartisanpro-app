<?php

namespace App\Domain\Shared\ValueObjects;

use DateTime;

/**
 * Value Object representing the result of suspicious activity analysis
 */
final class SuspiciousActivityResult
{
    private bool $isSuspicious;

    private int $riskScore; // 0-100

    private array $flags;

    private array $evidence;

    private DateTime $analyzedAt;

    public function __construct(
        bool $isSuspicious,
        int $riskScore,
        array $flags = [],
        array $evidence = [],
        ?DateTime $analyzedAt = null
    ) {
        if ($riskScore < 0 || $riskScore > 100) {
            throw new \InvalidArgumentException('Risk score must be between 0 and 100');
        }

        $this->isSuspicious = $isSuspicious;
        $this->riskScore = $riskScore;
        $this->flags = $flags;
        $this->evidence = $evidence;
        $this->analyzedAt = $analyzedAt ?? new DateTime;
    }

    public static function clean(int $riskScore = 0): self
    {
        return new self(false, $riskScore);
    }

    public static function suspicious(int $riskScore, array $flags, array $evidence = []): self
    {
        return new self(true, $riskScore, $flags, $evidence);
    }

    public function isSuspicious(): bool
    {
        return $this->isSuspicious;
    }

    public function getRiskScore(): int
    {
        return $this->riskScore;
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function getEvidence(): array
    {
        return $this->evidence;
    }

    public function getAnalyzedAt(): DateTime
    {
        return $this->analyzedAt;
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags);
    }

    public function toArray(): array
    {
        return [
            'is_suspicious' => $this->isSuspicious,
            'risk_score' => $this->riskScore,
            'flags' => $this->flags,
            'evidence' => $this->evidence,
            'analyzed_at' => $this->analyzedAt->format('Y-m-d H:i:s'),
        ];
    }
}
