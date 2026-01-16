<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Domain\Identity\Models\Client\Client;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for authentication API endpoints
 *
 * Tests:
 * - User registration (Client, Artisan, Fournisseur)
 * - User login
 * - OTP generation and verification
 *
 * Requirements: 1.1, 1.2, 1.3, 1.6
 */
class AuthenticationTest extends TestCase
{
 use RefreshDatabase;

 /**
  * Test client registration
  */
 public function test_client_can_register(): void
 {
  $response = $this->postJson('/api/v1/auth/register', [
   'email' => 'client@example.com',
   'password' => 'password123',
   'password_confirmation' => 'password123',
   'user_type' => 'CLIENT',
   'phone_number' => '+22501234567890',
  ]);

  $response->assertStatus(201)
   ->assertJsonStructure([
    'message',
    'data' => [
     'user' => [
      'id',
      'email',
      'user_type',
      'account_status',
      'phone_number',
      'created_at',
     ],
     'token',
     'token_type',
    ],
   ])
   ->assertJson([
    'data' => [
     'user' => [
      'email' => 'client@example.com',
      'user_type' => 'CLIENT',
     ],
    ],
   ]);

  // Verify user was created in database
  $this->assertDatabaseHas('users', [
   'email' => 'client@example.com',
   'user_type' => 'CLIENT',
  ]);
 }

 /**
  * Test artisan registration
  */
 public function test_artisan_can_register(): void
 {
  $response = $this->postJson('/api/v1/auth/register', [
   'email' => 'artisan@example.com',
   'password' => 'password123',
   'password_confirmation' => 'password123',
   'user_type' => 'ARTISAN',
   'phone_number' => '+22501234567890',
   'trade_category' => 'PLUMBER',
   'location' => [
    'latitude' => 5.3600,
    'longitude' => -4.0083,
    'accuracy' => 10.0,
   ],
  ]);

  $response->assertStatus(201)
   ->assertJsonStructure([
    'message',
    'data' => [
     'user' => [
      'id',
      'email',
      'user_type',
      'trade_category',
      'location',
      'is_kyc_verified',
      'can_accept_missions',
     ],
     'token',
    ],
   ])
   ->assertJson([
    'data' => [
     'user' => [
      'email' => 'artisan@example.com',
      'user_type' => 'ARTISAN',
      'trade_category' => 'PLUMBER',
      'is_kyc_verified' => false,
      'can_accept_missions' => false,
     ],
    ],
   ]);
 }

 /**
  * Test fournisseur registration
  */
 public function test_fournisseur_can_register(): void
 {
  $response = $this->postJson('/api/v1/auth/register', [
   'email' => 'fournisseur@example.com',
   'password' => 'password123',
   'password_confirmation' => 'password123',
   'user_type' => 'FOURNISSEUR',
   'phone_number' => '+22501234567890',
   'business_name' => 'Matériaux Pro',
   'shop_location' => [
    'latitude' => 5.3600,
    'longitude' => -4.0083,
    'accuracy' => 10.0,
   ],
  ]);

  $response->assertStatus(201)
   ->assertJsonStructure([
    'message',
    'data' => [
     'user' => [
      'id',
      'email',
      'user_type',
      'business_name',
      'shop_location',
      'is_kyc_verified',
     ],
     'token',
    ],
   ])
   ->assertJson([
    'data' => [
     'user' => [
      'email' => 'fournisseur@example.com',
      'user_type' => 'FOURNISSEUR',
      'business_name' => 'Matériaux Pro',
     ],
    ],
   ]);
 }

 /**
  * Test registration with duplicate email fails
  */
 public function test_registration_with_duplicate_email_fails(): void
 {
  // Create first user
  $this->postJson('/api/v1/auth/register', [
   'email' => 'duplicate@example.com',
   'password' => 'password123',
   'password_confirmation' => 'password123',
   'user_type' => 'CLIENT',
   'phone_number' => '+22501234567890',
  ]);

  // Try to create second user with same email
  $response = $this->postJson('/api/v1/auth/register', [
   'email' => 'duplicate@example.com',
   'password' => 'password123',
   'password_confirmation' => 'password123',
   'user_type' => 'CLIENT',
   'phone_number' => '+22509876543210',
  ]);

  $response->assertStatus(422)
   ->assertJsonValidationErrors(['email']);
 }

