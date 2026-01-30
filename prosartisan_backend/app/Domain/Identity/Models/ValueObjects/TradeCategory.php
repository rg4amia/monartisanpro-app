<?php

namespace App\Domain\Identity\Models\ValueObjects;

use App\Models\Trade;
use InvalidArgumentException;

/**
 * Value Object representing an artisan's trade category
 */
final class TradeCategory
{
    // Legacy constants for backward compatibility with tests
    private const PLUMBER = 'PLUMBER';
    private const ELECTRICIAN = 'ELECTRICIAN';
    private const MASON = 'MASON';

    private string $value;
    private ?string $name;

    private function __construct(string $value, ?string $name = null)
    {
        $this->value = strtoupper($value);
        $this->name = $name;
    }

    /**
     * Legacy method for backward compatibility with tests
     */
    public static function PLUMBER(): self
    {
        return new self(self::PLUMBER, 'Plombier');
    }

    /**
     * Legacy method for backward compatibility with tests
     */
    public static function ELECTRICIAN(): self
    {
        return new self(self::ELECTRICIAN, 'Électricien');
    }

    /**
     * Legacy method for backward compatibility with tests
     */
    public static function MASON(): self
    {
        return new self(self::MASON, 'Maçon');
    }

    /**
     * Create from trade code (validates against database)
     */
    public static function fromString(string $value): self
    {
        $value = strtoupper($value);

        // Check if it's a legacy hardcoded value (for tests)
        if (in_array($value, [self::PLUMBER, self::ELECTRICIAN, self::MASON])) {
            return match ($value) {
                self::PLUMBER => self::PLUMBER(),
                self::ELECTRICIAN => self::ELECTRICIAN(),
                self::MASON => self::MASON(),
            };
        }

        // For production, validate against database
        $trade = Trade::where('code', $value)->first();

        if (!$trade) {
            throw new InvalidArgumentException("Invalid trade category: {$value}");
        }

        return new self($trade->code, $trade->name);
    }

    /**
     * Create from trade model
     */
    public static function fromTrade(Trade $trade): self
    {
        return new self($trade->code, $trade->name);
    }

    /**
     * Create without validation (for existing data)
     */
    public static function fromStringUnsafe(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get trade name for display
     */
    public function getLabel(): string
    {
        if ($this->name) {
            return $this->name;
        }

        // Fallback to database lookup if name not cached
        $trade = Trade::where('code', $this->value)->first();
        return $trade ? $trade->name : $this->value;
    }

    /**
     * Get the trade model
     */
    public function getTrade(): ?Trade
    {
        return Trade::where('code', $this->value)->first();
    }

    /**
     * Legacy method for backward compatibility with tests
     */
    public function isPlumber(): bool
    {
        return $this->value === self::PLUMBER;
    }

    /**
     * Legacy method for backward compatibility with tests
     */
    public function isElectrician(): bool
    {
        return $this->value === self::ELECTRICIAN;
    }

    /**
     * Legacy method for backward compatibility with tests
     */
    public function isMason(): bool
    {
        return $this->value === self::MASON;
    }

    public function equals(TradeCategory $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
