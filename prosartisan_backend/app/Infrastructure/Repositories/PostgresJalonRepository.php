<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Worksite\Models\Jalon\Jalon;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use App\Domain\Worksite\Models\ValueObjects\JalonId;
use App\Domain\Worksite\Models\ValueObjects\JalonStatus;
use App\Domain\Worksite\Models\ValueObjects\ProofOfDelivery;
use App\Domain\Worksite\Repositories\JalonRepository;
use DateTime;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL implementation of JalonRepository
 */
final class PostgresJalonRepository implements JalonRepository
{
 private const TABLE = 'jalons';

 public function save(Jalon $jalon): void
 {
  $proof = $jalon->getProof();

  $data = [
   'id' => $jalon->getId()->getValue(),
   'chantier_id' => $jalon->getChantierId()->getValue(),
   'description' => $jalon->getDescription(),
   'labor_amount_centimes' => $jalon->getLaborAmount()->toCentimes(),
   'sequence_number' => $jalon->getSequenceNumber(),
   'status' => $jalon->getStatus()->getValue(),
   'proof_photo_url' => $proof?->getPhotoUrl(),
   'proof_latitude' => $proof?->getLocation()->getLatitude(),
   'proof_longitude' => $proof?->getLocation()->getLongitude(),
   'proof_accuracy' => $proof?->getLocation()->getAccuracy(),
   'proof_captured_at' => $proof?->getCapturedAt()->format('Y-m-d H:i:s'),
   'proof_exif_data' => $proof ? json_encode($proof->getExifData()) : null,
   'submitted_at' => $jalon->getSubmittedAt()?->format('Y-m-d H:i:s'),
   'validated_at' => $jalon->getValidatedAt()?->format('Y-m-d H:i:s'),
   'auto_validation_deadline' => $jalon->getAutoValidationDeadline()?->format('Y-m-d H:i:s'),
   'contest_reason' => $jalon->getContestReason(),
   'updated_at' => now()->format('Y-m-d H:i:s'),
  ];

  // Check if jalon exists
  $exists = DB::table(self::TABLE)->where('id', $jalon->getId()->getValue())->exists();

  if (!$exists) {
   $data['created_at'] = $jalon->getCreatedAt()->format('Y-m-d H:i:s');
  }

  DB::table(self::TABLE)->upsert($data, ['id'], array_keys($data));
 }

 public function findById(JalonId $id): ?Jalon
 {
  $row = DB::table(self::TABLE)
   ->where('id', $id->getValue())
   ->first();

  return $row ? $this->mapRowToJalon($row) : null;
 }

 public function findByChantierId(ChantierId $chantierId): array
 {
  $rows = DB::table(self::TABLE)
   ->where('chantier_id', $chantierId->getValue())
   ->orderBy('sequence_number', 'asc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToJalon($row))->toArray();
 }

 public function findPendingAutoValidations(): array
 {
  $rows = DB::table(self::TABLE)
   ->where('status', JalonStatus::SUBMITTED)
   ->whereNotNull('auto_validation_deadline')
   ->where('auto_validation_deadline', '<=', now())
   ->orderBy('auto_validation_deadline', 'asc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToJalon($row))->toArray();
 }

 public function findByStatus(string $status): array
 {
  $rows = DB::table(self::TABLE)
   ->where('status', $status)
   ->orderBy('created_at', 'desc')
   ->get();

  return $rows->map(fn($row) => $this->mapRowToJalon($row))->toArray();
 }

 private function mapRowToJalon($row): Jalon
 {
  // Reconstruct proof of delivery if exists
  $proof = null;
  if ($row->proof_photo_url && $row->proof_latitude && $row->proof_longitude) {
   $location = new GPS_Coordinates(
    (float) $row->proof_latitude,
    (float) $row->proof_longitude,
    (float) $row->proof_accuracy ?? 10.0
   );

   $exifData = $row->proof_exif_data ? json_decode($row->proof_exif_data, true) : [];

   $proof = new ProofOfDelivery(
    $row->proof_photo_url,
    $location,
    new DateTime($row->proof_captured_at),
    $exifData
   );
  }

  return new Jalon(
   JalonId::fromString($row->id),
   ChantierId::fromString($row->chantier_id),
   $row->description,
   MoneyAmount::fromCentimes($row->labor_amount_centimes),
   $row->sequence_number,
   JalonStatus::fromString($row->status),
   $proof,
   new DateTime($row->created_at),
   $row->submitted_at ? new DateTime($row->submitted_at) : null,
   $row->validated_at ? new DateTime($row->validated_at) : null,
   $row->auto_validation_deadline ? new DateTime($row->auto_validation_deadline) : null,
   $row->contest_reason
  );
 }
}
