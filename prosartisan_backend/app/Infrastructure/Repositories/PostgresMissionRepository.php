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
            'budget_min_centimes' => $mission->getBudgetMin()->toCentimes(),
            'budget_max_centimes' => $mission->getBudgetMax()->toCentimes(),
            'status' => $mission->getStatus()->getValue(),
            'created_at' => $mission->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];

        // Handle location based on database driver
        if (DB::getDriverName() === 'pgsql') {
            $data['location'] = DB::raw("ST_GeomFromText('POINT({$mission->getLocation()->getLongitude()} {$mission->getLocation()->getLatitude()})', 4326)");
        } else {
            // For SQLite/MySQL, store as JSON or separate columns
            $data['location'] = json_encode([
                'latitude' => $mission->getLocation()->getLatitude(),
                'longitude' => $mission->getLocation()->getLongitude()
            ]);
        }

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
        if (DB::getDriverName() === 'pgsql') {
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
        } else {
            $row = DB::table('missions')
                ->select([
                    'id',
                    'client_id',
                    'description',
                    'trade_category',
                    'location',
                    'budget_min_centimes',
                    'budget_max_centimes',
                    'status',
                    'created_at'
                ])
                ->where('id', $id->getValue())
                ->first();
        }

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
        if (DB::getDriverName() === 'pgsql') {
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
        } else {
            $rows = DB::table('missions')
                ->select([
                    'id',
                    'client_id',
                    'description',
                    'trade_category',
                    'location',
                    'budget_min_centimes',
                    'budget_max_centimes',
                    'status',
                    'created_at'
                ])
                ->where('client_id', $clientId->getValue())
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $rows->map(fn($row) => $this->mapRowToMission($row))->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function findOpenMissionsNearLocation(GPS_Coordinates $location, float $radiusKm): array
    {
        if (DB::getDriverName() === 'pgsql') {
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
        } else {
            // For SQLite, use a simple approximation (not geographically accurate but works for testing)
            $rows = DB::table('missions')
                ->select([
                    'id',
                    'client_id',
                    'description',
                    'trade_category',
                    'location',
                    'budget_min_centimes',
                    'budget_max_centimes',
                    'status',
                    'created_at'
                ])
                ->whereIn('status', [MissionStatus::OPEN, MissionStatus::QUOTED])
                ->get()
                ->filter(function ($row) use ($location, $radiusKm) {
                    $locationData = json_decode($row->location, true);
                    $missionLocation = new GPS_Coordinates($locationData['latitude'], $locationData['longitude']);
                    $distance = $location->distanceTo($missionLocation);
                    return $distance <= ($radiusKm * 1000); // Convert km to meters
                });
        }

        return $rows->map(fn($row) => $this->mapRowToMission($row))->toArray();
    }

    /**
     * Map database row to Mission domain object
     */
    private function mapRowToMission($row): Mission
    {
        // Handle location based on database driver
        if (DB::getDriverName() === 'pgsql') {
            $location = new GPS_Coordinates($row->latitude, $row->longitude);
        } else {
            // For SQLite/MySQL, parse JSON location
            $locationData = json_decode($row->location, true);
            $location = new GPS_Coordinates($locationData['latitude'], $locationData['longitude']);
        }

        return new Mission(
            MissionId::fromString($row->id),
            UserId::fromString($row->client_id),
            $row->description,
            TradeCategory::fromString($row->trade_category),
            $location,
            MoneyAmount::fromCentimes($row->budget_min_centimes),
            MoneyAmount::fromCentimes($row->budget_max_centimes),
            MissionStatus::fromString($row->status),
            [], // Quotes will be loaded separately if needed
            new DateTime($row->created_at)
        );
    }
}
