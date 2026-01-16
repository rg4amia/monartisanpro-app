<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\Client\Client;
use App\Domain\Identity\Models\Fournisseur\Fournisseur;
use App\Domain\Identity\Models\ReferentZone\ReferentZone;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Infrastructure\Repositories\PostgresUserRepository;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostgresUserRepositoryTest extends TestCase
{
 use RefreshDatabase;

 private PostgresUserRepository $repository;

 protected function setUp(): void
 {
  parent::setUp();
  $this->repository = new PostgresUserRepository();
 }

 /** @test */
 public function it_can_save_and_retrieve_a_client()
 {
  // Arrange
  $client = Client::createClient(
   Email::fromString('client@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789')
  );

  // Act
  $this->repository->save($client);
  $retrieved = $this->repository->findById($client->getId());

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertInstanceOf(Client::class, $retrieved);
  $this->assertEquals($client->getId()->toString(), $retrieved->getId()->toString());
  $this->assertEquals($client->getEmail()->toString(), $retrieved->getEmail()->toString());
  $this->assertEquals($client->getPhoneNumber()->toString(), $retrieved->getPhoneNumber()->toString());
 }

 /** @test */
 public function it_can_save_and_retrieve_an_artisan()
 {
  // Arrange
  $artisan = Artisan::createArtisan(
   Email::fromString('artisan@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789'),
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083) // Abidjan coordinates
  );

  // Act
  $this->repository->save($artisan);
  $retrieved = $this->repository->findById($artisan->getId());

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertInstanceOf(Artisan::class, $retrieved);
  $this->assertEquals($artisan->getId()->toString(), $retrieved->getId()->toString());
  $this->assertEquals($artisan->getEmail()->toString(), $retrieved->getEmail()->toString());
  $this->assertEquals($artisan->getCategory()->toString(), $retrieved->getCategory()->toString());
  $this->assertEqualsWithDelta($artisan->getLocation()->getLatitude(), $retrieved->getLocation()->getLatitude(), 0.0001);
  $this->assertEqualsWithDelta($artisan->getLocation()->getLongitude(), $retrieved->getLocation()->getLongitude(), 0.0001);
 }

 /** @test */
 public function it_can_save_and_retrieve_a_fournisseur()
 {
  // Arrange
  $fournisseur = Fournisseur::createFournisseur(
   Email::fromString('supplier@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789'),
   'Matériaux Pro',
   new GPS_Coordinates(5.3600, -4.0083)
  );

  // Act
  $this->repository->save($fournisseur);
  $retrieved = $this->repository->findById($fournisseur->getId());

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertInstanceOf(Fournisseur::class, $retrieved);
  $this->assertEquals($fournisseur->getId()->toString(), $retrieved->getId()->toString());
  $this->assertEquals($fournisseur->getBusinessName(), $retrieved->getBusinessName());
  $this->assertEqualsWithDelta($fournisseur->getShopLocation()->getLatitude(), $retrieved->getShopLocation()->getLatitude(), 0.0001);
 }

 /** @test */
 public function it_can_save_and_retrieve_a_referent_zone()
 {
  // Arrange
  $referent = ReferentZone::createReferentZone(
   Email::fromString('referent@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789'),
   new GPS_Coordinates(5.3600, -4.0083),
   'Abidjan Nord'
  );

  // Act
  $this->repository->save($referent);
  $retrieved = $this->repository->findById($referent->getId());

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertInstanceOf(ReferentZone::class, $retrieved);
  $this->assertEquals($referent->getId()->toString(), $retrieved->getId()->toString());
  $this->assertEquals($referent->getZone(), $retrieved->getZone());
 }

 /** @test */
 public function it_can_find_user_by_email()
 {
  // Arrange
  $client = Client::createClient(
   Email::fromString('findme@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789')
  );
  $this->repository->save($client);

  // Act
  $retrieved = $this->repository->findByEmail(Email::fromString('findme@example.com'));

  // Assert
  $this->assertNotNull($retrieved);
  $this->assertEquals($client->getId()->toString(), $retrieved->getId()->toString());
 }

 /** @test */
 public function it_returns_null_when_user_not_found_by_id()
 {
  // Act
  $retrieved = $this->repository->findById(UserId::generate());

  // Assert
  $this->assertNull($retrieved);
 }

 /** @test */
 public function it_returns_null_when_user_not_found_by_email()
 {
  // Act
  $retrieved = $this->repository->findByEmail(Email::fromString('nonexistent@example.com'));

  // Assert
  $this->assertNull($retrieved);
 }

 /** @test */
 public function it_can_update_an_existing_user()
 {
  // Arrange
  $client = Client::createClient(
   Email::fromString('update@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789')
  );
  $this->repository->save($client);

  // Act - Update the client
  $client->suspend('Test suspension');
  $this->repository->save($client);

  // Assert
  $retrieved = $this->repository->findById($client->getId());
  $this->assertTrue($retrieved->isSuspended());
 }

 /** @test */
 public function it_can_save_and_retrieve_kyc_documents()
 {
  // Arrange
  $kycDocs = new KYCDocuments(
   'CNI',
   'CI123456789',
   'https://example.com/id.jpg',
   'https://example.com/selfie.jpg',
   new DateTime()
  );

  $artisan = Artisan::createArtisan(
   Email::fromString('kyc@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789'),
   TradeCategory::ELECTRICIAN(),
   new GPS_Coordinates(5.3600, -4.0083),
   $kycDocs
  );

  // Act
  $this->repository->save($artisan);
  $retrieved = $this->repository->findById($artisan->getId());

  // Assert
  $this->assertNotNull($retrieved->getKYCDocuments());
  $this->assertEquals('CNI', $retrieved->getKYCDocuments()->getIdType());
  $this->assertEquals('CI123456789', $retrieved->getKYCDocuments()->getIdNumber());
 }

 /** @test */
 public function it_can_find_artisans_near_location()
 {
  // Arrange - Create artisans at different locations
  $centerLocation = new GPS_Coordinates(5.3600, -4.0083); // Abidjan center

  // Artisan within 1km
  $nearbyArtisan = Artisan::createArtisan(
   Email::fromString('nearby@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789'),
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3650, -4.0083) // ~500m away
  );
  $nearbyArtisan->activate();
  $this->repository->save($nearbyArtisan);

  // Artisan far away (should not be in results for 1km radius)
  $farArtisan = Artisan::createArtisan(
   Email::fromString('far@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456790'),
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.4600, -4.0083) // ~10km away
  );
  $farArtisan->activate();
  $this->repository->save($farArtisan);

  // Act - Search within 1km radius
  $results = $this->repository->findArtisansNearLocation($centerLocation, 1.0);

  // Assert
  $this->assertCount(1, $results);
  $this->assertEquals($nearbyArtisan->getId()->toString(), $results[0]->getId()->toString());
 }

 /** @test */
 public function it_only_returns_active_artisans_in_proximity_search()
 {
  // Arrange
  $location = new GPS_Coordinates(5.3600, -4.0083);

  // Active artisan
  $activeArtisan = Artisan::createArtisan(
   Email::fromString('active@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789'),
   TradeCategory::MASON(),
   $location
  );
  $activeArtisan->activate();
  $this->repository->save($activeArtisan);

  // Pending artisan (should not appear in results)
  $pendingArtisan = Artisan::createArtisan(
   Email::fromString('pending@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456790'),
   TradeCategory::MASON(),
   $location
  );
  $this->repository->save($pendingArtisan);

  // Act
  $results = $this->repository->findArtisansNearLocation($location, 1.0);

  // Assert
  $this->assertCount(1, $results);
  $this->assertEquals($activeArtisan->getId()->toString(), $results[0]->getId()->toString());
 }

 /** @test */
 public function it_can_delete_a_user()
 {
  // Arrange
  $client = Client::createClient(
   Email::fromString('delete@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789')
  );
  $this->repository->save($client);

  // Act
  $this->repository->delete($client->getId());

  // Assert
  $retrieved = $this->repository->findById($client->getId());
  $this->assertNull($retrieved);
 }

 /** @test */
 public function it_preserves_failed_login_attempts_and_lock_status()
 {
  // Arrange
  $client = Client::createClient(
   Email::fromString('locked@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789')
  );

  // Simulate failed login attempts
  $client->recordFailedLoginAttempt();
  $client->recordFailedLoginAttempt();
  $client->recordFailedLoginAttempt(); // This should lock the account

  $this->repository->save($client);

  // Act
  $retrieved = $this->repository->findById($client->getId());

  // Assert
  $this->assertTrue($retrieved->isLocked());
  $this->assertEquals(3, $retrieved->getFailedLoginAttempts());
  $this->assertNotNull($retrieved->getLockedUntil());
 }

 /** @test */
 public function it_can_update_artisan_location()
 {
  // Arrange
  $artisan = Artisan::createArtisan(
   Email::fromString('move@example.com'),
   HashedPassword::fromPlainPassword('password123'),
   PhoneNumber::fromString('+2250123456789'),
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083)
  );
  $this->repository->save($artisan);

  // Act - Update location
  $newLocation = new GPS_Coordinates(5.4000, -4.0500);
  $artisan->updateLocation($newLocation);
  $this->repository->save($artisan);

  // Assert
  $retrieved = $this->repository->findById($artisan->getId());
  $this->assertEqualsWithDelta(5.4000, $retrieved->getLocation()->getLatitude(), 0.0001);
  $this->assertEqualsWithDelta(-4.0500, $retrieved->getLocation()->getLongitude(), 0.0001);
 }
}
