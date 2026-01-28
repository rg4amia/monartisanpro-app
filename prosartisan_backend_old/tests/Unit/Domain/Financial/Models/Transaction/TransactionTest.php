<?php

namespace Tests\Unit\Domain\Financial\Models\Transaction;

use App\Domain\Financial\Models\Transaction\Transaction;
use App\Domain\Financial\Models\ValueObjects\TransactionId;
use App\Domain\Financial\Models\ValueObjects\TransactionType;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase
{
    private UserId $fromUserId;

    private UserId $toUserId;

    private MoneyAmount $amount;

    protected function setUp(): void
    {
        $this->fromUserId = UserId::generate();
        $this->toUserId = UserId::generate();
        $this->amount = MoneyAmount::fromCentimes(100000); // 1000 XOF
    }

    public function test_create_transaction_with_generated_id(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease(),
            'Labor payment for milestone completion'
        );

        $this->assertInstanceOf(TransactionId::class, $transaction->getId());
        $this->assertTrue($transaction->getFromUserId()->equals($this->fromUserId));
        $this->assertTrue($transaction->getToUserId()->equals($this->toUserId));
        $this->assertTrue($transaction->getAmount()->equals($this->amount));
        $this->assertTrue($transaction->getType()->equals(TransactionType::laborRelease()));
        $this->assertTrue($transaction->getStatus()->isPending());
        $this->assertEquals('Labor payment for milestone completion', $transaction->getDescription());
        $this->assertNull($transaction->getCompletedAt());
        $this->assertNull($transaction->getFailedAt());
    }

    public function test_complete_transaction(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease()
        );

        $mobileMoneyRef = 'MM123456789';
        $transaction->complete($mobileMoneyRef);

        $this->assertTrue($transaction->getStatus()->isCompleted());
        $this->assertEquals($mobileMoneyRef, $transaction->getMobileMoneyReference());
        $this->assertInstanceOf(DateTime::class, $transaction->getCompletedAt());
    }

    public function test_fail_transaction(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease()
        );

        $failureReason = 'Insufficient funds';
        $transaction->fail($failureReason);

        $this->assertTrue($transaction->getStatus()->isFailed());
        $this->assertEquals($failureReason, $transaction->getFailureReason());
        $this->assertInstanceOf(DateTime::class, $transaction->getFailedAt());
    }

    public function test_cancel_transaction(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease()
        );

        $transaction->cancel();

        $this->assertTrue($transaction->getStatus()->isCancelled());
    }

    public function test_cannot_complete_non_pending_transaction(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease()
        );

        $transaction->complete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only pending transactions can be completed');

        $transaction->complete();
    }

    public function test_cannot_fail_non_pending_transaction(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease()
        );

        $transaction->complete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only pending transactions can be failed');

        $transaction->fail('Some reason');
    }

    public function test_cannot_cancel_non_pending_transaction(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease()
        );

        $transaction->complete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only pending transactions can be cancelled');

        $transaction->cancel();
    }

    public function test_involves_user(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease()
        );

        $this->assertTrue($transaction->involvesUser($this->fromUserId));
        $this->assertTrue($transaction->involvesUser($this->toUserId));
        $this->assertFalse($transaction->involvesUser(UserId::generate()));
    }

    public function test_get_direction_for_user(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease()
        );

        $this->assertEquals('outgoing', $transaction->getDirectionForUser($this->fromUserId));
        $this->assertEquals('incoming', $transaction->getDirectionForUser($this->toUserId));
        $this->assertNull($transaction->getDirectionForUser(UserId::generate()));
    }

    public function test_escrow_block_transaction_only_needs_from_user(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            null, // No toUserId for escrow block
            $this->amount,
            TransactionType::escrowBlock(),
            'Block funds in escrow'
        );

        $this->assertTrue($transaction->getFromUserId()->equals($this->fromUserId));
        $this->assertNull($transaction->getToUserId());
        $this->assertTrue($transaction->getType()->equals(TransactionType::escrowBlock()));
    }

    public function test_service_fee_transaction_only_needs_from_user(): void
    {
        $transaction = Transaction::create(
            $this->fromUserId,
            null, // No toUserId for service fee
            MoneyAmount::fromCentimes(5000), // 5% fee
            TransactionType::serviceFee(),
            'Platform service fee'
        );

        $this->assertTrue($transaction->getFromUserId()->equals($this->fromUserId));
        $this->assertNull($transaction->getToUserId());
        $this->assertTrue($transaction->getType()->equals(TransactionType::serviceFee()));
    }

    public function test_constructor_validates_positive_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction amount must be positive');

        Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            MoneyAmount::fromCentimes(0), // Invalid amount
            TransactionType::laborRelease()
        );
    }

    public function test_constructor_validates_escrow_block_requires_from_user(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Escrow block transaction requires fromUserId');

        new Transaction(
            TransactionId::generate(),
            null, // Missing fromUserId
            null,
            $this->amount,
            TransactionType::escrowBlock()
        );
    }

    public function test_constructor_validates_regular_transaction_requires_both_users(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction requires both fromUserId and toUserId');

        Transaction::create(
            $this->fromUserId,
            null, // Missing toUserId
            $this->amount,
            TransactionType::laborRelease()
        );
    }

    public function test_constructor_validates_users_cannot_be_same(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction cannot have same fromUserId and toUserId');

        Transaction::create(
            $this->fromUserId,
            $this->fromUserId, // Same as fromUserId
            $this->amount,
            TransactionType::laborRelease()
        );
    }

    public function test_to_array_returns_complete_data(): void
    {
        $metadata = ['mission_id' => 'test-mission-123'];
        $transaction = Transaction::create(
            $this->fromUserId,
            $this->toUserId,
            $this->amount,
            TransactionType::laborRelease(),
            'Test transaction',
            $metadata
        );

        $transaction->complete('MM123456789');

        $array = $transaction->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('from_user_id', $array);
        $this->assertArrayHasKey('to_user_id', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('type_label', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('status_label', $array);
        $this->assertArrayHasKey('mobile_money_reference', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('completed_at', $array);
        $this->assertArrayHasKey('failed_at', $array);
        $this->assertArrayHasKey('failure_reason', $array);

        $this->assertEquals($this->fromUserId->getValue(), $array['from_user_id']);
        $this->assertEquals($this->toUserId->getValue(), $array['to_user_id']);
        $this->assertEquals('LABOR_RELEASE', $array['type']);
        $this->assertEquals('Libération main-d\'œuvre', $array['type_label']);
        $this->assertEquals('COMPLETED', $array['status']);
        $this->assertEquals('Terminée', $array['status_label']);
        $this->assertEquals('MM123456789', $array['mobile_money_reference']);
        $this->assertEquals('Test transaction', $array['description']);
        $this->assertEquals($metadata, $array['metadata']);
        $this->assertNotNull($array['completed_at']);
        $this->assertNull($array['failed_at']);
        $this->assertNull($array['failure_reason']);
    }
}
