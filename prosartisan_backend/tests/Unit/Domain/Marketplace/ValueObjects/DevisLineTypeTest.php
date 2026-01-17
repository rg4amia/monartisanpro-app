<?php

namespace Tests\Unit\Domain\Marketplace\ValueObjects;

use App\Domain\Marketplace\Models\ValueObjects\DevisLineType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DevisLineTypeTest extends TestCase
{
 public function test_creates_material_type(): void
 {
  $type = DevisLineType::material();

  $this->assertEquals('MATERIAL', $type->getValue());
  $this->assertTrue($type->isMaterial());
  $this->assertFalse($type->isLabor());
 }

 public function test_creates_labor_type(): void
 {
  $type = DevisLineType::labor();

  $this->assertEquals('LABOR', $type->getValue());
  $this->assertTrue($type->isLabor());
  $this->assertFalse($type->isMaterial());
 }

 public function test_creates_from_string(): void
 {
  $type = DevisLineType::fromString('MATERIAL');

  $this->assertTrue($type->isMaterial());
 }

 public function test_creates_from_lowercase_string(): void
 {
  $type = DevisLineType::fromString('material');

  $this->assertTrue($type->isMaterial());
 }

 public function test_rejects_invalid_type(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Invalid devis line type');

  DevisLineType::fromString('INVALID');
 }

 public function test_provides_french_label(): void
 {
  $this->assertEquals('Matériel', DevisLineType::material()->getLabel());
  $this->assertEquals('Main-d\'œuvre', DevisLineType::labor()->getLabel());
 }

 public function test_compares_types(): void
 {
  $type1 = DevisLineType::material();
  $type2 = DevisLineType::material();
  $type3 = DevisLineType::labor();

  $this->assertTrue($type1->equals($type2));
  $this->assertFalse($type1->equals($type3));
 }

 public function test_converts_to_string(): void
 {
  $type = DevisLineType::material();

  $this->assertEquals('MATERIAL', (string) $type);
 }
}
