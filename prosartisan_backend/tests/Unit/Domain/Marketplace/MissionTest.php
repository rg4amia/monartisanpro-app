<?php

namespace Tests\Unit\Domain\Marketplace;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Exceptions\MaximumQuotesExceededException;
use App\Domain\Marketplace\Models\Devis\Devis;
use App\Domain\Marketplace\Models\Devis\DevisLine;
use App\Domain\Marketplace\Models\Mission\Mission;
use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Marketplace\Models\ValueObjects\MissionStatus;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MissionTest extends TestCase
{
 public function test_creates_mission_with_required_fields(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $this->assertInstanceOf(Mission::class, $mission);
  $this->assertEquals('Réparation de plomberie', $mission->getDescription());
  $this->assertTrue($mission->getCategory()->isPlumber());
  $this->assertTrue($mission->getStatus()->isOpen());
  $this->assertCount(0, $mission->getQuotes());
 }

 public function test_rejects_empty_description(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Mission description cannot be empty');

  Mission::create(
   UserId::generate(),
   '',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );
 }

 public function test_rejects_invalid_budget_range(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Budget minimum cannot be greater than maximum');

  Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(100000),
   MoneyAmount::fromFrancs(50000)
  );
 }

 public function test_adds_quote_to_mission(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $devis = Devis::create(
   $mission->getId(),
   UserId::generate(),
   [
    DevisLine::createMaterial('TuyauxPVC', 10, MoneyAmount::fromFrancs(5000)),
    DevisLine::createLabor('Installation', 1, MoneyAmount::fromFrancs(30000)),
   ]
  );

  $mission->addQuote($devis);

  $this->assertCount(1, $mission->getQuotes());
  $this->assertTrue($mission->getStatus()->isQuoted());
 }

 public function test_can_receive_up_to_3_quotes(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  for ($i = 0; $i < 3; $i++) {
   $devis = Devis::create(
    $mission->getId(),
    UserId::generate(),
    [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
   );
   $mission->addQuote($devis);
  }

  $this->assertCount(3, $mission->getQuotes());
  $this->assertFalse($mission->canReceiveMoreQuotes());
 }

 public function test_rejects_fourth_quote(): void
 {
  $this->expectException(MaximumQuotesExceededException::class);

  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  // Add 3 quotes
  for ($i = 0; $i < 3; $i++) {
   $devis = Devis::create(
    $mission->getId(),
    UserId::generate(),
    [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
   );
   $mission->addQuote($devis);
  }

  // Try to add 4th quote
  $devis = Devis::create(
   $mission->getId(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
  );
  $mission->addQuote($devis);
 }

 public function test_accepts_quote_and_rejects_others(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  // Add 3 quotes
  $devisIds = [];
  for ($i = 0; $i < 3; $i++) {
   $devis = Devis::create(
    $mission->getId(),
    UserId::generate(),
    [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
   );
   $mission->addQuote($devis);
   $devisIds[] = $devis->getId();
  }

  // Accept the second quote
  $mission->acceptQuote($devisIds[1]);

  $this->assertTrue($mission->getStatus()->isAccepted());

  $quotes = $mission->getQuotes();
  $this->assertTrue($quotes[0]->getStatus()->isRejected());
  $this->assertTrue($quotes[1]->getStatus()->isAccepted());
  $this->assertTrue($quotes[2]->getStatus()->isRejected());
 }

 public function test_rejects_quote_acceptance_for_non_existent_quote(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('not found in this mission');

  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $mission->acceptQuote(DevisId::generate());
 }

 public function test_cancels_mission(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $mission->cancel();

  $this->assertTrue($mission->getStatus()->isCancelled());
 }

 public function test_rejects_cancellation_of_accepted_mission(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Cannot cancel an accepted mission');

  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $devis = Devis::create(
   $mission->getId(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
  );
  $mission->addQuote($devis);
  $mission->acceptQuote($devis->getId());

  $mission->cancel();
 }

 public function test_gets_accepted_quote(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $devis = Devis::create(
   $mission->getId(),
   UserId::generate(),
   [DevisLine::createLabor('Service', 1, MoneyAmount::fromFrancs(50000))]
  );
  $mission->addQuote($devis);
  $mission->acceptQuote($devis->getId());

  $acceptedQuote = $mission->getAcceptedQuote();

  $this->assertNotNull($acceptedQuote);
  $this->assertTrue($acceptedQuote->getId()->equals($devis->getId()));
 }

 public function test_returns_null_when_no_accepted_quote(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $this->assertNull($mission->getAcceptedQuote());
 }

 public function test_converts_to_array(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $array = $mission->toArray();

  $this->assertArrayHasKey('id', $array);
  $this->assertArrayHasKey('client_id', $array);
  $this->assertArrayHasKey('description', $array);
  $this->assertArrayHasKey('category', $array);
  $this->assertArrayHasKey('location', $array);
  $this->assertArrayHasKey('budget_min', $array);
  $this->assertArrayHasKey('budget_max', $array);
  $this->assertArrayHasKey('status', $array);
  $this->assertArrayHasKey('quotes_count', $array);
  $this->assertArrayHasKey('quotes', $array);
  $this->assertArrayHasKey('created_at', $array);
 }
}