 /**
  * Test user can login with valid credentials
  */
 public function test_user_can_login_with_valid_credentials(): void
 {
  // Create a user
  $userRepository = app(UserRepository::class);
  $user = Client::create(
   email: Email::fromString('login@example.com'),
   password: HashedPassword::fromPlainText('password123'),
   phoneNumber: PhoneNumber::fromString('+22501234567890')
  );
  $userRepository->save($user);

  // Attempt login
  $response = $this->postJson('/api/v1/auth/login', [
   'email' => 'login@example.com',
   'password' => 'password123',
  ]);

  $response->assertStatus(200)
   ->assertJsonStructure([
    'message',
    'data' => [
     'user' => [
      'id',
      'email',
      'user_type',
     ],
     'token',
     'token_type',
    ],
   ])
   ->assertJson([
    'data' => [
     'user' => [
      'email' => 'login@example.com',
     ],
    ],
   ]);
 }

 /**
  * Test login with invalid credentials fails
  */
 public function test_login_with_invalid_credentials_fails(): void
 {
  // Create a user
  $userRepository = app(UserRepository::class);
  $user = Client::create(
   email: Email::fromString('test@example.com'),
   password: HashedPassword::fromPlainText('password123'),
   phoneNumber: PhoneNumber::fromString('+22501234567890')
  );
  $userRepository->save($user);

  // Attempt login with wrong password
  $response = $this->postJson('/api/v1/auth/login', [
   'email' => 'test@example.com',
   'password' => 'wrongpassword',
  ]);

  $response->assertStatus(401)
   ->assertJson([
    'error' => 'INVALID_CREDENTIALS',
   ]);
 }

 /**
  * Test OTP generation
  */
 public function test_otp_can_be_generated(): void
 {
  $response = $this->postJson('/api/v1/auth/otp/generate', [
   'phone_number' => '+22501234567890',
  ]);

  $response->assertStatus(200)
   ->assertJsonStructure([
    'message',
    'data' => [
     'phone_number',
     'expires_at',
    ],
   ])
   ->assertJson([
    'data' => [
     'phone_number' => '+22501234567890',
    ],
   ]);
 }

 /**
  * Test OTP verification with invalid code fails
  */
 public function test_otp_verification_with_invalid_code_fails(): void
 {
  // Generate OTP first
  $this->postJson('/api/v1/auth/otp/generate', [
   'phone_number' => '+22501234567890',
  ]);

  // Try to verify with wrong code
  $response = $this->postJson('/api/v1/auth/otp/verify', [
   'phone_number' => '+22501234567890',
   'code' => '000000',
  ]);

  $response->assertStatus(400)
   ->assertJson([
    'error' => 'INVALID_OTP',
   ]);
 }

 /**
  * Test registration validation errors
  */
 public function test_registration_requires_all_fields(): void
 {
  $response = $this->postJson('/api/v1/auth/register', []);

  $response->assertStatus(422)
   ->assertJsonValidationErrors(['email', 'password', 'user_type', 'phone_number']);
 }

 /**
  * Test artisan registration requires trade category and location
  */
 public function test_artisan_registration_requires_trade_category_and_location(): void
 {
  $response = $this->postJson('/api/v1/auth/register', [
   'email' => 'artisan@example.com',
   'password' => 'password123',
   'password_confirmation' => 'password123',
   'user_type' => 'ARTISAN',
   'phone_number' => '+22501234567890',
  ]);

  $response->assertStatus(422)
   ->assertJsonValidationErrors(['trade_category', 'location']);
 }

 /**
  * Test fournisseur registration requires business name and shop location
  */
 public function test_fournisseur_registration_requires_business_name_and_shop_location(): void
 {
  $response = $this->postJson('/api/v1/auth/register', [
   'email' => 'fournisseur@example.com',
   'password' => 'password123',
   'password_confirmation' => 'password123',
   'user_type' => 'FOURNISSEUR',
   'phone_number' => '+22501234567890',
  ]);

  $response->assertStatus(422)
   ->assertJsonValidationErrors(['business_name', 'shop_location']);
 }
}
