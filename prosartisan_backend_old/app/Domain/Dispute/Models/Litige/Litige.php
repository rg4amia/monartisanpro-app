<?php

namespace App\Domain\Dispute\Models\Litige;

use App\Domain\Dispute\Events\DisputeReported;
use App\Domain\Dispute\Models\Arbitrage\Arbitration;
use App\Domain\Dispute\Models\Mediation\Mediation;
use App\Domain\Dispute\Models\ValueObjects\DisputeStatus;
use App\Domain\Dispute\Models\ValueObjects\DisputeType;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Dispute\Models\ValueObjects\Resolution;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\Services\DomainEventDispatcher;
use DateTime;
use InvalidArgumentException;

/**
 * Aggregate root representing a dispute (Litige)
 *
 * Manages dispute lifecycle from reporting through mediation/arbitration to resolution
 *
 * Requirements: 9.1, 9.2, 9.7
 */
final class Litige
{
    private LitigeId $id;

    private MissionId $missionId;

    private UserId $reporterId;

    private UserId $defendantId;

    private DisputeType $type;

    private string $description;

    private array $evidence; // URLs to photos/documents

    private DisputeStatus $status;

    private ?Mediation $mediation;

    private ?Arbitration $arbitration;

    private ?Resolution $resolution;

    private DateTime $createdAt;

    private ?DateTime $resolvedAt;

    public function __construct(
        LitigeId $id,
        MissionId $missionId,
        UserId $reporterId,
        UserId $defendantId,
        DisputeType $type,
        string $description,
        array $evidence = [],
        ?DisputeStatus $status = null,
        ?Mediation $mediation = null,
        ?Arbitration $arbitration = null,
        ?Resolution $resolution = null,
        ?DateTime $createdAt = null,
        ?DateTime $resolvedAt = null
    ) {
        $this->validateDescription($description);
        $this->validateParties($reporterId, $defendantId);

        $this->id = $id;
        $this->missionId = $missionId;
        $this->reporterId = $reporterId;
        $this->defendantId = $defendantId;
        $this->type = $type;
        $this->description = $description;
        $this->evidence = $evidence;
        $this->status = $status ?? DisputeStatus::open();
        $this->mediation = $mediation;
        $this->arbitration = $arbitration;
        $this->resolution = $resolution;
        $this->createdAt = $createdAt ?? new DateTime;
        $this->resolvedAt = $resolvedAt;
    }

    /**
     * Create a new dispute
     *
     * Requirement 9.1: Create Litige record with description and evidence
     */
    public static function create(
        MissionId $missionId,
        UserId $reporterId,
        UserId $defendantId,
        DisputeType $type,
        string $description,
        array $evidence = []
    ): self {
        $litige = new self(
            LitigeId::generate(),
            $missionId,
            $reporterId,
            $defendantId,
            $type,
            $description,
            $evidence
        );

        // Fire DisputeReported domain event
        DomainEventDispatcher::dispatch(new DisputeReported(
            $litige->id,
            $missionId,
            $reporterId,
            $type,
            new DateTime
        ));

        return $litige;
    }

    /**
     * Start mediation process
     *
     * Requirement 9.3: Assign mediator based on chantier value
     */
    public function startMediation(UserId $mediatorId): void
    {
        if (! $this->status->isOpen()) {
            throw new InvalidArgumentException('Cannot start mediation: dispute is not open');
        }

        if ($this->mediation !== null) {
            throw new InvalidArgumentException('Mediation already started');
        }

        $this->mediation = new Mediation($mediatorId);
        $this->status = DisputeStatus::inMediation();
    }

    /**
     * Escalate to arbitration
     *
     * Requirement 9.4: Escalate to arbitration when mediation fails
     */
    public function escalateToArbitration(UserId $arbitratorId): void
    {
        if (! $this->status->isInMediation()) {
            throw new InvalidArgumentException('Cannot escalate to arbitration: dispute is not in mediation');
        }

        if ($this->arbitration !== null) {
            throw new InvalidArgumentException('Arbitration already started');
        }

        // End mediation if active
        if ($this->mediation && $this->mediation->isActive()) {
            $this->mediation->end();
        }

        $this->status = DisputeStatus::inArbitration();
    }

