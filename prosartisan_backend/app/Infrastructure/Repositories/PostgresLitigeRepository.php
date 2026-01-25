<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Dispute\Models\Arbitrage\Arbitration;
use App\Domain\Dispute\Models\Litige\Litige;
use App\Domain\Dispute\Models\Mediation\Mediation;
use App\Domain\Dispute\Models\ValueObjects\ArbitrationDecision;
use App\Domain\Dispute\Models\ValueObjects\DecisionType;
use App\Domain\Dispute\Models\ValueObjects\DisputeStatus;
use App\Domain\Dispute\Models\ValueObjects\DisputeType;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Dispute\Models\ValueObjects\Resolution;
use App\Domain\Dispute\Repositories\LitigeRepository;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use Illuminate\Database\ConnectionInterface;

/**
 * PostgreSQL implementation of LitigeRepository
 *
 * Handles persistence of Litige aggregates in PostgreSQL database
 *
 * Requirements: 9.1
 */
final class PostgresLitigeRepository implements LitigeRepository
{
    public function __construct(
        private ConnectionInterface $connection
    ) {}

    public function save(Litige $litige): void
    {
        $this->connection->transaction(function () use ($litige) {
            $this->saveLitige($litige);
            $this->saveMediation($litige);
            $this->saveArbitration($litige);
        });
    }

    public function findById(LitigeId $id): ?Litige
    {
        $row = $this->connection->table('litiges')
            ->where('id', $id->getValue())
            ->first();

        if (! $row) {
            return null;
        }

        return $this->mapRowToLitige($row);
    }

    public function findByMissionId(MissionId $missionId): array
    {
        $rows = $this->connection->table('litiges')
            ->where('mission_id', $missionId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRowToLitige($row))->toArray();
    }

    public function findOpenDisputes(): array
    {
        return $this->findByStatus(DisputeStatus::OPEN);
    }

    public function findByStatus(string $status): array
    {
        $rows = $this->connection->table('litiges')
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRowToLitige($row))->toArray();
    }

    public function findByUser(string $userId): array
    {
        $rows = $this->connection->table('litiges')
            ->where(function ($query) use ($userId) {
                $query->where('reporter_id', $userId)
                    ->orWhere('defendant_id', $userId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->mapRowToLitige($row))->toArray();
    }

    public function delete(LitigeId $id): void
    {
        $this->connection->transaction(function () use ($id) {
            // Delete mediation communications first
            $this->connection->table('mediation_communications')
                ->where('litige_id', $id->getValue())
                ->delete();

            // Delete the litige
            $this->connection->table('litiges')
                ->where('id', $id->getValue())
                ->delete();
        });
    }

    private function saveLitige(Litige $litige): void
    {
        $data = [
            'id' => $litige->getId()->getValue(),
            'mission_id' => $litige->getMissionId()->getValue(),
            'reporter_id' => $litige->getReporterId()->getValue(),
            'defendant_id' => $litige->getDefendantId()->getValue(),
            'type' => $litige->getType()->getValue(),
            'description' => $litige->getDescription(),
            'evidence' => json_encode($litige->getEvidence()),
            'status' => $litige->getStatus()->getValue(),
            'resolution_outcome' => $litige->getResolution()?->getOutcome(),
            'resolution_amount_centimes' => $litige->getResolution()?->getAmount()?->toCentimes(),
            'resolution_notes' => $litige->getResolution()?->getNotes(),
            'created_at' => $litige->getCreatedAt()->format('Y-m-d H:i:s'),
            'resolved_at' => $litige->getResolvedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];

        $this->connection->table('litiges')->updateOrInsert(
            ['id' => $litige->getId()->getValue()],
            $data
        );
    }

    private function saveMediation(Litige $litige): void
    {
        $mediation = $litige->getMediation();
        if (! $mediation) {
            return;
        }

        // Update litige with mediation info
        $this->connection->table('litiges')
            ->where('id', $litige->getId()->getValue())
            ->update([
                'mediator_id' => $mediation->getMediatorId()->getValue(),
                'mediation_started_at' => $mediation->getStartedAt()->format('Y-m-d H:i:s'),
                'mediation_ended_at' => $mediation->getEndedAt()?->format('Y-m-d H:i:s'),
            ]);

        // Save communications
        foreach ($mediation->getCommunications() as $communication) {
            $this->connection->table('mediation_communications')->updateOrInsert(
                [
                    'litige_id' => $litige->getId()->getValue(),
                    'sender_id' => $communication->getSenderId()->getValue(),
                    'sent_at' => $communication->getSentAt()->format('Y-m-d H:i:s'),
                ],
                [
                    'message' => $communication->getMessage(),
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]
            );
        }
    }

    private function saveArbitration(Litige $litige): void
    {
        $arbitration = $litige->getArbitration();
        if (! $arbitration) {
            return;
        }

        $this->connection->table('litiges')
            ->where('id', $litige->getId()->getValue())
            ->update([
                'arbitrator_id' => $arbitration->getArbitratorId()->getValue(),
                'arbitration_decision_type' => $arbitration->getDecision()->getType()->getValue(),
                'arbitration_decision_amount_centimes' => $arbitration->getDecision()->getAmount()?->toCentimes(),
                'arbitration_justification' => $arbitration->getJustification(),
                'arbitration_rendered_at' => $arbitration->getRenderedAt()->format('Y-m-d H:i:s'),
            ]);
    }

    private function mapRowToLitige($row): Litige
    {
        $mediation = null;
        if ($row->mediator_id) {
            $mediation = new Mediation(
                UserId::fromString($row->mediator_id),
                $this->loadMediationCommunications($row->id),
                new DateTime($row->mediation_started_at),
                $row->mediation_ended_at ? new DateTime($row->mediation_ended_at) : null
            );
        }

        $arbitration = null;
        if ($row->arbitrator_id) {
            $decision = new ArbitrationDecision(
                DecisionType::fromString($row->arbitration_decision_type),
                $row->arbitration_decision_amount_centimes
                 ? MoneyAmount::fromCentimes($row->arbitration_decision_amount_centimes)
                 : null
            );

            $arbitration = new Arbitration(
                UserId::fromString($row->arbitrator_id),
                $decision,
                $row->arbitration_justification,
                new DateTime($row->arbitration_rendered_at)
            );
        }

        $resolution = null;
        if ($row->resolution_outcome) {
            $resolution = new Resolution(
                $row->resolution_outcome,
                $row->resolution_notes ?? '',
                $row->resolution_amount_centimes
                 ? MoneyAmount::fromCentimes($row->resolution_amount_centimes)
                 : null,
                $row->resolved_at ? new DateTime($row->resolved_at) : null
            );
        }

        return new Litige(
            LitigeId::fromString($row->id),
            MissionId::fromString($row->mission_id),
            UserId::fromString($row->reporter_id),
            UserId::fromString($row->defendant_id),
            DisputeType::fromString($row->type),
            $row->description,
            json_decode($row->evidence, true) ?? [],
            DisputeStatus::fromString($row->status),
            $mediation,
            $arbitration,
            $resolution,
            new DateTime($row->created_at),
            $row->resolved_at ? new DateTime($row->resolved_at) : null
        );
    }

    private function loadMediationCommunications(string $litigeId): array
    {
        $communications = $this->connection->table('mediation_communications')
            ->where('litige_id', $litigeId)
            ->orderBy('sent_at')
            ->get();

        return $communications->map(function ($comm) {
            return new \App\Domain\Dispute\Models\Mediation\MediationCommunication(
                $comm->message,
                UserId::fromString($comm->sender_id),
                new DateTime($comm->sent_at)
            );
        })->toArray();
    }
}
