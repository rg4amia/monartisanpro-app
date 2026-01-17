<?php

namespace Tests\Unit\Infrastructure;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Infrastructure\Repositories\PostgresSequestreRepository;
use App\Infrastructure\Repositories\PostgresJetonRepository;
use App\Infrastructure\Repositories\PostgresTransactionRepository;
use App\Domain\Financial\Models\Sequestre\Sequestre;
use App\Domain\Financial\Models\JetonMateriel\JetonMateriel;
use App\Domain\Financial\Models\Transaction\Transaction;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Financial\Models\ValueObjects\TransactionId;
use App\Domain\Financial\Models\ValueObjects\JetonCode;
use App\Domain\Financial\Models\ValueObjects\TransactionType;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;

class FinancialRepositoriesTest extends TestCase
{
 use RefreshDatabase;

 private PostgresSequestreRepository $sequestreRepository;
 private PostgresJetonRepository $jetonRepository;
 private PostgresTransactionRepository $transactionRepository;

 protected function setUp(): void
 {
  parent::setUp();
  $this->sequestreRepository = new PostgresSequestreRepository();
  $this->jetonRepository = new PostgresJetonRepository();
  $this->transactionRepository = new PostgresTransactionRepository();
 }

 /** @test */
 public function it_can_save_and_retrieve_sequestre()
 {
  // Arrange
  $sequestre = Sequestre::create(
   MissionId::generate(),
   UserId::generate(),
   UserId::generate(),
   MoneyAmount::fromCentimes(100000) // 1000 XOF
  );

  // Act
  $this->sequestreRepository->save($sequestre);
  $retrieved = $this->sequestreRepository->findById($sequestre->getId());

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertEquals($sequestre->getId()->getValue(), $retrieved->getId()->getValue());
  $this->assertEquals($sequestre->getTotalAmount()->toCentimes(), $retrieved->getTotalAmount()->toCentimes());
 }

 /** @test */
 public function it_can_save_and_retrieve_jeton()
 {
  // Arrange
  $jeton = JetonMateriel::create(
   SequestreId::generate(),
   UserId::generate(),
   MoneyAmount::fromCentimes(65000) // 650 XOF
  );

  // Act
  $this->jetonRepository->save($jeton);
  $retrieved = $this->jetonRepository->findById($jeton->getId());

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertEquals($jeton->getId()->getValue(), $retrieved->getId()->getValue());
  $this->assertEquals($jeton->getCode()->getValue(), $retrieved->getCode()->getValue());
  $this->assertEquals($jeton->getTotalAmount()->toCentimes(), $retrieved->getTotalAmount()->toCentimes());
 }

 /** @test */
 public function it_can_save_and_retrieve_transaction()
 {
  // Arrange
  $transaction = Transaction::create(
   UserId::generate(),
   UserId::generate(),
   MoneyAmount::fromCentimes(50000), // 500 XOF
   TransactionType::escrowBlock(),
   'Test escrow block'
  );

  // Act
  $this->transactionRepository->save($transaction);
  $retrieved = $this->transactionRepository->findById($transaction->getId());

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertEquals($transaction->getId()->getValue(), $retrieved->getId()->getValue());
  $this->assertEquals($transaction->getAmount()->toCentimes(), $retrieved->getAmount()->toCentimes());
  $this->assertEquals($transaction->getType()->getValue(), $retrieved->getType()->getValue());
 }

 /** @test */
 public function transactions_table_is_append_only()
 {
  // Arrange
  $transaction = Transaction::create(
   UserId::generate(),
   UserId::generate(),
   MoneyAmount::fromCentimes(50000),
   TransactionType::escrowBlock(),
   'Test transaction'
  );

  // Act - Save transaction
  $this->transactionRepository->save($transaction);

  // Try to save the same transaction again (should create a new record, not update)
  $transaction->complete('test-reference');
  $this->transactionRepository->save($transaction);

  // Assert - Should have 2 records (append-only behavior)
  $transactions = $this->transactionRepository->findByUserId($transaction->getFromUserId());
  $this->assertCount(2, $transactions);
 }

 /** @test */
 public function it_can_find_jeton_by_code()
 {
  // Arrange
  $jeton = JetonMateriel::create(
   SequestreId::generate(),
   UserId::generate(),
   MoneyAmount::fromCentimes(65000)
  );
  $this->jetonRepository->save($jeton);

  // Act
  $retrieved = $this->jetonRepository->findByCode($jeton->getCode()->getValue());

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertEquals($jeton->getId()->getValue(), $retrieved->getId()->getValue());
 }

 /** @test */
 public function it_can_find_sequestre_by_mission_id()
 {
  // Arrange
  $missionId = MissionId::generate();
  $sequestre = Sequestre::create(
   $missionId,
   UserId::generate(),
   UserId::generate(),
   MoneyAmount::fromCentimes(100000)
  );
  $this->sequestreRepository->save($sequestre);

  // Act
  $retrieved = $this->sequestreRepository->findByMissionId($missionId);

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertEquals($sequestre->getId()->getValue(), $retrieved->getId()->getValue());
 }
}
