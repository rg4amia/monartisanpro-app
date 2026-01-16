<?php

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\Models\ValueObjects\AuthToken;
use DateTime;
use InvalidArgumentException;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
 /**
  * Test AuthToken constructor with valid values
  */
 public function test_constructor_with_valid_values(): void
 {
  // Arrange
  $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoiMTIzIn0.signature';
  $expiresAt = new DateTime('+24 hours');

  // Act
  $authToken = new AuthToken($token, $expiresAt);

  // Assert
  $this->assertEquals($token, $authToken->getToken());
  $this->assertEquals($expiresAt, $authToken->getExpiresAt());
 }

 /**
  * Test AuthToken constructor rejects empty token
  */
 public function test_constructor_rejects_empty_token(): void
 {
  // Arrange
  $expiresAt = new DateTime('+24 hours');

  // Assert
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Token cannot be empty');

  // Act
  new AuthToken('', $expiresAt);
 }

 /**
  * Test AuthToken is not expired immediately after creation
  */
 public function test_token_is_not_expired_immediately(): void
 {
  // Arrange
  $token = 'valid.jwt.token';
  $expiresAt = new DateTime('+24 hours');

  // Act
  $authToken = new AuthToken($token, $expiresAt);

  // Assert
  $this->assertFalse($authToken->isExpired());
 }

 /**
  * Test AuthToken expiration detection
  */
 public function test_token_expiration_detection(): void
 {
  // Arrange
  $token = 'expired.jwt.token';
  $expiresAt = new DateTime('-1 hour'); // Already expired

  // Act
  $authToken = new AuthToken($token, $expiresAt);

  // Assert
  $this->assertTrue($authToken->isExpired());
 }

 /**
  * Test AuthToken toString returns token string
  */
 public function test_to_string_returns_token(): void
 {
  // Arrange
  $token = 'valid.jwt.token';
  $expiresAt = new DateTime('+24 hours');
  $authToken = new AuthToken($token, $expiresAt);

  // Act & Assert
  $this->assertEquals($token, $authToken->toString());
  $this->assertEquals($token, (string)$authToken);
 }
}
