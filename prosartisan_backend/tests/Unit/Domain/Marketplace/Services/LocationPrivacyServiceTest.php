<?php

namespace Tests\Unit\Domain\Marketplace\Services;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\Devis\Devis;
use App\Domain\Marketplace\Models\Devis\DevisLine;
use App\Domain\Marketplace\Models\Mission\Mission;
use App\Domain\Marketplace\Models\ValueObjects\DevisLineType;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Marketplace\Repositories\MissionRepository;
use App\Domain\Marketplace\Services\DefaultLocationPrivacyService;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LocationPrivacyService
 *
 * Tests GPS coordinate blurring for privacy protection
 * and controlled revelation after quote acceptance
 *
 * Requirements: 2.4, 3.5
 */
class LocationPrivacyServiceTest extends TestCase
{
 private MissionRepository $mockRepository;
 private DefaultLocationPrivacyService $service;

 protected function setUp(): void
 {
  parent::setUp();

  $this->mockRepository = $this->createMock(MissionRepository::class);
  $this->service = new DefaultLocationPrivacyService($this->mockRepository);
 }

 /**
  * Test that blurCoordinates returns coordinates within specified radius
  * Requirement 2.4: Apply 50m blur to GPS coordinates
  */
 public function test_blur_coordinates_returns_coordinates_within_radius(): void
 {
  $originalCoords = new GPS_Coordinates(5.3600, -4.0083); // Abidjan
  $blurRadius = 50; // meters

  $blurredCoords = $this->service->blurCoordinates($originalCoords, $blurRadius);

  // Calculate distance between original and blurred coordinates
  $distance = $originalCoords->distanceTo($blurredCoords);

  // Blurred coordinates should be within the specified radius
  $this->assertLessThanOrEqual(
   $blurRadius,
   $distance,
   "Blurred coordinates should be within {$blurRadius}m of original"
  );
 }

 /**
  * Test that blurCoordinates uses default 50m radius when not specified
  * Requirement 2.4: Default blur radius is 50m
  */
 public function test_blur_coordinates_uses_default_50m_radius(): void
 {
  $originalCoords = new GPS_Coordinates(5.3600, -4.0083);

  $blurredCoords = $this->service->blurCoordinates($originalCoords);

  $distance = $originalCoords->distanceTo($blurredCoords);

  // Should be within default 50m radius
  $this->assertLessThanOrEqual(50, $distance);
 }

 /**
  * Test that blurCoordinates produces different results on multiple calls
  * (randomness check)
  */
 public function test_blur_coordinates_produces_random_results(): void
 {
  $originalCoords = new GPS_Coordinates(5.3600, -4.0083);

  $blurred1 = $this->service->blurCoordinates($originalCoords);
  $blurred2 = $this->service->blurCoordinates($originalCoords);
  $blurred3 = $this->service->blurCoordinates($originalCoords);

  // At least one should be different (very high probability with random blur)
  $allSame = $blurred1->equals($blurred2) && $blurred2->equals($blurred3);

  $this->assertFalse(
   $allSame,
   'Blurred coordinates should vary due to randomness'
  );
 }

 /**
  * Test that blurCoordinates rejects negative radius
  */
 public function test_blur_coordinates_rejects_negative_radius(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Blur radius must be positive');

  $coords = new GPS_Coordinates(5.3600, -4.0083);
  $this->service->blurCoordinates($coords, -10);
 }

 /**
  * Test that blurCoordinates rejects zero radius
  */
 public function test_blur_coordinates_rejects_zero_radius(): void
 {
  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage('Blur radius must be positive');

  $coords = new GPS_Coordinates(5.3600, -4.0083);
  $this->service->blurCoordinates($coords, 0);
 }

 /**
  * Test that blurCoordinates works with different radius values
  */
 public function test_blur_coordinates_respects_custom_radius(): void
 {
  $originalCoords = new GPS_Coordinates(5.3600, -4.0083);
  $customRadius = 100; // meters

  $blurredCoords = $this->service->blurCoordinates($originalCoords, $customRadius);

  $distance = $originalCoords->distanceTo($blurredCoords);

  $this->assertLessThanOrEqual($customRadius, $distance);
 }

 /**
  * Test that revealExactLocation returns exact coordinates for authorized artisan
  * Requirement 3.5: Reveal exact coordinates after quote acceptance
  */
 public function test_reveal_exact_location_returns_exact_coordinates_for_authorized_artisan(): void
 {
  $missionId = MissionId::generate();
  $artisanId = UserId::generate();
  $clientId = UserId::generate();
  $exactLocation = new GPS_Coordinates(5.3600, -4.0083);

  // Create a mission with an accepted quote from the artisan
  $mission = $this->createMissionWithAcceptedQuote($missionId, $clientId, $artisanId, $exactLocation);

  $this->mockRepository
   ->expects($this->once())
   ->method('findById')
   ->with($missionId)
   ->willReturn($mission);

  $revealedLocation = $this->service->revealExactLocation($missionId, $artisanId);

  // Should return exact coordinates
  $this->assertTrue($exactLocation->equals($revealedLocation));
 }

