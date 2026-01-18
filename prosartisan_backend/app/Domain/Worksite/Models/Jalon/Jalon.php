<?php

namespace App\Domain\Worksite\Models\Jalon;

use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use App\Domain\Worksite\Models\ValueObjects\JalonId;
use App\Domain\Worksite\Models\ValueObjects\JalonStatus;
use App\Domain\Worksite\Models\ValueObjects\ProofOfDelivery;
use App\Domain\Worksite\Events\MilestoneValidated;
use App\Domain\Shared\Services\DomainEventDispatcher;
use App\Domain\Identity\Models\ValueObjects\UserId;
use DateTime;
use InvalidArgumentException;

/**
 * Entity representing a project milestone (Jalon)
 *
 * Manages proof submission, validation, and auto-validation deadlines
 * Requirements: 6.2, 6.4, 6.5
 */
final class Jalon
{
    private JalonId $id;
    private ChantierId $chantierId;
    private string $description;
    private MoneyAmount $laborAmount;
    private int $sequenceNumber;
    private JalonStatus $status;
    private ?ProofOfDelivery $proof;
    private DateTime $createdAt;
    private ?DateTime $submittedAt;
    private ?DateTime $validatedAt;
    private ?DateTime $autoValidationDeadline;
    private ?string $contestReason;

    private const AUTO_VALIDATION_HOURS = 48;

    public function __construct(
        JalonId $id,
        ChantierId $chantierId,
        string $description,
        MoneyAmount $laborAmount,
        int $sequenceNumber,
        ?JalonStatus $status = null,
        ?ProofOfDelivery $proof = null,
        ?DateTime $createdAt = null,
        ?DateTime $submittedAt = null,
        ?DateTime $validatedAt = null,
        ?DateTime $autoValidationDeadline = null,
        ?string $contestReason = null
    ) {
        $this->validateDescription($description);
        $this->validateLaborAmount($laborAmount);
        $this->validateSequenceNumber($sequenceNumber);

        $this->id = $id;
        $this->chantierId = $chantierId;
        $this->description = $description;
        $this->laborAmount = $laborAmount;
        $this->sequenceNumber = $sequenceNumber;
        $this->status = $status ?? JalonStatus::pending();
        $this->proof = $proof;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->submittedAt = $submittedAt;
        $this->validatedAt = $validatedAt;
        $this->autoValidationDeadline = $autoValidationDeadline;
        $this->contestReason = $contestReason;
    }

    /**
     * Create a new jalon with generated ID
     *
     * Requirement 6.1: Create milestones based on accepted devis
     */
    public static function create(
        ChantierId $chantierId,
        string $description,
        MoneyAmount $laborAmount,
        int $sequenceNumber
    ): self {
        return new self(
            JalonId::generate(),
            $chantierId,
            $description,
            $laborAmount,
            $sequenceNumber
        );
    }

