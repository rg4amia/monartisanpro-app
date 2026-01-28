<?php

namespace Tests\Unit\Domain\Financial\Models\JetonMateriel;

use App\Domain\Financial\Models\JetonMateriel\JetonMateriel;
use App\Domain\Financial\Models\ValueObjects\JetonCode;
use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JetonMaterielTest extends TestCase
{
    private SequestreId $sequestreId;

    private UserId $artisanId;

    private UserId $supplierId;

    private MoneyAmount $amount;

    private GPS_Coordinates $artisanLocation;

    private GPS_Coordinates $supplierLocation;

    protected function setUp(): void
    {
        $this->sequestreId = SequestreId::generate();
        $this->artisanId = UserId::generate();
        $this->supplierId = UserId::generate();
        $this->amount = MoneyAmount::fromCentimes(100000); // 1000 XOF
        $this->artisanLocation = new GPS_Coordinates(5.3600, -4.0083, 5.0); // Abidjan
        $this->supplierLocation = new GPS_Coordinates(5.3605, -4.0088, 5.0); // 50m away
    }

    public function test_create_jeton_with_generated_id_and_code(): void
    {
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount
        );

        $this->assertInstanceOf(JetonId::class, $jeton->getId());
        $this->assertInstanceOf(JetonCode::class, $jeton->getCode());
        $this->assertTrue($jeton->getSequestreId()->equals($this->sequestreId));
        $this->assertTrue($jeton->getArtisanId()->equals($this->artisanId));
        $this->assertTrue($jeton->getTotalAmount()->equals($this->amount));
        $this->assertTrue($jeton->getStatus()->isActive());
        $this->assertFalse($jeton->isExpired());
        $this->assertTrue($jeton->canBeUsed());
    }

    public function test_jeton_expires_after_7_days(): void
    {
        $createdAt = new DateTime('2024-01-01 10:00:00');
        $jeton = new JetonMateriel(
            JetonId::generate(),
            $this->sequestreId,
            $this->artisanId,
            JetonCode::generate(),
            $this->amount,
            [],
            null,
            null,
            $createdAt
        );

        $expectedExpiry = new DateTime('2024-01-08 10:00:00');
        $this->assertEquals($expectedExpiry, $jeton->getExpiresAt());
    }

    public function test_validate_jeton_with_authorized_supplier(): void
    {
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount,
            [$this->supplierId]
        );

        $usageAmount = MoneyAmount::fromCentimes(50000); // 500 XOF

        $jeton->validate(
            $this->supplierId,
            $usageAmount,
            $this->artisanLocation,
            $this->supplierLocation
        );

        $this->assertTrue($jeton->getUsedAmount()->equals($usageAmount));
        $this->assertTrue($jeton->getStatus()->isPartiallyUsed());
        $this->assertEquals(50000, $jeton->getRemainingAmount()->toCentimes());
    }

    public function test_validate_jeton_fully_uses_amount(): void
    {
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount
        );

        $jeton->validate(
            $this->supplierId,
            $this->amount,
            $this->artisanLocation,
            $this->supplierLocation
        );

        $this->assertTrue($jeton->getUsedAmount()->equals($this->amount));
        $this->assertTrue($jeton->getStatus()->isFullyUsed());
        $this->assertEquals(0, $jeton->getRemainingAmount()->toCentimes());
        $this->assertFalse($jeton->canBeUsed());
    }

    public function test_validate_jeton_fails_when_supplier_not_authorized(): void
    {
        $unauthorizedSupplierId = UserId::generate();
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount,
            [$this->supplierId] // Only this supplier is authorized
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supplier is not authorized for this jeton');

        $jeton->validate(
            $unauthorizedSupplierId,
            MoneyAmount::fromCentimes(50000),
            $this->artisanLocation,
            $this->supplierLocation
        );
    }

    public function test_validate_jeton_fails_when_too_far_apart(): void
    {
        $farLocation = new GPS_Coordinates(5.4000, -4.1000, 5.0); // > 100m away
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Artisan and supplier must be within 100m');

        $jeton->validate(
            $this->supplierId,
            MoneyAmount::fromCentimes(50000),
            $this->artisanLocation,
            $farLocation
        );
    }

    public function test_validate_jeton_fails_when_amount_exceeds_remaining(): void
    {
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount
        );

        $excessiveAmount = MoneyAmount::fromCentimes(150000); // More than total

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot use 1 500 FCFA, only 1 000 FCFA remaining');

        $jeton->validate(
            $this->supplierId,
            $excessiveAmount,
            $this->artisanLocation,
            $this->supplierLocation
        );
    }

    public function test_validate_jeton_fails_when_expired(): void
    {
        $pastDate = new DateTime('-10 days');
        $jeton = new JetonMateriel(
            JetonId::generate(),
            $this->sequestreId,
            $this->artisanId,
            JetonCode::generate(),
            $this->amount,
            [],
            null,
            null,
            $pastDate
        );

        $this->assertTrue($jeton->isExpired());
        $this->assertFalse($jeton->canBeUsed());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot use expired jeton');

        $jeton->validate(
            $this->supplierId,
            MoneyAmount::fromCentimes(50000),
            $this->artisanLocation,
            $this->supplierLocation
        );
    }

    public function test_is_supplier_authorized_returns_true_when_no_restrictions(): void
    {
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount
            // No authorized suppliers = all suppliers allowed
        );

        $this->assertTrue($jeton->isSupplierAuthorized($this->supplierId));
        $this->assertTrue($jeton->isSupplierAuthorized(UserId::generate()));
    }

    public function test_add_authorized_supplier(): void
    {
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount,
            [$this->supplierId]
        );

        $newSupplierId = UserId::generate();
        $this->assertFalse($jeton->isSupplierAuthorized($newSupplierId));

        $jeton->addAuthorizedSupplier($newSupplierId);
        $this->assertTrue($jeton->isSupplierAuthorized($newSupplierId));
    }

    public function test_expire_jeton(): void
    {
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount
        );

        $this->assertTrue($jeton->canBeUsed());

        $jeton->expire();

        $this->assertTrue($jeton->getStatus()->isExpired());
        $this->assertFalse($jeton->canBeUsed());
    }

    public function test_constructor_validates_positive_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Jeton total amount must be positive');

        new JetonMateriel(
            JetonId::generate(),
            $this->sequestreId,
            $this->artisanId,
            JetonCode::generate(),
            MoneyAmount::fromCentimes(0) // Invalid amount
        );
    }

    public function test_constructor_validates_authorized_suppliers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All authorized suppliers must be UserId instances');

        new JetonMateriel(
            JetonId::generate(),
            $this->sequestreId,
            $this->artisanId,
            JetonCode::generate(),
            $this->amount,
            ['invalid_supplier'] // Should be UserId instances
        );
    }

    public function test_to_array_returns_complete_data(): void
    {
        $jeton = JetonMateriel::create(
            $this->sequestreId,
            $this->artisanId,
            $this->amount,
            [$this->supplierId]
        );

        $array = $jeton->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('sequestre_id', $array);
        $this->assertArrayHasKey('artisan_id', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('total_amount', $array);
        $this->assertArrayHasKey('used_amount', $array);
        $this->assertArrayHasKey('remaining_amount', $array);
        $this->assertArrayHasKey('authorized_suppliers', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('can_be_used', $array);
        $this->assertArrayHasKey('is_expired', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('expires_at', $array);

        $this->assertEquals($this->sequestreId->getValue(), $array['sequestre_id']);
        $this->assertEquals($this->artisanId->getValue(), $array['artisan_id']);
        $this->assertEquals([$this->supplierId->getValue()], $array['authorized_suppliers']);
        $this->assertTrue($array['can_be_used']);
        $this->assertFalse($array['is_expired']);
    }
}
