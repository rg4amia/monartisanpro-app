<?php

namespace Tests\Unit\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\AccountLockedException;
use App\Domain\Identity\Exceptions\AccountSuspendedException;
use App\Domain\Identity\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Exceptions\InvalidTokenException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\Services\LaravelAuthenticationService;
use App\Domain\Identity\Services\LogSMSService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
 private LaravelAuthenticationService $authService;
 private UserRepository $userRepository;

 protected function setUp(): void
 {
  parent::setUp();

  // Create mock repository
  $this->userRepository = $this->createMock(UserRepository::class);

  // Create service with mock repository
  $this->authService = new LaravelAuthenticationService(
   $this->userRepository,
   new LogSMSService()
  );
 }

 /**
  * Test successful authentication generates valid token
  *
  * Requirements: 1.3
  */
 public function test_authenticate_with_valid_credentials_returns_token(): void
 {
  // Arrange
  $email = new Email('test@example.com');
  $password = 'SecurePassword123!';
  $hashedPassword = HashedPassword::fromPlainText($password);

  $user = new User(
   UserId::generate(),
   $email,
   $hashedPassword,
   UserType::CLIENT(),
   AccountStatus::ACTIVE()
  );

  $this->userRepository
   ->expects($this->once())
   ->method('findByEmail')
   ->with($email)
   ->willReturn($user);

  $this->userRepository
   ->expects($this->once())
   ->method('save')
   ->with($user);

  // Act
  $token = $this->authService->authenticate($email, $password);

  // Assert
  $this->assertNotNull($token);
  $this->assertNotEmpty($token->getToken());
  $this->assertFalse($token->isExpired());
 }

 /**
  * Test authentication with invalid email fails
  *
  * Requirements: 1.3
  */
 public function test_authenticate_with_invalid_email_throws_exception(): void
 {
  // Arrange
  $email = new Email('nonexistent@example.com');
  $password = 'password';

  $this->userRepository
   ->expects($this->once())
   ->method('findByEmail')
   ->with($email)
   ->willReturn(null);

  // Assert
  $this->expectException(InvalidCredentialsException::class);

  // Act
  $this->authService->authenticate($email, $password);
 }

 /**
  * Test authentication with wrong password fails
  *
  * Requirements: 1.3
  */
 public function test_authenticate_with_wrong_password_throws_exception(): void
 {
  // Arrange
  $email = new Email('test@example.com');
  $correctPassword = 'CorrectPassword123!';
  $wrongPassword = 'WrongPassword';
  $hashedPassword = HashedPassword::fromPlainText($correctPassword);

  $user = new User(
   UserId::generate(),
   $email,
   $hashedPassword,
   UserType::CLIENT(),
   AccountStatus::ACTIVE()
  );

  $this->userRepository
   ->expects($this->once())
   ->method('findByEmail')
   ->with($email)
   ->willReturn($user);

  $this->userRepository
   ->expects($this->once())
   ->method('save')
   ->with($user);

  // Assert
  $this->expectException(InvalidCredentialsException::class);

  // Act
  $this->authService->authenticate($email, $wrongPassword);

  // Verify failed attempt was recorded
  $this->assertEquals(1, $user->getFailedLoginAttempts());
 }

 /**
  * Test account lockout after 3 failed attempts
  *
  * Requirements: 1.5
  */
 public function test_account_locks_after_three_failed_attempts(): void
 {
  // Arrange
  $email = new Email('test@example.com');
  $correctPassword = 'CorrectPassword123!';
  $wrongPassword = 'WrongPassword';
  $hashedPassword = HashedPassword::fromPlainText($correctPassword);

  $user = new User(
   UserId::generate(),
   $email,
   $hashedPassword,
   UserType::CLIENT(),
   AccountStatus::ACTIVE()
  );

  $this->userRepository
   ->method('findByEmail')
   ->with($email)
   ->willReturn($user);

  $this->userRepository
   ->method('save')
   ->with($user);

  // Act - Attempt 3 failed logins
  for ($i = 0; $i < 3; $i++) {
   try {
    $this->authService->authenticate($email, $wrongPassword);
   } catch (InvalidCredentialsException $e) {
    // Expected
   }
  }

  // Assert - Account should be locked
  $this->assertTrue($user->isLocked());
  $this->assertNotNull($user->getLockedUntil());

  // Try to authenticate with correct password - should fail due to lock
  $this->expectException(AccountLockedException::class);
  $this->authService->authenticate($email, $correctPassword);
 }

 /**
  * Test authentication with suspended account fails
  *
  * Requirements: 1.3
  */
 public function test_authenticate_with_suspended_account_throws_exception(): void
 {
  // Arrange
  $email = new Email('test@example.com');
  $password = 'SecurePassword123!';
  $hashedPassword = HashedPassword::fromPlainText($password);

  $user = new User(
   UserId::generate(),
   $email,
   $hashedPassword,
   UserType::CLIENT(),
   AccountStatus::SUSPENDED()
  );

  $this->userRepository
   ->expects($this->once())
   ->method('findByEmail')
   ->with($email)
   ->willReturn($user);

  // Assert
  $this->expectException(AccountSuspendedException::class);

  // Act
  $this->authService->authenticate($email, $password);
 }

 /**
  * Test OTP generation creates valid 6-digit code
  *
  * Requirements: 1.6
  */
 public function test_generate_otp_creates_valid_code(): void
 {
  // Arrange
  $phone = new PhoneNumber('+2250123456789');

  // Act
  $otp = $this->authService->generateOTP($phone);

  // Assert
  $this->assertNotNull($otp);
  $this->assertMatchesRegularExpression('/^\d{6}$/', $otp->getCode());
  $this->assertFalse($otp->isExpired());
  $this->assertEquals($phone->toString(), $otp->getPhoneNumber()->toString());
 }

 /**
  * Test OTP verification with correct code succeeds
  *
  * Requirements: 1.6
  */
 public function test_verify_otp_with_correct_code_returns_true(): void
 {
  // Arrange
  $phone = new PhoneNumber('+2250123456789');
  $otp = $this->authService->generateOTP($phone);

  // Act
  $result = $this->authService->verifyOTP($phone, $otp->getCode());

  // Assert
  $this->assertTrue($result);
 }

 /**
  * Test OTP verification with wrong code fails
  *
  * Requirements: 1.6
  */
 public function test_verify_otp_with_wrong_code_returns_false(): void
 {
  // Arrange
  $phone = new PhoneNumber('+2250123456789');
  $this->authService->generateOTP($phone);

  // Act
  $result = $this->authService->verifyOTP($phone, '000000');

  // Assert
  $this->assertFalse($result);
 }

 /**
  * Test OTP verification with non-existent phone fails
  *
  * Requirements: 1.6
  */
 public function test_verify_otp_with_nonexistent_phone_returns_false(): void
 {
  // Arrange
  $phone = new PhoneNumber('+2250123456789');

  // Act - No OTP generated
  $result = $this->authService->verifyOTP($phone, '123456');

  // Assert
  $this->assertFalse($result);
 }

 /**
  * Test token generation includes user information
  *
  * Requirements: 1.3
  */
 public function test_generate_token_includes_user_information(): void
 {
  // Arrange
  $user = new User(
   UserId::generate(),
   new Email('test@example.com'),
   HashedPassword::fromPlainText('password'),
   UserType::ARTISAN(),
   AccountStatus::ACTIVE()
  );

  // Act
  $token = $this->authService->generateToken($user);

  // Assert
  $this->assertNotNull($token);
  $this->assertNotEmpty($token->getToken());

  // Verify token contains user ID
  $userId = $this->authService->verifyToken($token->getToken());
  $this->assertEquals($user->getId()->toString(), $userId);
 }

 /**
  * Test token verification with invalid token fails
  *
  * Requirements: 1.3
  */
 public function test_verify_token_with_invalid_token_throws_exception(): void
 {
  // Assert
  $this->expectException(InvalidTokenException::class);

  // Act
  $this->authService->verifyToken('invalid.token.here');
 }

 /**
  * Test token refresh generates new token
  *
  * Requirements: 1.3
  */
 public function test_refresh_token_generates_new_token(): void
 {
  // Arrange
  $user = new User(
   UserId::generate(),
   new Email('test@example.com'),
   HashedPassword::fromPlainText('password'),
   UserType::CLIENT(),
   AccountStatus::ACTIVE()
  );

  $this->userRepository
   ->expects($this->once())
   ->method('findById')
   ->willReturn($user);

  $originalToken = $this->authService->generateToken($user);

  // Act
  $newToken = $this->authService->refreshToken($originalToken->getToken());

  // Assert
  $this->assertNotNull($newToken);
  $this->assertNotEquals($originalToken->getToken(), $newToken->getToken());
 }

 /**
  * Test successful login resets failed attempts counter
  *
  * Requirements: 1.5
  */
 public function test_successful_login_resets_failed_attempts(): void
 {
  // Arrange
  $email = new Email('test@example.com');
  $password = 'SecurePassword123!';
  $hashedPassword = HashedPassword::fromPlainText($password);

  $user = new User(
   UserId::generate(),
   $email,
   $hashedPassword,
   UserType::CLIENT(),
   AccountStatus::ACTIVE()
  );

  // Simulate previous failed attempts
  $user->recordFailedLoginAttempt();
  $user->recordFailedLoginAttempt();
  $this->assertEquals(2, $user->getFailedLoginAttempts());

  $this->userRepository
   ->expects($this->once())
   ->method('findByEmail')
   ->with($email)
   ->willReturn($user);

  $this->userRepository
   ->expects($this->once())
   ->method('save')
   ->with($user);

  // Act
  $this->authService->authenticate($email, $password);

  // Assert
  $this->assertEquals(0, $user->getFailedLoginAttempts());
 }

 protected function tearDown(): void
 {
  Cache::flush();
  parent::tearDown();
 }
}
