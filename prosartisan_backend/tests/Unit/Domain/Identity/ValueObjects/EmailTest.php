<?php

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\Models\ValueObjects\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
 public function test_can_create_valid_email(): void
 {
  $email = new Email('test@example.com');

  $this->assertEquals('test@example.com', $email->getValue());
 }

 public function test_email_is_normalized_to_lowercase(): void
 {
  $email = new Email('Test@Example.COM');

  $this->assertEquals('test@example.com', $email->getValue());
 }

 public function test_throws_exception_for_invalid_email(): void
 {
  $this->expectException(InvalidArgumentException::class);

  new Email('invalid-email');
 }

 public function test_can_get_domain(): void
 {
  $email = new Email('user@example.com');

  $this->assertEquals('example.com', $email->getDomain());
 }

 public function test_can_get_local_part(): void
 {
  $email = new Email('user@example.com');

  $this->assertEquals('user', $email->getLocalPart());
 }

 public function test_emails_are_equal(): void
 {
  $email1 = new Email('test@example.com');
  $email2 = new Email('test@example.com');

  $this->assertTrue($email1->equals($email2));
 }
}
