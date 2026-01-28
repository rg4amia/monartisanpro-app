<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Worksite\Models\Chantier\Chantier;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use App\Domain\Worksite\Models\ValueObjects\ChantierStatus;
use App\Domain\Worksite\Repositories\ChantierRepository;
use App\Domain\Worksite\Repositories\JalonRepository;
use DateTime;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL implementation of ChantierRepository
 */
final class PostgresChantierRepository implements ChantierRepository
{
    private const TABLE = 'chantiers';

    private JalonRepository $jalonRepository;

    public function __construct(JalonRepository $jalonRepository)
    {
        $this->jalonRepository = $jalonRepository;
    }

    public function save(Chantier $chantier): void
    {
        $data = [
            'id' => $chantier->getId()->getValue(),
            'mission_id' => $chantier->getMissionId()->getValue(),
            'client_id' => $chantier->getClientId()->getValue(),
            'artisan_id' => $chantier->getArtisanId()->getValue(),
            'status' => $chantier->getStatus()->getValue(),
            'started_at' => $chantier->getStartedAt()->format('Y-m-d H:i:s'),
            'completed_at' => $chantier->getCompletedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];

        // Check if chantier exists
        $exists = DB::table(self::TABLE)->where('id', $chantier->getId()->getValue())->exists();

        if (! $exists) {
            $data['created_at'] = now()->format('Y-m-d H:i:s');
        }

        DB::table(self::TABLE)->upsert($data, ['id'], array_keys($data));

        // Save all milestones
        foreach ($chantier->getAllMilestones() as $milestone) {
            $this->jalonRepository->save($milestone);
        }
    }

    public function findById(ChantierId $id): ?Chantier
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id->getValue())
            ->first();

        return $row ? $this->mapRowToChantier($row) : null;
    }

    public function findByMissionId(MissionId $missionId): ?Chantier
    {
        $row = DB::table(self::TABLE)
            ->where('mission_id', $missionId->getValue())
            ->first();

        return $row ? $this->mapRowToChantier($row) : null;
    }

    public function findActiveByArtisan(UserId $artisanId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('artisan_id', $artisanId->getValue())
            ->where('status', ChantierStatus::IN_PROGRESS)
            ->orderBy('started_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRowToChantier($row))->toArray();
    }

    public function findByClient(UserId $clientId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('client_id', $clientId->getValue())
            ->orderBy('started_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRowToChantier($row))->toArray();
    }

    public function findByArtisan(UserId $artisanId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('artisan_id', $artisanId->getValue())
            ->orderBy('started_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRowToChantier($row))->toArray();
    }

    private function mapRowToChantier($row): Chantier
    {
        $chantierId = ChantierId::fromString($row->id);

        // Load milestones for this chantier
        $milestones = $this->jalonRepository->findByChantierId($chantierId);

        return new Chantier(
            $chantierId,
            MissionId::fromString($row->mission_id),
            UserId::fromString($row->client_id),
            UserId::fromString($row->artisan_id),
            $milestones,
            ChantierStatus::fromString($row->status),
            new DateTime($row->started_at),
            $row->completed_at ? new DateTime($row->completed_at) : null
        );
    }
}
