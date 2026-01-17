<?php

namespace App\Domain\Marketplace\Models\Mission;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Exceptions\MaximumQuotesExceededException;
use App\Domain\Marketplace\Models\Devis\Devis;
use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Marketplace\Models\ValueObjects\MissionStatus;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use InvalidArgumentException;

/**
 * Aggregate root representing a mission (work request) created by a client
 * Manages quotes (devis) submitted by artisans
 */
final class Mission
{
    private MissionId $id;
    private UserId $clientId;
    private string $description;
    private TradeCategory $category;
    private GPS_Coordinates $location;
    private MoneyAmount $budgetMin;
    private MoneyAmount $budgetMax;
    private MissionStatus $status;
    /** @var Devis[] */
    private array $quotes;
    private DateTime $createdAt;

    private const MAX_QUOTES = 3;

    public function __construct(
        MissionId $id,
        UserId $clientId,
        string $description,
        TradeCategory $category,
        GPS_Coordinates $location,
        MoneyAmount $budgetMin,
        MoneyAmount $budgetMax,
        ?MissionStatus $status = null,
        array $quotes = [],
        ?DateTime $createdAt = null
    ) {
        $this->validateDescription($description);
        $this->validateBudgetRange($budgetMin, $budgetMax);

        $this->id = $id;
        $this->clientId = $clientId;
        $this->description = $description;
        $this->category = $category;
        $this->location = $location;
        $this->budgetMin = $budgetMin;
        $this->budgetMax = $budgetMax;
        $this->status = $status ?? MissionStatus::open();
        $this->quotes = $quotes;
        $this->createdAt = $createdAt ?? new DateTime();
    }

    /**
     * Create a new mission with generated ID
     */
    public static function create(
        UserId $clientId,
        string $description,
        TradeCategory $category,
        GPS_Coordinates $location,
        MoneyAmount $budgetMin,
        MoneyAmount $budgetMax
    ): self {
        return new self(
            MissionId::generate(),
            $clientId,
            $description,
            $category,
            $location,
            $budgetMin,
            $budgetMax
        );
    }

    /**
     * Add a quote to this mission
     *
     * @throws MaximumQuotesExceededException if mission already has 3 quotes
     * @throws InvalidArgumentException if mission cannot receive more quotes
     */
    public function addQuote(Devis $quote): void
    {
        if (!$this->canReceiveMoreQuotes()) {
            if (count($this->quotes) >= self::MAX_QUOTES) {
                throw new MaximumQuotesExceededException($this->id->getValue());
            }

            throw new InvalidArgumentException(
                "Mission with status {$this->status->getValue()} cannot receive quotes"
            );
        }

        // Verify the quote is for this mission
        if (!$quote->getMissionId()->equals($this->id)) {
            throw new InvalidArgumentException('Quote mission ID does not match this mission');
        }

        $this->quotes[] = $quote;

        // Update status to QUOTED if this is the first quote
        if ($this->status->isOpen() && count($this->quotes) === 1) {
            $this->status = MissionStatus::quoted();
        }
    }

    /**
     * Accept a specific quote and reject all others
     *
     * @throws InvalidArgumentException if quote not found or mission cannot accept quotes
     */
    public function acceptQuote(DevisId $quoteId): void
    {
        if (!$this->status->isOpen() && !$this->status->isQuoted()) {
            throw new InvalidArgumentException(
                "Cannot accept quote for mission with status {$this->status->getValue()}"
            );
        }

        $quoteFound = false;

        foreach ($this->quotes as $quote) {
            if ($quote->getId()->equals($quoteId)) {
                $quote->accept();
                $quoteFound = true;
            } else {
                // Reject all other pending quotes
                if ($quote->getStatus()->isPending()) {
                    $quote->reject();
                }
            }
        }

        if (!$quoteFound) {
            throw new InvalidArgumentException("Quote {$quoteId->getValue()} not found in this mission");
        }

        $this->status = MissionStatus::accepted();
    }

    /**
     * Cancel this mission
     *
     * @throws InvalidArgumentException if mission is already accepted
     */
    public function cancel(): void
    {
        if ($this->status->isAccepted()) {
            throw new InvalidArgumentException('Cannot cancel an accepted mission');
        }

        $this->status = MissionStatus::cancelled();
    }

    /**
     * Check if this mission can receive more quotes
     * Mission can receive quotes if:
     * - Status is OPEN or QUOTED
     * - Has less than 3 quotes
     */
    public function canReceiveMoreQuotes(): bool
    {
        return ($this->status->isOpen() || $this->status->isQuoted())
            && count($this->quotes) < self::MAX_QUOTES;
    }

    /**
     * Get the accepted quote if any
     */
    public function getAcceptedQuote(): ?Devis
    {
        foreach ($this->quotes as $quote) {
            if ($quote->getStatus()->isAccepted()) {
                return $quote;
            }
        }

        return null;
    }

    public function getId(): MissionId
    {
        return $this->id;
    }

    public function getClientId(): UserId
    {
        return $this->clientId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategory(): TradeCategory
    {
        return $this->category;
    }

    public function getLocation(): GPS_Coordinates
    {
        return $this->location;
    }

    public function getBudgetMin(): MoneyAmount
    {
        return $this->budgetMin;
    }

    public function getBudgetMax(): MoneyAmount
    {
        return $this->budgetMax;
    }

    public function getStatus(): MissionStatus
    {
        return $this->status;
    }

    /**
     * @return Devis[]
     */
    public function getQuotes(): array
    {
        return $this->quotes;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'client_id' => $this->clientId->getValue(),
            'description' => $this->description,
            'category' => $this->category->getValue(),
            'location' => $this->location->toArray(),
            'budget_min' => $this->budgetMin->toArray(),
            'budget_max' => $this->budgetMax->toArray(),
            'status' => $this->status->getValue(),
            'quotes_count' => count($this->quotes),
            'quotes' => array_map(fn(Devis $quote) => $quote->toArray(), $this->quotes),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    private function validateDescription(string $description): void
    {
        if (empty(trim($description))) {
            throw new InvalidArgumentException('Mission description cannot be empty');
        }
    }

    private function validateBudgetRange(MoneyAmount $min, MoneyAmount $max): void
    {
        if ($min->isGreaterThan($max)) {
            throw new InvalidArgumentException('Budget minimum cannot be greater than maximum');
        }
    }
}
