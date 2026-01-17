<?php

namespace Tests\Unit\Domain\Marketplace;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\Devis\Devis;
use App\Domain\Marketplace\Models\Devis\DevisLine;
use App\Domain\Marketplace\Models\ValueObjects\DevisStatus;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DevisTest extends TestCase
{
 public function test_creates_devis_with_line_items(): void
 {
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [
    DevisLine::createMaterial('Tuyaux PVC', 10, MoneyAmount::fromFrancs(5000)),
    DevisLine::createLabor('Installation', 1, MoneyAmount::fromFrancs(30000)),
   ]
  );

  $this->assertInstanceOf(Devis::class, $devis);
  $this->assertTrue($devis->getStatus()->isPending());
  $this->assertCount(2, $devis->getLineItems());
 }

 public function test_calculates_total_amount_from_line_items(): void
 {
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [
    DevisLine::createMaterial('Tuyaux PVC', 10, MoneyAmount::fromFrancs(5000)),
    DevisLine::createLabor('Installation', 1, MoneyAmount::fromFrancs(30000)),
   ]
  );

  // 10 * 5000 = 50000 (materials) + 1 * 30000 = 30000 (labor) = 80000 total
  $this->assertEquals(8000000, $devis->getTotalAmount()->toCentimes());
  $this->assertEquals(5000000, $devis->getMaterialsAmount()->toCentimes());
  $this->assertEquals(3000000, $devis->getLaborAmount()->toCentimes());
 }

 public function test_rejects_devis_without_line_items(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Devis must have at least one line item');

  Devis::create(
   MissionId::generate(),
   UserId::generate(),
   []
  );
 }

 public function test_accepts_devis(): void
 {
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
  );

  $devis->accept();

  $this->assertTrue($devis->getStatus()->isAccepted());
 }

 public function test_rejects_devis(): void
 {
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
  );

  $devis->reject();

  $this->assertTrue($devis->getStatus()->isRejected());
 }

 public function test_rejects_acceptance_of_non_pending_devis(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Cannot accept devis with status');

  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
  );

  $devis->reject();
  $devis->accept(); // Should fail
 }

 public function test_rejects_rejection_of_non_pending_devis(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Cannot reject devis with status');

  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
  );

  $devis->accept();
  $devis->reject(); // Should fail
 }

 public function test_checks_expiration(): void
 {
  $expiresAt = new DateTime('+7 days');
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))],
   $expiresAt
  );

  $this->assertFalse($devis->isExpired());
 }

 public function test_detects_expired_devis(): void
 {
  $expiresAt = new DateTime('-1 day');
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))],
   $expiresAt
  );

  $this->assertTrue($devis->isExpired());
 }

 public function test_rejects_acceptance_of_expired_devis(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Cannot accept expired devis');

  $expiresAt = new DateTime('-1 day');
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))],
   $expiresAt
  );

  $devis->accept();
 }

 public function test_devis_without_expiration_never_expires(): void
 {
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
  );

  $this->assertFalse($devis->isExpired());
  $this->assertNull($devis->getExpiresAt());
 }

 public function test_separates_materials_and_labor_amounts(): void
 {
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [
    DevisLine::createMaterial('Ciment', 5, MoneyAmount::fromFrancs(10000)),
    DevisLine::createMaterial('Sable', 10, MoneyAmount::fromFrancs(5000)),
    DevisLine::createLabor('Maçonnerie', 3, MoneyAmount::fromFrancs(20000)),
    DevisLine::createLabor('Finition', 1, MoneyAmount::fromFrancs(15000)),
   ]
  );

  // Materials: (5 * 10000) + (10 * 5000) = 50000 + 50000 = 100000
  // Labor: (3 * 20000) + (1 * 15000) = 60000 + 15000 = 75000
  // Total: 175000

  $this->assertEquals(10000000, $devis->getMaterialsAmount()->toCentimes());
  $this->assertEquals(7500000, $devis->getLaborAmount()->toCentimes());
  $this->assertEquals(17500000, $devis->getTotalAmount()->toCentimes());
 }

 public function test_converts_to_array(): void
 {
  $devis = Devis::create(
   MissionId::generate(),
   UserId::generate(),
   [
    DevisLine::createMaterial('Tuyaux PVC', 10, MoneyAmount::fromFrancs(5000)),
    DevisLine::createLabor('Installation', 1, MoneyAmount::fromFrancs(30000)),
   ]
  );

  $array = $devis->toArray();

  $this->assertArrayHasKey('id', $array);
  $this->assertArrayHasKey('mission_id', $array);
  $this->assertArrayHasKey('artisan_id', $array);
  $this->assertArrayHasKey('total_amount', $array);
  $this->assertArrayHasKey('materials_amount', $array);
  $this->assertArrayHasKey('labor_amount', $array);
  $this->assertArrayHasKey('line_items', $array);
  $this->assertArrayHasKey('status', $array);
  $this->assertArrayHasKey('created_at', $array);
  $this->assertArrayHasKey('expires_at', $array);
  $this->assertCount(2, $array['line_items']);
 }
}
