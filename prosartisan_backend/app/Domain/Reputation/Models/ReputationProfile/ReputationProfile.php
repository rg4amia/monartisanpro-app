<?php

namespace App\Domain\Reputation\Models\ReputationProfile;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Reputation\Models\ValueObjects\NZassaScore;
use App\Domain\Reputation\Models\ValueObjects\ProfileId;
use App\Domain\Reputation\Models\ValueObjects\ReputationMetrics;
use App\Domain\Reputation\Models\ValueObjects\ScoreSnapshot;
use DateTime;

/**
 * Reputation Profile aggregate root
 */
class ReputationProfile
{
    private ProfileId $id;

    private UserId $artisanId;

    private NZassaScore $currentScore;

    private array $scoreHistory; // Collection of ScoreSnapshot

    private ReputationMetrics $metrics;

    private DateTime $lastCalculatedAt;

    private DateTime $createdAt;

    private DateTime $updatedAt;

    public function __construct(
        ProfileId $id,
        UserId $artisanId,
        NZassaScore $currentScore,
        ReputationMetrics $metrics,
        array $scoreHistory = [],
        ?DateTime $lastCalculatedAt = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->artisanId = $artisanId;
        $this->currentScore = $currentScore;
        $this->metrics = $metrics;
        $this->scoreHistory = $scoreHistory;
        $this->lastCalculatedAt = $lastCalculatedAt ?? new DateTime;
        $this->createdAt = $createdAt ?? new DateTime;
        $this->updatedAt = $updatedAt ?? new DateTime;
    }

    public static function create(UserId $artisanId): self
    {
        return new self(
            ProfileId::generate(),
            $artisanId,
            NZassaScore::zero(),
            ReputationMetrics::empty()
        );
    }

    public function recalculateScore(ReputationMetrics $newMetrics, NZassaScore $newScore, string $reason): void
    {
        $oldScore = $this->currentScore;

        // Add snapshot to history
        $this->scoreHistory[] = ScoreSnapshot::create($oldScore, "Previous score before recalculation: {$reason}");

        // Update current values
        $this->currentScore = $newScore;
        $this->metrics = $newMetrics;
        $this->lastCalculatedAt = new DateTime;
        $this->updatedAt = new DateTime;

        // Add new score to history
        $this->scoreHistory[] = ScoreSnapshot::create($newScore, $reason);
    }

    public function getId(): ProfileId
    {
        return $this->id;
    }

    public function getArtisanId(): UserId
    {
        return $this->artisanId;
    }

    public function getCurrentScore(): NZassaScore
    {
        return $this->currentScore;
    }

    public function getScoreHistory(): array
    {
        return $this->scoreHistory;
    }

    public function getMetrics(): ReputationMetrics
    {
        return $this->metrics;
    }

    public function getLastCalculatedAt(): DateTime
    {
        return $this->lastCalculatedAt;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function isEligibleForMicroCredit(): bool
    {
        return $this->currentScore->isEligibleForCredit();
    }

    public function addScoreSnapshot(string $reason): void
    {
        $this->scoreHistory[] = ScoreSnapshot::create($this->currentScore, $reason);
        $this->updatedAt = new DateTime;
    }
}