    /**
     * Submit proof of delivery for this milestone
     *
     * Requirement 6.2: Require GPS-tagged photo with timestamp
     */
    public function submitProof(ProofOfDelivery $proof): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidArgumentException(
                "Cannot submit proof for jalon in status: {$this->status->getValue()}"
            );
        }

        if (!$proof->verifyIntegrity()) {
            throw new InvalidArgumentException('Proof of delivery failed integrity verification');
        }

        $this->proof = $proof;
        $this->status = JalonStatus::submitted();
        $this->submittedAt = new DateTime();

        // Set auto-validation deadline (48 hours from submission)
        $this->autoValidationDeadline = (clone $this->submittedAt)
            ->modify('+' . self::AUTO_VALIDATION_HOURS . ' hours');
    }

    /**
     * Validate the milestone manually by client
     *
     * Requirement 6.3: Client validation of milestone
     */
    public function validate(UserId $clientId, UserId $artisanId): void
    {
        if (!$this->status->isSubmitted()) {
            throw new InvalidArgumentException(
                "Cannot validate jalon in status: {$this->status->getValue()}"
            );
        }

        $this->status = JalonStatus::validated();
        $this->validatedAt = new DateTime();
        $this->autoValidationDeadline = null; // Clear deadline since manually validated

        // Fire MilestoneValidated domain event
        DomainEventDispatcher::dispatch(new MilestoneValidated(
            $this->id,
            $this->chantierId,
            $clientId,
            $artisanId,
            $this->laborAmount,
            false, // Not auto-validated
            new DateTime()
        ));
    }

    /**
     * Contest the milestone with a reason
     *
     * Requirement 6.3: Client can contest milestone within 48 hours
     */
    public function contest(string $reason): void
    {
        if (!$this->status->isSubmitted()) {
            throw new InvalidArgumentException(
                "Cannot contest jalon in status: {$this->status->getValue()}"
            );
        }

        if (empty(trim($reason))) {
            throw new InvalidArgumentException('Contest reason cannot be empty');
        }

        $this->status = JalonStatus::contested();
        $this->contestReason = $reason;
        $this->autoValidationDeadline = null; // Clear deadline since contested
    }

    /**
     * Auto-validate the milestone after deadline expires
     *
     * Requirement 6.5: Auto-validate after 48 hours if no response
     */
    public function autoValidate(UserId $clientId, UserId $artisanId): void
    {
        if (!$this->status->isSubmitted()) {
            throw new InvalidArgumentException(
                "Cannot auto-validate jalon in status: {$this->status->getValue()}"
            );
        }

        if (!$this->isAutoValidationDue()) {
            throw new InvalidArgumentException(
                'Auto-validation deadline has not been reached'
            );
        }

        $this->status = JalonStatus::validated();
        $this->validatedAt = new DateTime();
        $this->autoValidationDeadline = null;

        // Fire MilestoneValidated domain event
        DomainEventDispatcher::dispatch(new MilestoneValidated(
            $this->id,
            $this->chantierId,
            $clientId,
            $artisanId,
            $this->laborAmount,
            true, // Auto-validated
            new DateTime()
        ));
    }

    /**
     * Check if auto-validation deadline has passed
     *
     * Requirement 6.5: Check if 48-hour deadline has expired
     */
    public function isAutoValidationDue(): bool
    {
        if ($this->autoValidationDeadline === null) {
            return false;
        }

        if (!$this->status->isSubmitted()) {
            return false;
        }

        return new DateTime() >= $this->autoValidationDeadline;
    }

    /**
     * Check if milestone can be validated (submitted and within deadline or past deadline)
     */
    public function canBeValidated(): bool
    {
        return $this->status->isSubmitted();
    }

    /**
     * Check if milestone is completed (validated)
     */
    public function isCompleted(): bool
    {
        return $this->status->isValidated();
    }

    /**
     * Get hours remaining until auto-validation
     */
    public function getHoursUntilAutoValidation(): ?float
    {
        if ($this->autoValidationDeadline === null) {
            return null;
        }

        $now = new DateTime();
        if ($now >= $this->autoValidationDeadline) {
            return 0.0;
        }

        $diff = $this->autoValidationDeadline->getTimestamp() - $now->getTimestamp();
        return $diff / 3600; // Convert seconds to hours
    }

    // Getters
    public function getId(): JalonId
    {
        return $this->id;
    }

    public function getChantierId(): ChantierId
    {
        return $this->chantierId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLaborAmount(): MoneyAmount
    {
        return $this->laborAmount;
    }

    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }

    public function getStatus(): JalonStatus
    {
        return $this->status;
    }

    public function getProof(): ?ProofOfDelivery
    {
        return $this->proof;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getSubmittedAt(): ?DateTime
    {
        return $this->submittedAt;
    }

    public function getValidatedAt(): ?DateTime
    {
        return $this->validatedAt;
    }

    public function getAutoValidationDeadline(): ?DateTime
    {
        return $this->autoValidationDeadline;
    }

    public function getContestReason(): ?string
    {
        return $this->contestReason;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'chantier_id' => $this->chantierId->getValue(),
            'description' => $this->description,
            'labor_amount' => $this->laborAmount->toArray(),
            'sequence_number' => $this->sequenceNumber,
            'status' => $this->status->getValue(),
            'status_label' => $this->status->getFrenchLabel(),
            'proof' => $this->proof?->toArray(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'submitted_at' => $this->submittedAt?->format('Y-m-d H:i:s'),
            'validated_at' => $this->validatedAt?->format('Y-m-d H:i:s'),
            'auto_validation_deadline' => $this->autoValidationDeadline?->format('Y-m-d H:i:s'),
            'hours_until_auto_validation' => $this->getHoursUntilAutoValidation(),
            'contest_reason' => $this->contestReason,
            'is_completed' => $this->isCompleted(),
            'can_be_validated' => $this->canBeValidated(),
            'is_auto_validation_due' => $this->isAutoValidationDue(),
        ];
    }

    private function validateDescription(string $description): void
    {
        if (empty(trim($description))) {
            throw new InvalidArgumentException('Jalon description cannot be empty');
        }

        if (strlen($description) > 1000) {
            throw new InvalidArgumentException('Jalon description cannot exceed 1000 characters');
        }
    }

    private function validateLaborAmount(MoneyAmount $amount): void
    {
        if ($amount->toCentimes() <= 0) {
            throw new InvalidArgumentException('Jalon labor amount must be positive');
        }
    }

    private function validateSequenceNumber(int $sequenceNumber): void
    {
        if ($sequenceNumber < 1) {
            throw new InvalidArgumentException('Jalon sequence number must be positive');
        }
    }
}
