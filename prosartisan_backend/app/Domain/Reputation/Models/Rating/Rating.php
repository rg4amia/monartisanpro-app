<?php

namespace App\Domain\Reputation\Models\Rating;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Reputation\Models\ValueObjects\RatingId;
use App\Domain\Reputation\Models\ValueObjects\RatingValue;
use DateTime;

/**
 * Rating entity representing client feedback on artisan work
 */
class Rating
{
    private RatingId $id;

    private MissionId $missionId;

    private UserId $clientId;

    private UserId $artisanId;

    private RatingValue $rating;

    private ?string $comment;

    private DateTime $createdAt;

    private DateTime $updatedAt;

    public function __construct(
        RatingId $id,
        MissionId $missionId,
        UserId $clientId,
        UserId $artisanId,
        RatingValue $rating,
        ?string $comment = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->missionId = $missionId;
        $this->clientId = $clientId;
        $this->artisanId = $artisanId;
        $this->rating = $rating;
        $this->comment = $comment;
        $this->createdAt = $createdAt ?? new DateTime;
        $this->updatedAt = $updatedAt ?? new DateTime;
    }

    public static function create(
        MissionId $missionId,
        UserId $clientId,
        UserId $artisanId,
        RatingValue $rating,
        ?string $comment = null
    ): self {
        return new self(
            RatingId::generate(),
            $missionId,
            $clientId,
            $artisanId,
            $rating,
            $comment
        );
    }

    public function updateRating(RatingValue $rating, ?string $comment = null): void
    {
        $this->rating = $rating;
        $this->comment = $comment;
        $this->updatedAt = new DateTime;
    }

    public function getId(): RatingId
    {
        return $this->id;
    }

    public function getMissionId(): MissionId
    {
        return $this->missionId;
    }

    public function getClientId(): UserId
    {
        return $this->clientId;
    }

    public function getArtisanId(): UserId
    {
        return $this->artisanId;
    }

    public function getRating(): RatingValue
    {
        return $this->rating;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
