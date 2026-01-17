<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Reputation\Models\Rating\Rating;
use App\Domain\Reputation\Models\ValueObjects\RatingId;
use App\Domain\Reputation\Models\ValueObjects\RatingValue;
use App\Domain\Reputation\Repositories\RatingRepository;
use Illuminate\Support\Facades\DB;
use DateTime;

/**
 * PostgreSQL implementation of RatingRepository
 */
class PostgresRatingRepository implements RatingRepository
{
    public function save(Rating $rating): void
    {
        DB::table('ratings')->updateOrInsert(
            ['id' => $rating->getId()->getValue()],
            [
                'mission_id' => $rating->getMissionId()->getValue(),
                'client_id' => $rating->getClientId()->getValue(),
                'artisan_id' => $rating->getArtisanId()->getValue(),
                'rating' => $rating->getRating()->getValue(),
                'comment' => $rating->getComment(),
                'created_at' => $rating->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $rating->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findById(RatingId $id): ?Rating
    {
        $data = DB::table('ratings')
            ->where('id', $id->getValue())
            ->first();

        if (!$data) {
            return null;
        }

        return $this->mapToRating($data);
    }

    public function findByMissionId(MissionId $missionId): ?Rating
    {
        $data = DB::table('ratings')
            ->where('mission_id', $missionId->getValue())
            ->first();

        if (!$data) {
            return null;
        }

        return $this->mapToRating($data);
    }

    public function findByArtisanId(UserId $artisanId): array
    {
        $results = DB::table('ratings')
            ->where('artisan_id', $artisanId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();

        return $results->map(fn($data) => $this->mapToRating($data))->toArray();
    }

    public function findByClientId(UserId $clientId): array
    {
        $results = DB::table('ratings')
            ->where('client_id', $clientId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();

        return $results->map(fn($data) => $this->mapToRating($data))->toArray();
    }

    public function getAverageRatingForArtisan(UserId $artisanId): float
    {
        $average = DB::table('ratings')
            ->where('artisan_id', $artisanId->getValue())
            ->avg('rating');

        return $average ? (float) $average : 0.0;
    }

    public function countRatingsForArtisan(UserId $artisanId): int
    {
        return DB::table('ratings')
            ->where('artisan_id', $artisanId->getValue())
            ->count();
    }

    privatefunction mapToRating($data): Rating
    {
        return new Rating(
            RatingId::fromString($data->id),
            MissionId::fromString($data->mission_id),
            UserId::fromString($data->client_id),
            UserId::fromString($data->artisan_id),
            RatingValue::fromInt($data->rating),
            $data->comment,
            new DateTime($data->created_at),
            new DateTime($data->updated_at)
        );
    }
}
