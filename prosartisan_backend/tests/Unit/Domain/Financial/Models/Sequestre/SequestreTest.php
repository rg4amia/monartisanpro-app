<?php

namespace Tests\Unit\Domain\Financial\Models\Sequestre;

use App\Domain\Financial\Models\Sequestre\Sequestre;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Financial\Models\ValueObjects\SequestreStatus;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SequestreTest extends TestCase
{
 private MissionId $missionId;
 private UserId $clientId;
 private UserId $artisanId;
 private MoneyAmount $totalAmount;

 protected function setUp(): void
 {
  $this->missionId = MissionId::generate();
  $this->clientId = UserId::generate();
  $this->artisanId = UserId::generate();
  $this->totalAmount = MoneyAmount::fromCentimes(100000); // 1000 XOF
 }

 public function test_create_sequestre_with_generated_id(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  $this->assertInstanceOf(SequestreId::class, $sequestre->getId());
  $this->assertTrue($sequestre->getMissionId()->equals($this->missionId));
  $this->assertTrue($sequestre->getClientId()->equals($this->clientId));
  $this->assertTrue($sequestre->getArtisanId()->equals($this->artisanId));
  $this->assertTrue($sequestre->getTotalAmount()->equals($this->totalAmount));
  $this->assertTrue($sequestre->getStatus()->isBlocked());
  $this->assertInstanceOf(DateTime::class, $sequestre->getCreatedAt());
 }

 public function test_fragment_splits_65_35(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  // Should be automatically fragmented in constructor
  $expectedMaterials = MoneyAmount::fromCentimes(65000); // 65% of 100000
  $expectedLabor = MoneyAmount::fromCentimes(35000); // 35% of 100000

  $this->assertTrue($sequestre->getMaterialsAmount()->equals($expectedMaterials));
  $this->assertTrue($sequestre->getLaborAmount()->equals($expectedLabor));
 }

 public function test_release_materials(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  $releaseAmount = MoneyAmount::fromCentimes(30000);
  $sequestre->releaseMaterials($releaseAmount);

  $this->assertTrue($sequestre->getMaterialsReleased()->equals($releaseAmount));
  $this->assertTrue($sequestre->getStatus()->isPartial());
  $this->assertEquals(35000, $sequestre->getRemainingMaterials()->toCentimes()); // 65000 - 30000
 }

 public function test_release_labor(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  $releaseAmount = MoneyAmount::fromCentimes(20000);
  $sequestre->releaseLabor($releaseAmount);

  $this->assertTrue($sequestre->getLaborReleased()->equals($releaseAmount));
  $this->assertTrue($sequestre->getStatus()->isPartial());
  $this->assertEquals(15000, $sequestre->getRemainingLabor()->toCentimes()); // 35000 - 20000
 }

 public function test_fully_release_sequestre(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  // Release all materials
  $sequestre->releaseMaterials($sequestre->getMaterialsAmount());
  $this->assertTrue($sequestre->getStatus()->isPartial());

  // Release all labor
  $sequestre->releaseLabor($sequestre->getLaborAmount());
  $this->assertTrue($sequestre->getStatus()->isReleased());
  $this->assertTrue($sequestre->isFullyReleased());
  $this->assertEquals(0, $sequestre->getRemainingTotal()->toCentimes());
 }

 public function test_refund_sequestre(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  $refundAmount = MoneyAmount::fromCentimes(50000);
  $sequestre->refund($refundAmount);

  $this->assertTrue($sequestre->getStatus()->isRefunded());

  // Refund should be proportional (65/35 split)
  $expectedMaterialsRefund = MoneyAmount::fromCentimes(32500); // 65% of 50000
  $expectedLaborRefund = MoneyAmount::fromCentimes(17500); // 35% of 50000

  $this->assertTrue($sequestre->getMaterialsReleased()->equals($expectedMaterialsRefund));
  $this->assertTrue($sequestre->getLaborReleased()->equals($expectedLaborRefund));
  $this->assertEquals(50000, $sequestre->getRemainingTotal()->toCentimes());
 }

 public function test_cannot_release_more_materials_than_available(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  $excessiveAmount = MoneyAmount::fromCentimes(70000); // More than 65000 available

  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Cannot release 700 FCFA from materials, only 650 FCFA remaining');

  $sequestre->releaseMaterials($excessiveAmount);
 }

 public function test_cannot_release_more_labor_than_available(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  $excessiveAmount = MoneyAmount::fromCentimes(40000); // More than 35000 available

  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Cannot release 400 FCFA from labor, only 350 FCFA remaining');

  $sequestre->releaseLabor($excessiveAmount);
 }

 public function test_cannot_refund_more_than_available(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  // Release some funds first
  $sequestre->releaseMaterials(MoneyAmount::fromCentimes(30000));

  $excessiveRefund = MoneyAmount::fromCentimes(80000); // More than remaining 70000

  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Cannot refund 800 FCFA, only 700 FCFA available');

  $sequestre->refund($excessiveRefund);
 }

 public function test_constructor_validates_positive_amount(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Sequestre total amount must be positive');

  Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   MoneyAmount::fromCentimes(0) // Invalid amount
  );
 }

 public function test_get_remaining_amounts(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  // Initially, all amounts should be remaining
  $this->assertEquals(65000, $sequestre->getRemainingMaterials()->toCentimes());
  $this->assertEquals(35000, $sequestre->getRemainingLabor()->toCentimes());
  $this->assertEquals(100000, $sequestre->getRemainingTotal()->toCentimes());

  // After releasing some materials
  $sequestre->releaseMaterials(MoneyAmount::fromCentimes(20000));
  $this->assertEquals(45000, $sequestre->getRemainingMaterials()->toCentimes());
  $this->assertEquals(35000, $sequestre->getRemainingLabor()->toCentimes());
  $this->assertEquals(80000, $sequestre->getRemainingTotal()->toCentimes());

  // After releasing some labor
  $sequestre->releaseLabor(MoneyAmount::fromCentimes(10000));
  $this->assertEquals(45000, $sequestre->getRemainingMaterials()->toCentimes());
  $this->assertEquals(25000, $sequestre->getRemainingLabor()->toCentimes());
  $this->assertEquals(70000, $sequestre->getRemainingTotal()->toCentimes());
 }

 public function test_to_array_returns_complete_data(): void
 {
  $sequestre = Sequestre::create(
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount
  );

  $sequestre->releaseMaterials(MoneyAmount::fromCentimes(20000));

  $array = $sequestre->toArray();

  $this->assertArrayHasKey('id', $array);
  $this->assertArrayHasKey('mission_id', $array);
  $this->assertArrayHasKey('client_id', $array);
  $this->assertArrayHasKey('artisan_id', $array);
  $this->assertArrayHasKey('total_amount', $array);
  $this->assertArrayHasKey('materials_amount', $array);
  $this->assertArrayHasKey('labor_amount', $array);
  $this->assertArrayHasKey('materials_released', $array);
  $this->assertArrayHasKey('labor_released', $array);
  $this->assertArrayHasKey('remaining_materials', $array);
  $this->assertArrayHasKey('remaining_labor', $array);
  $this->assertArrayHasKey('remaining_total', $array);
  $this->assertArrayHasKey('status', $array);
  $this->assertArrayHasKey('is_fully_released', $array);
  $this->assertArrayHasKey('created_at', $array);

  $this->assertEquals($this->missionId->getValue(), $array['mission_id']);
  $this->assertEquals($this->clientId->getValue(), $array['client_id']);
  $this->assertEquals($this->artisanId->getValue(), $array['artisan_id']);
  $this->assertEquals('PARTIAL', $array['status']);
  $this->assertFalse($array['is_fully_released']);
 }

 public function test_constructor_with_existing_fragmentation(): void
 {
  $materialsAmount = MoneyAmount::fromCentimes(60000);
  $laborAmount = MoneyAmount::fromCentimes(40000);

  $sequestre = new Sequestre(
   SequestreId::generate(),
   $this->missionId,
   $this->clientId,
   $this->artisanId,
   $this->totalAmount,
   $materialsAmount,
   $laborAmount
  );

  // Should use provided amounts instead of calculating
  $this->assertTrue($sequestre->getMaterialsAmount()->equals($materialsAmount));
  $this->assertTrue($sequestre->getLaborAmount()->equals($laborAmount));
 }
}
