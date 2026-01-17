<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Financial\Models\JetonValidation\JetonValidation;
use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Financial\Repositories\JetonValidationRepository;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use Illuminate\Support\Facades\DB;
use DateTime;

/**
 * PostgreSQL implementation of JetonValidationRepository
 *
 * Note: JetonValidations are immutable audit records - only INSERT operations allowed
 */
final class PostgresJetonValidationRepository implements JetonValidationRepository
{
    private const TABLE = 'jeton_validations';

    public function save(JetonValidation $validation): void
    {
        $data = [
            'id' => $validation->getId(),
            'jeton_id' => $validation->getJetonId()->getValue(),
            'fournisseur_id' => $validation->getFournisseurId()->getValue(),
            'artisan_id' => $validation->getArtisanId()->getValue(),
            'amount_used_centimes' => $validation->getAmountUsed()->getAmountInCentimes(),
            'artisan_latitude' => $validation->getArtisanLocation()->getLatitude(),
            'artisan_longitude' => $validation->getArtisanLocation()->getLongitude(),
            'supplier_latitude' => $validation->getSupplierLocation()->getLatitude(),
            'supplier_longitude' => $validation->getSupplierLocation()->getLongitude(),
            'distance_meters' => $validation->getDistanceMeters(),
            'validation_status' => $validation->getValidationStatus(),
            'validation_notes' => $validation->getValidationNotes(),
            'validated_at' => $validation->getValidatedAt()->format('Y-m-d H:i:s'),
            'created_at' => $validation->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $validation->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        // Only INSERT - never UPDATE (immutable audit log)
        DB::table(self::TABLE)->insert($data);
    }

    public function findById(string $id): ?JetonValidation
    {
        $row = DB::table(self::TABLE)
            ->where('id', $id)
            ->first();

        return $row ? $this->mapRowToValidation($row) : null;
    }

    public function findByJetonId(JetonId $jetonId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('jeton_id', $jetonId->getValue())
            ->orderBy('validated_at', 'desc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToValidation($row))->toArray();
    }

    public function findByFournisseurId(UserId $fournisseurId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('fournisseur_id', $fournisseurId->getValue())
            ->orderBy('validated_at', 'desc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToValidation($row))->toArray();
    }

    public function findByArtisanId(UserId $artisanId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('artisan_id', $artisanId->getValue())
            ->orderBy('validated_at', 'desc')
            ->get();

        return $rows->map(fn($row) => $this->mapRowToValidation($row))->toArray();
    }

    private function mapRowToValidation($row): JetonValidation
    {
        return new JetonValidation(
            $row->id,
            JetonId::fromString($row->jeton_id),
            UserId::fromString($row->fournisseur_id),
            UserId::fromString($row->artisan_id),
            MoneyAmount::fromCentimes($row->amount_used_centimes),
            GPS_Coordinates::fromLatLng($row->artisan_latitude, $row->artisan_longitude),
            GPS_Coordinates::fromLatLng($row->supplier_latitude, $row->supplier_longitude),
            $row->distance_meters,
            $row->validation_status,
            $row->validation_notes,
            new DateTime($row->validated_at),
            new DateTime($row->created_at)
        );
    }
}
