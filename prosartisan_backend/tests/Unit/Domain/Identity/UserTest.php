<?php

namespace Tests\Unit\Domain\Identity;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use DateTime;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
 public function test_can_create_user_with_generated_id(): void
 {
  $email = new Email('test@example.com');
  $password = HashedPassword::fromPlainText('password123');
  $type = UserType::CLIENT();

  $user = User::create($email, $password, $type);

  $this->assertInstanceOf(User::class, $user);
  $this->assertInstanceOf(UserId::class, $user->getId());
  $this->assertEquals($email, $user->getEmail());
  $this->assertEquals($type, $user->getType());
  $this->assertTrue($user->getStatus()->isPending());
 }

 public function test_can_verify_kyc_documents(): void
 {
  $user = User::create(
   new Email('artisan@example.com'),
   HashedPassword::fromPlainText('password123'),
   UserType::ARTISAN()
  );

  $kycDocs = new KYCDocuments(
   'CNI',
   '123456789',
   '/path/to/id.jpg',
   '/path/to/selfie.jpg'
  );

  $user->verifyKYC($kycDocs);

  $this->assertTrue($user->hasKYCDocuments());
  $this->assertTrue($user->isActive());
 }

 public function test_can_suspend_user_account(): void
 {
  $user = User::create(
   new Email('user@example.com'),
   HashedPassword::fromPlainText('password123'),
   UserType::CLIENT()
  );

  $user->activate();
  $this->assertTrue($user->isActive());

  $user->suspend('Violation of terms');
  $this->assertTrue($user->isSuspended());
 }

 public function test_account_locks_after_three_failed_login_attempts(): void
 {
  $user = User::create(
   new Email('user@example.com'),
   HashedPassword::fromPlainText('password123'),
   UserType::CLIENT()
  );

  $this->assertFalse($user->isLocked());

  $user->recordFailedLoginAttempt();
  $this->assertFalse($user->isLocked());

  $user->recordFailedLoginAttempt();
  $this->assertFalse($user->isLocked());

  $user->recordFailedLoginAttempt();
  $this->assertTrue($user->isLocked());
 }

 public function test_can_verify_password(): void
 {
  $plainPassword = 'mySecurePassword123';
  $user = User::create(
   new Email('user@example.com'),
   HashedPassword::fromPlainText($plainPassword),
   UserType::CLIENT()
  );

  $this->assertTrue($user->verifyPassword($plainPassword));
  $this->assertFalse($user->verifyPassword('wrongPassword'));
 }

 public function test_failed_login_attempts_reset_after_successful_login(): void
 {
  $user = User::create(
   new Email('user@example.com'),
   HashedPassword::fromPlainText('password123'),
   UserType::CLIENT()
  );

  $user->recordFailedLoginAttempt();
  $user->recordFailedLoginAttempt();
  $this->assertEquals(2, $user->getFailedLoginAttempts());

  $user->resetFailedLoginAttempts();
  $this->assertEquals(0, $user->getFailedLoginAttempts());
 }
}
