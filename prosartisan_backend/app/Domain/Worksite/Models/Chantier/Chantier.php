<?php

namespace App\Domain\Worksite\Models\Chantier;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\Services\DomainEventDispatcher;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Worksite\Events\ChantierCompleted;
use App\Domain\Worksite\Models\Jalon\Jalon;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use App\Domain\Worksite\Models\ValueObjects\ChantierStatus;
use DateTime;
use InvalidArgumentException;

/**
 * Aggregate root representing a worksite (Chantier)
 *
 * Manages project milestones and tracks overall progress
 * Requirements: 6.1, 6.7
 */
final class Chantier
{
    private ChantierId $id;

    private MissionId $missionId;

    private UserId $clientId;

    private UserId $artisanId;

    private array $milestones; // Collection of Jalon

    private ChantierStatus $status;

    private DateTime $startedAt;

    private ?DateTime $completedAt;

    public function __construct(
        ChantierId $id,
        MissionId $missionId,
        UserId $clientId,
        UserId $artisanId,
        array $milestones = [],
        ?ChantierStatus $status = null,
        ?DateTime $startedAt = null,
        ?DateTime $completedAt = null
    ) {
        $this->validateMilestones($milestones);

        $this->id = $id;
        $this->missionId = $missionId;
        $this->clientId = $clientId;
        $this->artisanId = $artisanId;
        $this->milestones = $milestones;
        $this->status = $status ?? ChantierStatus::inProgress();
        $this->startedAt = $startedAt ?? new DateTime;
        $this->completedAt = $completedAt;
    }

    /**
     * Create a new chantier with generated ID
     *
     * Requirement 6.1: Start chantier after escrow is established
     */
    public static function create(
        MissionId $missionId,
        UserId $clientId,
        UserId $artisanId
    ): self {
        return new self(
            ChantierId::generate(),
            $missionId,
            $clientId,
            $artisanId
        );
    }

    /**
     * Add a milestone to the chantier
     *
     * Requirement 6.1: Create milestones based on accepted devis
     */
    public function addMilestone(Jalon $milestone): void
    {
        if ($this->status->isCompleted()) {
            throw new InvalidArgumentException('Cannot add milestone to completed chantier');
        }

        // Ensure milestone belongs to this chantier
        if (! $milestone->getChantierId()->equals($this->id)) {
            throw new InvalidArgumentException('Milestone does not belong to this chantier');
        }

        // Check for duplicate sequence numbers
        $sequenceNumber = $milestone->getSequenceNumber();
        foreach ($this->milestones as $existingMilestone) {
            if ($existingMilestone->getSequenceNumber() === $sequenceNumber) {
                throw new InvalidArgumentException(
                    "Milestone with sequence number {$sequenceNumber} already exists"
                );
            }
        }

        $this->milestones[] = $milestone;
        $this->sortMilestonesBySequence();
    }

    /**
     * Start the chantier
     *
     * Requirement 6.1: Mark chantier as started
     */
    public function start(): void
    {
        if (! $this->status->isInProgress()) {
            throw new InvalidArgumentException(
                "Cannot start chantier in status: {$this->status->getValue()}"
            );
        }

        // Chantier is already started by default, but this method can be used
        // for explicit starting logic if needed
        $this->startedAt = new DateTime;
    }

    /**
     * Complete the chantier
     *
     * Requirement 6.7: Mark chantier as completed when all milestones are validated
     */
    public function complete(): void
    {
        if ($this->status->isCompleted()) {
            throw new InvalidArgumentException('Chantier is already completed');
        }

        if (! $this->areAllMilestonesCompleted()) {
            throw new InvalidArgumentException(
                'Cannot complete chantier: not all milestones are validated'
            );
        }

        $this->status = ChantierStatus::completed();
        $this->completedAt = new DateTime;

        // Fire ChantierCompleted domain event
        DomainEventDispatcher::dispatch(new ChantierCompleted(
            $this->id,
            $this->missionId,
            $this->artisanId,
            $this->clientId,
            new DateTime
        ));
    }

    /**
     * Mark chantier as disputed
     */
    public function markAsDisputed(): void
    {
        if ($this->status->isCompleted()) {
            throw new InvalidArgumentException('Cannot dispute completed chantier');
        }

        $this->status = ChantierStatus::disputed();
    }

    /**
     * Resume chantier from disputed status
     */
    public function resumeFromDispute(): void
    {
        if (! $this->status->isDisputed()) {
            throw new InvalidArgumentException('Chantier is not in disputed status');
        }

        $this->status = ChantierStatus::inProgress();
    }

    /**
     * Get all milestones
     */
    public function getAllMilestones(): array
    {
        return $this->milestones;
    }

