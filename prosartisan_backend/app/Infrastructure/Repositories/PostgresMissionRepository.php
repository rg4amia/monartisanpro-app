<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\Mission\Mission;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Marketplace\Models\ValueObjects\MissionStatus;
use App\Domain\Marketplace\Repositories\MissionRepository;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL implementation of MissionRepository
 *
 * Uses PostGIS for efficient geospatial queries
 */
class PostgresMissionRepository implements MissionRepository
{
    /**
     * {@inheritDoc}
     */
    public function save(Mission $mission): void
    {
        $data = [
            'id' => $mission->getId()->getValue(),
            'client_id' => $mission->getClientId()->getValue(),
            'description' => $mission->getDescription(),
            'trade_category' => $mission->getCategory()->getValue(),
            'location' => DB::raw("ST_GeomFromText('POINT({$mission->getLocation()->getLongitude()} {$mission->getLocation()->getLatitude()})', 4326)"),
            'budget_min_centimes' => $mission->getBudgetMin()->toCentimes(),
            'budget_max_centimes' => $mission->getBudgetMax()->toCentimes(),
            'status' => $mission->getStatus()->getValue(),
            'created_at' => $mission->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];

        DB::table('missions')->updateOrInsert(
            ['id' => $mission->getId()->getValue()],
            $data
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findById(MissionId $id): ?Mission
    {
        $row = DB::table('missions')
            ->select([
                'id',
                'client_id',
                'description',
                'trade_category',
                DB::raw('ST_X(location) as longitude'),
                DB::raw('ST_Y(location) as latitude'),
                'budget_min_centimes',
                'budget_max_centimes',
                'status',
                'created_at'
            ])
            ->where('id', $id->getValue())
            ->first();

        if (!$row) {
            return null;
        }

        return $this->mapRowToMission($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findByClientId(UserId $clientId): array
    {
        $rows = DB::table('missions')
            ->select([
                'id',
                'client_id',
                'description',
                'trade_category',
                DB::raw('ST_X(location) as longitude'),
                DB::raw('ST_Y(location) as latitude'),
                'budget_min_centimes',
                'budget_max_centimes',
                'status',
                'created_at'
            ])
            ->where('client_id', $clientId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToMission($row))->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function findOpenMissionsNearLocation(GPS_Coordinates $location, float $radiusKm): array
    {
        $rows = DB::table('missions')
            ->select([
                'id',
                'client_id',
                'description',
                'trade_category',
                DB::raw('ST_X(location) as longitude'),
                DB::raw('ST_Y(location) as latitude'),
                'budget_min_centimes',
                'budget_max_centimes',
                'status',
                'created_at',
                DB::raw("ST_Distance(location, ST_GeomFromText('POINT({$location->getLongitude()} {$location->getLatitude()})', 4326)::geography) as distance_meters")
            ])
            ->whereRaw("ST_DWithin(location, ST_GeomFromText('POINT({$location->getLongitude()} {$location->getLatitude()})', 4326)::geography, ?)", [$radiusKm * 1000])
            ->whereIn('status', [MissionStatus::OPEN, MissionStatus::QUOTED])
            ->orderBy('distance_meters')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToMission($row))->toArray();
    }

    /**
     * Map database row to Mission domain object
     */
    private function mapRowToMission($row): Mission
    {
        return new Mission(
            MissionId::fromString($row->id),
            UserId::fromString($row->client_id),
            $row->description,
            TradeCategory::fromString($row->trade_category),
            new GPS_Coordinates($row->latitude, $row->longitude),
            MoneyAmount::fromCentimes($row->budget_min_centimes),
            MoneyAmount::fromCentimes($row->budget_max_centimes),
            MissionStatus::fromString($row->status),
            [], // Quotes will be loaded separately if needed
            new DateTime($row->created_at)
        );
    }
}
