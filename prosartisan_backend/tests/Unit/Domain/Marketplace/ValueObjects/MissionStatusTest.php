<?php

namespace Tests\Unit\Domain\Marketplace\ValueObjects;

use App\Domain\Marketplace\Models\ValueObjects\MissionStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MissionStatusTest extends TestCase
{
 public function test_creates_open_status(): void
 {
  $status = MissionStatus::open();

  $this->assertEquals('OPEN', $status->getValue());
  $this->assertTrue($status->isOpen());
  $this->assertFalse($status->isQuoted());
  $this->assertFalse($status->isAccepted());
  $this->assertFalse($status->isCancelled());
 }

 public function test_creates_quoted_status(): void
 {
  $status = MissionStatus::quoted();

  $this->assertEquals('QUOTED', $status->getValue());
  $this->assertTrue($status->isQuoted());
 }

 public function test_creates_accepted_status(): void
 {
  $status = MissionStatus::accepted();

  $this->assertEquals('ACCEPTED', $status->getValue());
  $this->assertTrue($status->isAccepted());
 }

 public function test_creates_cancelled_status(): void
 {
  $status = MissionStatus::cancelled();

  $this->assertEquals('CANCELLED', $status->getValue());
  $this->assertTrue($status->isCancelled());
 }

 public function test_creates_from_string(): void
 {
  $status = MissionStatus::fromString('OPEN');

  $this->assertTrue($status->isOpen());
 }

 public function test_creates_from_lowercase_string(): void
 {
  $status = MissionStatus::fromString('open');

  $this->assertTrue($status->isOpen());
 }

 public function test_rejects_invalid_status(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Invalid mission status');

  MissionStatus::fromString('INVALID');
 }

 public function test_provides_french_label(): void
 {
  $this->assertEquals('Ouverte', MissionStatus::open()->getLabel());
  $this->assertEquals('Devis reçus', MissionStatus::quoted()->getLabel());
  $this->assertEquals('Acceptée', MissionStatus::accepted()->getLabel());
  $this->assertEquals('Annulée', MissionStatus::cancelled()->getLabel());
 }

 public function test_compares_statuses(): void
 {
  $status1 = MissionStatus::open();
  $status2 = MissionStatus::open();
  $status3 = MissionStatus::quoted();

  $this->assertTrue($status1->equals($status2));
  $this->assertFalse($status1->equals($status3));
 }

 public function test_converts_to_string(): void
 {
  $status = MissionStatus::open();

  $this->assertEquals('OPEN', (string) $status);
 }
}
