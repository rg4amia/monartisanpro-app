<?php

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObjects\MoneyAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyAmountTest extends TestCase
{
    public function test_creates_money_amount_from_centimes(): void
    {
        $amount = MoneyAmount::fromCentimes(100000);

        $this->assertEquals(100000, $amount->toCentimes());
        $this->assertEquals(1000.0, $amount->toFloat());
    }

    public function test_creates_money_amount_from_francs(): void
    {
        $amount = MoneyAmount::fromFrancs(1000.50);

        $this->assertEquals(100050, $amount->toCentimes());
        $this->assertEquals(1000.50, $amount->toFloat());
    }

    public function test_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Money amount cannot be negative');

        MoneyAmount::fromCentimes(-100);
    }

    public function test_adds_two_amounts(): void
    {
        $amount1 = MoneyAmount::fromCentimes(100000);
        $amount2 = MoneyAmount::fromCentimes(50000);

        $result = $amount1->add($amount2);

        $this->assertEquals(150000, $result->toCentimes());
    }

    public function test_subtracts_two_amounts(): void
    {
        $amount1 = MoneyAmount::fromCentimes(100000);
        $amount2 = MoneyAmount::fromCentimes(30000);

        $result = $amount1->subtract($amount2);

        $this->assertEquals(70000, $result->toCentimes());
    }

    public function test_rejects_subtraction_to_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot subtract to negative amount');

        $amount1 = MoneyAmount::fromCentimes(50000);
        $amount2 = MoneyAmount::fromCentimes(100000);

        $amount1->subtract($amount2);
    }

    public function test_multiplies_amount(): void
    {
        $amount = MoneyAmount::fromCentimes(100000);

        $result = $amount->multiply(1.5);

        $this->assertEquals(150000, $result->toCentimes());
    }

    public function test_calculates_percentage(): void
    {
        $amount = MoneyAmount::fromCentimes(100000);

        $result = $amount->percentage(65);

        $this->assertEquals(65000, $result->toCentimes());
    }

    public function test_rejects_invalid_percentage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentage must be between 0 and 100');

        $amount = MoneyAmount::fromCentimes(100000);
        $amount->percentage(150);
    }

    public function test_compares_amounts(): void
    {
        $amount1 = MoneyAmount::fromCentimes(100000);
        $amount2 = MoneyAmount::fromCentimes(50000);
        $amount3 = MoneyAmount::fromCentimes(100000);

        $this->assertTrue($amount1->isGreaterThan($amount2));
        $this->assertTrue($amount2->isLessThan($amount1));
        $this->assertTrue($amount1->equals($amount3));
    }

    public function test_formats_amount_in_french_locale(): void
    {
        $amount = MoneyAmount::fromCentimes(100000000); // 1,000,000 francs

        $formatted = $amount->format();

        $this->assertEquals('1 000 000 FCFA', $formatted);
    }

    public function test_converts_to_array(): void
    {
        $amount = MoneyAmount::fromCentimes(100000);

        $array = $amount->toArray();

        $this->assertArrayHasKey('amount_centimes', $array);
        $this->assertArrayHasKey('amount_francs', $array);
        $this->assertArrayHasKey('currency', $array);
        $this->assertArrayHasKey('formatted', $array);
        $this->assertEquals(100000, $array['amount_centimes']);
        $this->assertEquals(1000.0, $array['amount_francs']);
        $this->assertEquals('XOF', $array['currency']);
    }
}
