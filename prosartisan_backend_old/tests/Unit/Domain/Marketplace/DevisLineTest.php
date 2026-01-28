<?php

namespace Tests\Unit\Domain\Marketplace;

use App\Domain\Marketplace\Models\Devis\DevisLine;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DevisLineTest extends TestCase
{
    public function test_creates_material_line(): void
    {
        $line = DevisLine::createMaterial(
            'Tuyaux PVC',
            10,
            MoneyAmount::fromFrancs(5000)
        );

        $this->assertEquals('Tuyaux PVC', $line->getDescription());
        $this->assertEquals(10, $line->getQuantity());
        $this->assertTrue($line->isMaterial());
        $this->assertFalse($line->isLabor());
    }

    public function test_creates_labor_line(): void
    {
        $line = DevisLine::createLabor(
            'Installation',
            1,
            MoneyAmount::fromFrancs(30000)
        );

        $this->assertEquals('Installation', $line->getDescription());
        $this->assertEquals(1, $line->getQuantity());
        $this->assertTrue($line->isLabor());
        $this->assertFalse($line->isMaterial());
    }

    public function test_calculates_total(): void
    {
        $line = DevisLine::createMaterial(
            'Tuyaux PVC',
            10,
            MoneyAmount::fromFrancs(5000)
        );

        $total = $line->getTotal();

        $this->assertEquals(5000000, $total->toCentimes()); // 10 * 5000 = 50000 francs
    }

    public function test_rejects_empty_description(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Devis line description cannot be empty');

        DevisLine::createMaterial(
            '',
            10,
            MoneyAmount::fromFrancs(5000)
        );
    }

    public function test_rejects_zero_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Devis line quantity must be positive');

        DevisLine::createMaterial(
            'Tuyaux PVC',
            0,
            MoneyAmount::fromFrancs(5000)
        );
    }

    public function test_rejects_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Devis line quantity must be positive');

        DevisLine::createMaterial(
            'Tuyaux PVC',
            -5,
            MoneyAmount::fromFrancs(5000)
        );
    }

    public function test_converts_to_array(): void
    {
        $line = DevisLine::createMaterial(
            'Tuyaux PVC',
            10,
            MoneyAmount::fromFrancs(5000)
        );

        $array = $line->toArray();

        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('quantity', $array);
        $this->assertArrayHasKey('unit_price', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertEquals('Tuyaux PVC', $array['description']);
        $this->assertEquals(10, $array['quantity']);
        $this->assertEquals('MATERIAL', $array['type']);
    }
}