    /**
     * Get pending milestones (not yet validated)
     */
    public function getPendingMilestones(): array
    {
        return array_filter($this->milestones, function (Jalon $milestone) {
            return ! $milestone->isCompleted();
        });
    }

    /**
     * Get completed milestones (validated)
     */
    public function getCompletedMilestones(): array
    {
        return array_filter($this->milestones, function (Jalon $milestone) {
            return $milestone->isCompleted();
        });
    }

    /**
     * Get milestones that need auto-validation
     */
    public function getMilestonesNeedingAutoValidation(): array
    {
        return array_filter($this->milestones, function (Jalon $milestone) {
            return $milestone->isAutoValidationDue();
        });
    }

    /**
     * Get next milestone in sequence
     */
    public function getNextMilestone(): ?Jalon
    {
        $pendingMilestones = $this->getPendingMilestones();

        if (empty($pendingMilestones)) {
            return null;
        }

        // Return the milestone with the lowest sequence number
        usort($pendingMilestones, function (Jalon $a, Jalon $b) {
            return $a->getSequenceNumber() <=> $b->getSequenceNumber();
        });

        return $pendingMilestones[0];
    }

    /**
     * Get total labor amount for all milestones
     */
    public function getTotalLaborAmount(): MoneyAmount
    {
        $total = MoneyAmount::fromCentimes(0);

        foreach ($this->milestones as $milestone) {
            $total = $total->add($milestone->getLaborAmount());
        }

        return $total;
    }

    /**
     * Get completed labor amount (from validated milestones)
     */
    public function getCompletedLaborAmount(): MoneyAmount
    {
        $total = MoneyAmount::fromCentimes(0);

        foreach ($this->getCompletedMilestones() as $milestone) {
            $total = $total->add($milestone->getLaborAmount());
        }

        return $total;
    }

    /**
     * Get progress percentage (0-100)
     */
    public function getProgressPercentage(): float
    {
        if (empty($this->milestones)) {
            return 0.0;
        }

        $completedCount = count($this->getCompletedMilestones());
        $totalCount = count($this->milestones);

        return ($completedCount / $totalCount) * 100;
    }

    /**
     * Check if all milestones are completed
     *
     * Requirement 6.7: Determine when chantier can be completed
     */
    public function areAllMilestonesCompleted(): bool
    {
        if (empty($this->milestones)) {
            return false;
        }

        foreach ($this->milestones as $milestone) {
            if (! $milestone->isCompleted()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if chantier can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->status->isInProgress() && $this->areAllMilestonesCompleted();
    }

    /**
     * Get milestone by sequence number
     */
    public function getMilestoneBySequence(int $sequenceNumber): ?Jalon
    {
        foreach ($this->milestones as $milestone) {
            if ($milestone->getSequenceNumber() === $sequenceNumber) {
                return $milestone;
            }
        }

        return null;
    }

    // Getters
    public function getId(): ChantierId
    {
        return $this->id;
    }

    public function getMissionId(): MissionId
    {
        return $this->missionId;
    }

    public function getClientId(): UserId
    {
        return $this->clientId;
    }

    public function getArtisanId(): UserId
    {
        return $this->artisanId;
    }

    public function getStatus(): ChantierStatus
    {
        return $this->status;
    }

    public function getStartedAt(): DateTime
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTime
    {
        return $this->completedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'mission_id' => $this->missionId->getValue(),
            'client_id' => $this->clientId->getValue(),
            'artisan_id' => $this->artisanId->getValue(),
            'status' => $this->status->getValue(),
            'status_label' => $this->status->getFrenchLabel(),
            'started_at' => $this->startedAt->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
            'milestones_count' => count($this->milestones),
            'completed_milestones_count' => count($this->getCompletedMilestones()),
            'pending_milestones_count' => count($this->getPendingMilestones()),
            'progress_percentage' => $this->getProgressPercentage(),
            'total_labor_amount' => $this->getTotalLaborAmount()->toArray(),
            'completed_labor_amount' => $this->getCompletedLaborAmount()->toArray(),
            'can_be_completed' => $this->canBeCompleted(),
            'milestones' => array_map(fn (Jalon $milestone) => $milestone->toArray(), $this->milestones),
        ];
    }

    private function validateMilestones(array $milestones): void
    {
        foreach ($milestones as $milestone) {
            if (! $milestone instanceof Jalon) {
                throw new InvalidArgumentException('All milestones must be Jalon instances');
            }
        }
    }

    private function sortMilestonesBySequence(): void
    {
        usort($this->milestones, function (Jalon $a, Jalon $b) {
            return $a->getSequenceNumber() <=> $b->getSequenceNumber();
        });
    }
}
