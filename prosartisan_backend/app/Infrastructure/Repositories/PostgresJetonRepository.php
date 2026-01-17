<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Financial\Models\JetonMateriel\JetonMateriel;
use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Financial\Models\ValueObjects\JetonCode;
use App\Domain\Financial\Models\ValueObjects\JetonStatus;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Financial\Repositories\JetonRepository;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use Illuminate\Support\Facades\DB;
use DateTime;

/**
 * PostgreSQL implementation of JetonRepository
 */
final class PostgresJetonRepository implements JetonRepository
{
 private const TABLE = 'jetons_materiel';

 public function save(JetonMateriel $jeton): void
 {
  $data = [
   'id' => $jeton->getId()->getValue(),
   'sequestre_id' => $jeton->getSequestreId()->getValue(),
   'artisan_id' => $jeton->getArtisanId()->getValue(),
   'code' => $jeton->getCode()->getValue(),
   'total_amount_centimes' => $jeton->getTotalAmount()->toCentimes(),
   'used_amount_centimes' => $jeton->getUsedAmount()->toCentimes(),
   'authorized_suppliers' => json_encode(array_map(fn($id) => $id->getValue(), $jeton->getAuthorizedSuppliers())),
   'status' => $jeton->getStatus()->getValue(),
   'created_at' => $jeton->getCreatedAt()->format('Y-m-d H:i:s'),
   'expires_at' => $jeton->getExpiresAt()->format('Y-m-d H:i:s'),
   'updated_at' => now()->format('Y-m-d H:i:s'),
  ];

  DB::table(self::TABLE)->upsert($data, ['id'], array_keys($data));
 }

 public function findById(JetonId $id): ?JetonMateriel
 {
  $row = DB::table(self::TABLE)
   ->where('id', $id->getValue())
   ->first();

  return $row ? $this->mapRowToJeton($row) : null;
 }

 public function findByCode(string $code): ?JetonMateriel
 {
  $row = DB::table(self::TABLE)
   ->where('code', $code)
   ->first();

  return $row ? $this->mapRowToJeton($row) : null;
 }

 public function findActiveByArtisan(UserId $artisanId): array
 {
  $rows = DB::table(self::TABLE)
   ->where('artisan_id', $artisanId->getValue())
   ->whereIn('status', [JetonStatus::ACTIVE, JetonStatus::PARTIALLY_USED])
   ->where('expires_at', '>', now())
   ->orderBy('created_at', 'desc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToJeton($row))->toArray();
 }

 public function findBySequestre(SequestreId $sequestreId): array
 {
  $rows = DB::table(self::TABLE)
   ->where('sequestre_id', $sequestreId->getValue())
   ->orderBy('created_at', 'desc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToJeton($row))->toArray();
 }

 public function findExpiredJetons(): array
 {
  $rows = DB::table(self::TABLE)
   ->where('expires_at', '<=', now())
   ->whereIn('status', [JetonStatus::ACTIVE, JetonStatus::PARTIALLY_USED])
   ->get();

  return $rows->map(fn($row) => $this->mapRowToJeton($row))->toArray();
 }

 public function findAuthorizedForSupplier(UserId $supplierId): array
 {
  $rows = DB::table(self::TABLE)
   ->whereIn('status', [JetonStatus::ACTIVE, JetonStatus::PARTIALLY_USED])
   ->where('expires_at', '>', now())
   ->where(function ($query) use ($supplierId) {
    $query->where('authorized_suppliers', '[]') // No restrictions
     ->orWhereJsonContains('authorized_suppliers', $supplierId->getValue());
   })
   ->orderBy('created_at', 'desc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToJeton($row))->toArray();
 }

 public function delete(JetonId $id): void
 {
  DB::table(self::TABLE)->where('id', $id->getValue())->delete();
 }

 private function mapRowToJeton($row): JetonMateriel
 {
  $authorizedSuppliers = json_decode($row->authorized_suppliers, true) ?? [];
  $supplierIds = array_map(fn($id) => UserId::fromString($id), $authorizedSuppliers);

  return new JetonMateriel(
   JetonId::fromString($row->id),
   SequestreId::fromString($row->sequestre_id),
   UserId::fromString($row->artisan_id),
   JetonCode::fromString($row->code),
   MoneyAmount::fromCentimes($row->total_amount_centimes),
   $supplierIds,
   MoneyAmount::fromCentimes($row->used_amount_centimes),
   JetonStatus::fromString($row->status),
   new DateTime($row->created_at),
   new DateTime($row->expires_at)
  );
 }
}
