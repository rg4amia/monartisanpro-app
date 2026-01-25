<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\Devis\Devis;
use App\Domain\Marketplace\Models\Devis\DevisLine;
use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\DevisLineType;
use App\Domain\Marketplace\Models\ValueObjects\DevisStatus;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Marketplace\Repositories\DevisRepository;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL implementation of DevisRepository
 */
class PostgresDevisRepository implements DevisRepository
{
    /**
     * {@inheritDoc}
     */
    public function save(Devis $devis): void
    {
        DB::transaction(function () use ($devis) {
            // Save devis
            $devisData = [
                'id' => $devis->getId()->getValue(),
                'mission_id' => $devis->getMissionId()->getValue(),
                'artisan_id' => $devis->getArtisanId()->getValue(),
                'total_amount_centimes' => $devis->getTotalAmount()->toCentimes(),
                'materials_amount_centimes' => $devis->getMaterialsAmount()->toCentimes(),
                'labor_amount_centimes' => $devis->getLaborAmount()->toCentimes(),
                'status' => $devis->getStatus()->getValue(),
                'expires_at' => $devis->getExpiresAt()?->format('Y-m-d H:i:s'),
                'created_at' => $devis->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];

            DB::table('devis')->updateOrInsert(
                ['id' => $devis->getId()->getValue()],
                $devisData
            );

            // Delete existing line items
            DB::table('devis_lines')->where('devis_id', $devis->getId()->getValue())->delete();

            // Save line items
            foreach ($devis->getLineItems() as $lineItem) {
                DB::table('devis_lines')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'devis_id' => $devis->getId()->getValue(),
                    'description' => $lineItem->getDescription(),
                    'quantity' => $lineItem->getQuantity(),
                    'unit_price_centimes' => $lineItem->getUnitPrice()->toCentimes(),
                    'line_type' => $lineItem->getType()->getValue(),
                    'created_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function findById(DevisId $id): ?Devis
    {
        $devisRow = DB::table('devis')
            ->where('id', $id->getValue())
            ->first();

        if (! $devisRow) {
            return null;
        }

        $lineRows = DB::table('devis_lines')
            ->where('devis_id', $id->getValue())
            ->orderBy('created_at')
            ->get();

        return $this->mapRowsToDevis($devisRow, $lineRows);
    }

    /**
     * {@inheritDoc}
     */
    public function findByMissionId(MissionId $missionId): array
    {
        $devisRows = DB::table('devis')
            ->where('mission_id', $missionId->getValue())
            ->orderBy('created_at')
            ->get();

        $result = [];
        foreach ($devisRows as $devisRow) {
            $lineRows = DB::table('devis_lines')
                ->where('devis_id', $devisRow->id)
                ->orderBy('created_at')
                ->get();

            $result[] = $this->mapRowsToDevis($devisRow, $lineRows);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function findByArtisanId(UserId $artisanId): array
    {
        $devisRows = DB::table('devis')
            ->where('artisan_id', $artisanId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [];
        foreach ($devisRows as $devisRow) {
            $lineRows = DB::table('devis_lines')
                ->where('devis_id', $devisRow->id)
                ->orderBy('created_at')
                ->get();

            $result[] = $this->mapRowsToDevis($devisRow, $lineRows);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function findExpiredPendingDevis(): array
    {
        $devisRows = DB::table('devis')
            ->where('status', DevisStatus::PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $result = [];
        foreach ($devisRows as $devisRow) {
            $lineRows = DB::table('devis_lines')
                ->where('devis_id', $devisRow->id)
                ->orderBy('created_at')
                ->get();

            $result[] = $this->mapRowsToDevis($devisRow, $lineRows);
        }

        return $result;
    }

    /**
     * Map database rows to Devis domain object
     */
    private function mapRowsToDevis($devisRow, $lineRows): Devis
    {
        $lineItems = [];
        foreach ($lineRows as $lineRow) {
            $lineItems[] = new DevisLine(
                $lineRow->description,
                $lineRow->quantity,
                MoneyAmount::fromCentimes($lineRow->unit_price_centimes),
                DevisLineType::fromString($lineRow->line_type)
            );
        }

        return new Devis(
            DevisId::fromString($devisRow->id),
            MissionId::fromString($devisRow->mission_id),
            UserId::fromString($devisRow->artisan_id),
            $lineItems,
            $devisRow->expires_at ? new DateTime($devisRow->expires_at) : null,
            DevisStatus::fromString($devisRow->status),
            new DateTime($devisRow->created_at)
        );
    }
}
