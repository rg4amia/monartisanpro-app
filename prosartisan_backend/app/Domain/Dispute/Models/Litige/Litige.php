<?php

namespace App\Domain\Dispute\Models\Litige;

use App\Domain\Dispute\Models\Arbitrage\Arbitration;
use App\Domain\Dispute\Models\Mediation\Mediation;
use App\Domain\Dispute\Models\ValueObjects\DisputeStatus;
use App\Domain\Dispute\Models\ValueObjects\DisputeType;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Dispute\Models\ValueObjects\Resolution;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
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
        $this->createdAt = $createdAt ?? new DateTime();
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
        return new self(
            LitigeId::generate(),
            $missionId,
            $reporterId,
            $defendantId,
            $type,
            $description,
            $evidence
        );
    }

    /**
     * Start mediation process
     *
     * Requirement 9.3: Assign mediator based on chantier value
     */
    public function startMediation(UserId $mediatorId): void
    {
        if (!$this->status->isOpen()) {
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
        if (!$this->status->isInMediation()) {
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
        if (!$this->status->isInArbitration()) {
            throw new InvalidArgumentException('Cannot render decision: dispute is not in arbitration');
        }

        $this->arbitration = $arbitration;
        $this->resolution = Resolution::fromArbitration(
            $arbitration->getDecision(),
            $arbitration->getJustification()
        );
        $this->status = DisputeStatus::resolved();
        $this->resolvedAt = new DateTime();
    }

    /**
     * Resolve dispute through mediation
     */
    public function resolveFromMediation(Resolution $resolution): void
    {
        if (!$this->status->isInMediation()) {
            throw new InvalidArgumentException('Cannot resolve: dispute is not in mediation');
        }

        $this->resolution = $resolution;
        $this->status = DisputeStatus::resolved();
        $this->resolvedAt = new DateTime();

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
        if (!$this->status->isResolved()) {
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

        if (!filter_var($evidenceUrl, FILTER_VALIDATE_URL)) {
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
     * Get the other part
