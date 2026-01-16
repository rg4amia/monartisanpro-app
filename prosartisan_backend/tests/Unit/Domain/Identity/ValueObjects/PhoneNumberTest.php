<?php

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
 public function test_can_create_valid_phone_number(): void
 {
  $phone = new PhoneNumber('+22507123456');

  $this->assertEquals('+22507123456', $phone->getValue());
 }

 public function test_normalizes_phone_number_with_leading_zero(): void
 {
  $phone = new PhoneNumber('0712345678');

  $this->assertEquals('+225712345678', $phone->getValue());
 }

 public function test_adds_country_code_if_missing(): void
 {
  $phone = new PhoneNumber('0712345678');

  $this->assertTrue($phone->isCoteDIvoire());
  $this->assertEquals('225', $phone->getCountryCode());
 }

 public function test_can_format_phone_number(): void
 {
  $phone = new PhoneNumber('+22507123456');

  $formatted = $phone->format();

  $this->assertStringContainsString('+225', $formatted);
 }

 public function test_throws_exception_for_invalid_phone_number(): void
 {
  $this->expectException(InvalidArgumentException::class);

  new PhoneNumber('123'); // Too short
 }

 public function test_phone_numbers_are_equal(): void
 {
  $phone1 = new PhoneNumber('+22507123456');
  $phone2 = new PhoneNumber('+22507123456');

  $this->assertTrue($phone1->equals($phone2));
 }
}
