<?php

namespace App\Domain\Marketplace\Models\Devis;

use App\Domain\Marketplace\Models\ValueObjects\DevisLineType;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use InvalidArgumentException;

/**
 * Entity representing a line item in a devis (quote)
 * Each line represents either a material or labor cost
 */
final class DevisLine
{
    private string $description;

    private int $quantity;

    private MoneyAmount $unitPrice;

    private DevisLineType $type;

    public function __construct(
        string $description,
        int $quantity,
        MoneyAmount $unitPrice,
        DevisLineType $type
    ) {
        $this->validateDescription($description);
        $this->validateQuantity($quantity);

        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->type = $type;
    }

    public static function createMaterial(
        string $description,
        int $quantity,
        MoneyAmount $unitPrice
    ): self {
        return new self($description, $quantity, $unitPrice, DevisLineType::material());
    }

    public static function createLabor(
        string $description,
        int $quantity,
        MoneyAmount $unitPrice
    ): self {
        return new self($description, $quantity, $unitPrice, DevisLineType::labor());
    }

    /**
     * Calculate the total amount for this line item
     */
    public function getTotal(): MoneyAmount
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): MoneyAmount
    {
        return $this->unitPrice;
    }

    public function getType(): DevisLineType
    {
        return $this->type;
    }

    public function isMaterial(): bool
    {
        return $this->type->isMaterial();
    }

    public function isLabor(): bool
    {
        return $this->type->isLabor();
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->toArray(),
            'type' => $this->type->getValue(),
            'total' => $this->getTotal()->toArray(),
        ];
    }

    private function validateDescription(string $description): void
    {
        if (empty(trim($description))) {
            throw new InvalidArgumentException('Devis line description cannot be empty');
        }
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Devis line quantity must be positive');
        }
    }
}
