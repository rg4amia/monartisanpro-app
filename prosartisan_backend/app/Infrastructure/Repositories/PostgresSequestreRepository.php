<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Financial\Models\Sequestre\Sequestre;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Financial\Models\ValueObjects\SequestreStatus;
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use Illuminate\Support\Facades\DB;
use DateTime;

/**
 * PostgreSQL implementation of SequestreRepository
 */
final class PostgresSequestreRepository implements SequestreRepository
{
 private const TABLE = 'sequestres';

 public function save(Sequestre $sequestre): void
 {
  $data = [
   'id' => $sequestre->getId()->getValue(),
   'mission_id' => $sequestre->getMissionId()->getValue(),
   'client_id' => $sequestre->getClientId()->getValue(),
   'artisan_id' => $sequestre->getArtisanId()->getValue(),
   'total_amount_centimes' => $sequestre->getTotalAmount()->toCentimes(),
   'materials_amount_centimes' => $sequestre->getMaterialsAmount()->toCentimes(),
   'labor_amount_centimes' => $sequestre->getLaborAmount()->toCentimes(),
   'materials_released_centimes' => $sequestre->getMaterialsReleased()->toCentimes(),
   'labor_released_centimes' => $sequestre->getLaborReleased()->toCentimes(),
   'status' => $sequestre->getStatus()->getValue(),
   'created_at' => $sequestre->getCreatedAt()->format('Y-m-d H:i:s'),
   'updated_at' => now()->format('Y-m-d H:i:s'),
  ];

  DB::table(self::TABLE)->upsert($data, ['id'], array_keys($data));
 }

 public function findById(SequestreId $id): ?Sequestre
 {
  $row = DB::table(self::TABLE)
   ->where('id', $id->getValue())
   ->first();

  return $row ? $this->mapRowToSequestre($row) : null;
 }

 public function findByMissionId(MissionId $missionId): ?Sequestre
 {
  $row = DB::table(self::TABLE)
   ->where('mission_id', $missionId->getValue())
   ->first();

  return $row ? $this->mapRowToSequestre($row) : null;
 }

 public function findByClientId(UserId $clientId): array
 {
  $rows = DB::table(self::TABLE)
   ->where('client_id', $clientId->getValue())
   ->orderBy('created_at', 'desc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToSequestre($row))->toArray();
 }

 public function findByArtisanId(UserId $artisanId): array
 {
  $rows = DB::table(self::TABLE)
   ->where('artisan_id', $artisanId->getValue())
   ->orderBy('created_at', 'desc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToSequestre($row))->toArray();
 }

 public function findActive(): array
 {
  $rows = DB::table(self::TABLE)
   ->whereIn('status', [SequestreStatus::BLOCKED, SequestreStatus::PARTIAL])
   ->orderBy('created_at', 'desc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToSequestre($row))->toArray();
 }

 public function delete(SequestreId $id): void
 {
  DB::table(self::TABLE)->where('id', $id->getValue())->delete();
 }

 private function mapRowToSequestre($row): Sequestre
 {
  return new Sequestre(
   SequestreId::fromString($row->id),
   MissionId::fromString($row->mission_id),
   UserId::fromString($row->client_id),
   UserId::fromString($row->artisan_id),
   MoneyAmount::fromCentimes($row->total_amount_centimes),
   MoneyAmount::fromCentimes($row->materials_amount_centimes),
   MoneyAmount::fromCentimes($row->labor_amount_centimes),
   MoneyAmount::fromCentimes($row->materials_released_centimes),
   MoneyAmount::fromCentimes($row->labor_released_centimes),
   SequestreStatus::fromString($row->status),
   new DateTime($row->created_at)
  );
 }
}