 /**
  * Test that revealExactLocation throws exception for non-existent mission
  */
 public function test_reveal_exact_location_throws_exception_for_non_existent_mission(): void
 {
  $missionId = MissionId::generate();
  $artisanId = UserId::generate();

  $this->mockRepository
   ->expects($this->once())
   ->method('findById')
   ->with($missionId)
   ->willReturn(null);

  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage("Mission {$missionId->getValue()} not found");

  $this->service->revealExactLocation($missionId, $artisanId);
 }

 /**
  * Test that revealExactLocation throws exception when no quote is accepted
  */
 public function test_reveal_exact_location_throws_exception_when_no_quote_accepted(): void
 {
  $missionId = MissionId::generate();
  $artisanId = UserId::generate();
  $clientId = UserId::generate();
  $location = new GPS_Coordinates(5.3600, -4.0083);

  // Create mission without accepted quote
  $mission = $this->createMissionWithoutAcceptedQuote($missionId, $clientId, $location);

  $this->mockRepository
   ->expects($this->once())
   ->method('findById')
   ->with($missionId)
   ->willReturn($mission);

  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage("No accepted quote found for mission {$missionId->getValue()}");

  $this->service->revealExactLocation($missionId, $artisanId);
 }

 /**
  * Test that revealExactLocation throws exception for unauthorized artisan
  * Requirement 3.5: Only artisan with accepted quote can view exact location
  */
 public function test_reveal_exact_location_throws_exception_for_unauthorized_artisan(): void
 {
  $missionId = MissionId::generate();
  $authorizedArtisanId = UserId::generate();
  $unauthorizedArtisanId = UserId::generate();
  $clientId = UserId::generate();
  $location = new GPS_Coordinates(5.3600, -4.0083);

  // Create mission with accepted quote from authorized artisan
  $mission = $this->createMissionWithAcceptedQuote($missionId, $clientId, $authorizedArtisanId, $location);

  $this->mockRepository
   ->expects($this->once())
   ->method('findById')
   ->with($missionId)
   ->willReturn($mission);

  $this->expectException(InvalidArgumentException::class);
  $this->expectExceptionMessage(
   "Artisan {$unauthorizedArtisanId->getValue()} is not authorized to view exact location"
  );

  // Try to reveal location with different artisan ID
  $this->service->revealExactLocation($missionId, $unauthorizedArtisanId);
 }

 /**
  * Test that blurred and exact coordinates are different
  */
 public function test_blurred_and_exact_coordinates_are_different(): void
 {
  $exactCoords = new GPS_Coordinates(5.3600, -4.0083);
  $blurredCoords = $this->service->blurCoordinates($exactCoords);

  $this->assertFalse(
   $exactCoords->equals($blurredCoords),
   'Blurred coordinates should differ from exact coordinates'
  );
 }

 /**
  * Test that blurring preserves coordinate validity
  */
 public function test_blur_preserves_coordinate_validity(): void
 {
  // Test with edge case coordinates
  $northPole = new GPS_Coordinates(89.0, 0.0);
  $southPole = new GPS_Coordinates(-89.0, 0.0);
  $dateLine = new GPS_Coordinates(0.0, 179.0);

  // Should not throw exceptions
  $blurredNorth = $this->service->blurCoordinates($northPole);
  $blurredSouth = $this->service->blurCoordinates($southPole);
  $blurredDate = $this->service->blurCoordinates($dateLine);

  // Verify they are valid GPS_Coordinates objects
  $this->assertInstanceOf(GPS_Coordinates::class, $blurredNorth);
  $this->assertInstanceOf(GPS_Coordinates::class, $blurredSouth);
  $this->assertInstanceOf(GPS_Coordinates::class, $blurredDate);
 }

 /**
  * Helper method to create a mission with an accepted quote
  */
 private function createMissionWithAcceptedQuote(
  MissionId $missionId,
  UserId $clientId,
  UserId $artisanId,
  GPS_Coordinates $location
 ): Mission {
  // Create a real mission
  $mission = new Mission(
   $missionId,
   $clientId,
   'Test mission description',
   TradeCategory::PLUMBER(),
   $location,
   MoneyAmount::fromCentimes(100000), // 1000 XOF
   MoneyAmount::fromCentimes(500000)  // 5000 XOF
  );

  // Create a devis with line items
  $lineItems = [
   new DevisLine(
    'Materials',
    1,
    MoneyAmount::fromCentimes(200000),
    DevisLineType::MATERIAL()
   ),
   new DevisLine(
    'Labor',
    1,
    MoneyAmount::fromCentimes(100000),
    DevisLineType::LABOR()
   ),
  ];

  $devis = Devis::create($missionId, $artisanId, $lineItems);

  // Add the devis to the mission and accept it
  $mission->addQuote($devis);
  $mission->acceptQuote($devis->getId());

  return $mission;
 }

 /**
  * Helper method to create a mission without accepted quote
  */
 private function createMissionWithoutAcceptedQuote(
  MissionId $missionId,
  UserId $clientId,
  GPS_Coordinates $location
 ): Mission {
  // Create a real mission without any quotes
  return new Mission(
   $missionId,
   $clientId,
   'Test mission description',
   TradeCategory::PLUMBER(),
   $location,
   MoneyAmount::fromCentimes(100000),
   MoneyAmount::fromCentimes(500000)
  );
 }
}
