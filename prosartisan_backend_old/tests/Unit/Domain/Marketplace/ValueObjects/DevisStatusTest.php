<?php

namespace Tests\Unit\Domain\Marketplace\ValueObjects;

use App\Domain\Marketplace\Models\ValueObjects\DevisStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DevisStatusTest extends TestCase
{
    public function test_creates_pending_status(): void
    {
        $status = DevisStatus::pending();

        $this->assertEquals('PENDING', $status->getValue());
        $this->assertTrue($status->isPending());
        $this->assertFalse($status->isAccepted());
        $this->assertFalse($status->isRejected());
    }

    public function test_creates_accepted_status(): void
    {
        $status = DevisStatus::accepted();

        $this->assertEquals('ACCEPTED', $status->getValue());
        $this->assertTrue($status->isAccepted());
    }

    public function test_creates_rejected_status(): void
    {
        $status = DevisStatus::rejected();

        $this->assertEquals('REJECTED', $status->getValue());
        $this->assertTrue($status->isRejected());
    }

    public function test_creates_from_string(): void
    {
        $status = DevisStatus::fromString('PENDING');

        $this->assertTrue($status->isPending());
    }

    public function test_creates_from_lowercase_string(): void
    {
        $status = DevisStatus::fromString('pending');

        $this->assertTrue($status->isPending());
    }

    public function test_rejects_invalid_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid devis status');

        DevisStatus::fromString('INVALID');
    }

    public function test_provides_french_label(): void
    {
        $this->assertEquals('En attente', DevisStatus::pending()->getLabel());
        $this->assertEquals('Accepté', DevisStatus::accepted()->getLabel());
        $this->assertEquals('Rejeté', DevisStatus::rejected()->getLabel());
    }

    public function test_compares_statuses(): void
    {
        $status1 = DevisStatus::pending();
        $status2 = DevisStatus::pending();
        $status3 = DevisStatus::accepted();

        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    public function test_converts_to_string(): void
    {
        $status = DevisStatus::pending();

        $this->assertEquals('PENDING', (string) $status);
    }
}