    /**
     * Render arbitration decision
     *
     * Requirement 9.6: Execute arbitration decision
     */
    public function renderArbitrationDecision(Arbitration $arbitration): void
    {
        if (! $this->status->isInArbitration()) {
            throw new InvalidArgumentException('Cannot render decision: dispute is not in arbitration');
        }

        $this->arbitration = $arbitration;
        $this->resolution = Resolution::fromArbitration(
            $arbitration->getDecision(),
            $arbitration->getJustification()
        );
        $this->status = DisputeStatus::resolved();
        $this->resolvedAt = new DateTime;
    }

    /**
     * Resolve dispute through mediation
     */
    public function resolveFromMediation(Resolution $resolution): void
    {
        if (! $this->status->isInMediation()) {
            throw new InvalidArgumentException('Cannot resolve: dispute is not in mediation');
        }

        $this->resolution = $resolution;
        $this->status = DisputeStatus::resolved();
        $this->resolvedAt = new DateTime;

        // End mediation
        if ($this->mediation && $this->mediation->isActive()) {
            $this->mediation->end();
        }
    }

    /**
     * Close the dispute
     */
    public function close(): void
    {
        if (! $this->status->isResolved()) {
            throw new InvalidArgumentException('Cannot close: dispute is not resolved');
        }

        $this->status = DisputeStatus::closed();
    }

    /**
     * Add evidence to the dispute
     */
    public function addEvidence(string $evidenceUrl): void
    {
        if ($this->status->isResolved() || $this->status->isClosed()) {
            throw new InvalidArgumentException('Cannot add evidence: dispute is resolved or closed');
        }

        if (! filter_var($evidenceUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid evidence URL');
        }

        $this->evidence[] = $evidenceUrl;
    }

    /**
     * Check if dispute can be reported (within 7 days of final jalon validation)
     *
     * Requirement 9.7: Allow dispute reporting within 7 days
     */
    public function isReportingPeriodValid(DateTime $finalJalonValidatedAt): bool
    {
        $reportingDeadline = clone $finalJalonValidatedAt;
        $reportingDeadline->modify('+7 days');

        return $this->createdAt <= $reportingDeadline;
    }

    /**
     * Check if dispute involves a specific user
     */
    public function involvesUser(UserId $userId): bool
    {
        return $this->reporterId->equals($userId) || $this->defendantId->equals($userId);
    }

    /**
     * Get the other party in the dispute
     */
    public function getOtherParty(UserId $userId): UserId
    {
        if ($this->reporterId->equals($userId)) {
            return $this->defendantId;
        }

        if ($this->defendantId->equals($userId)) {
            return $this->reporterId;
        }

        throw new InvalidArgumentException('User is not involved in this dispute');
    }

    // Getters
    public function getId(): LitigeId
    {
        return $this->id;
    }

    public function getMissionId(): MissionId
    {
        return $this->missionId;
    }

    public function getReporterId(): UserId
    {
        return $this->reporterId;
    }

    public function getDefendantId(): UserId
    {
        return $this->defendantId;
    }

    public function getType(): DisputeType
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getEvidence(): array
    {
        return $this->evidence;
    }

    public function getStatus(): DisputeStatus
    {
        return $this->status;
    }

    public function getMediation(): ?Mediation
    {
        return $this->mediation;
    }

    public function getArbitration(): ?Arbitration
    {
        return $this->arbitration;
    }

    public function getResolution(): ?Resolution
    {
        return $this->resolution;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?DateTime
    {
        return $this->resolvedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'mission_id' => $this->missionId->getValue(),
            'reporter_id' => $this->reporterId->getValue(),
            'defendant_id' => $this->defendantId->getValue(),
            'type' => $this->type->getValue(),
            'type_label' => $this->type->getFrenchLabel(),
            'description' => $this->description,
            'evidence' => $this->evidence,
            'status' => $this->status->getValue(),
            'status_label' => $this->status->getFrenchLabel(),
            'mediation' => $this->mediation?->toArray(),
            'arbitration' => $this->arbitration?->toArray(),
            'resolution' => $this->resolution?->toArray(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'resolved_at' => $this->resolvedAt?->format('Y-m-d H:i:s'),
        ];
    }

    private function validateDescription(string $description): void
    {
        if (empty(trim($description))) {
            throw new InvalidArgumentException('Dispute description cannot be empty');
        }

        if (strlen($description) < 10) {
            throw new InvalidArgumentException('Dispute description must be at least 10 characters long');
        }
    }

    private function validateParties(UserId $reporterId, UserId $defendantId): void
    {
        if ($reporterId->equals($defendantId)) {
            throw new InvalidArgumentException('Reporter and defendant cannot be the same user');
        }
    }
}
