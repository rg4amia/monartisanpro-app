<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\Mission\Mission;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Infrastructure\Repositories\PostgresMissionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresMissionRepositoryTest extends TestCase
{
 use RefreshDatabase;

 private PostgresMissionRepository $repository;

 protected function setUp(): void
 {
  parent::setUp();
  $this->repository = new PostgresMissionRepository();
 }

 public function test_saves_and_finds_mission_by_id(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Réparation de plomberie urgente',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $this->repository->save($mission);

  $foundMission = $this->repository->findById($mission->getId());

  $this->assertNotNull($foundMission);
  $this->assertEquals($mission->getId()->getValue(), $foundMission->getId()->getValue());
  $this->assertEquals($mission->getDescription(), $foundMission->getDescription());
  $this->assertEquals($mission->getCategory()->getValue(), $foundMission->getCategory()->getValue());
  $this->assertEquals($mission->getBudgetMin()->toCentimes(), $foundMission->getBudgetMin()->toCentimes());
  $this->assertEquals($mission->getBudgetMax()->toCentimes(), $foundMission->getBudgetMax()->toCentimes());
 }

 public function test_returns_null_for_non_existent_mission(): void
 {
  $nonExistentId = MissionId::generate();

  $result = $this->repository->findById($nonExistentId);

  $this->assertNull($result);
 }

 public function test_finds_missions_by_client_id(): void
 {
  $clientId = UserId::generate();
  $otherClientId = UserId::generate();

  // Create missions for the client
  $mission1 = Mission::create(
   $clientId,
   'Mission 1',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $mission2 = Mission::create(
   $clientId,
   'Mission 2',
   TradeCategory::ELECTRICIAN(),
   new GPS_Coordinates(5.3610, -4.0083),
   MoneyAmount::fromFrancs(30000),
   MoneyAmount::fromFrancs(80000)
  );

  // Create mission for different client
  $otherMission = Mission::create(
   $otherClientId,
   'Other mission',
   TradeCategory::MASON(),
   new GPS_Coordinates(5.3620, -4.0083),
   MoneyAmount::fromFrancs(40000),
   MoneyAmount::fromFrancs(90000)
  );

  $this->repository->save($mission1);
  $this->repository->save($mission2);
  $this->repository->save($otherMission);

  $clientMissions = $this->repository->findByClientId($clientId);

  $this->assertCount(2, $clientMissions);
  $this->assertEquals($mission2->getId()->getValue(), $clientMissions[0]->getId()->getValue()); // Most recent first
  $this->assertEquals($mission1->getId()->getValue(), $clientMissions[1]->getId()->getValue());
 }

 public function test_finds_open_missions_near_location(): void
 {
  $centerLocation = new GPS_Coordinates(5.3600, -4.0083); // Abidjan center

  // Create nearby mission (within 1km)
  $nearbyMission = Mission::create(
   UserId::generate(),
   'Nearby mission',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3610, -4.0083), // ~1.1km away
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  // Create far mission (beyond search radius)
  $farMission = Mission::create(
   UserId::generate(),
   'Far mission',
   TradeCategory::ELECTRICIAN(),
   new GPS_Coordinates(5.4000, -4.0083), // ~4.4km away
   MoneyAmount::fromFrancs(30000),
   MoneyAmount::fromFrancs(80000)
  );

  $this->repository->save($nearbyMission);
  $this->repository->save($farMission);

  // Search within 2km radius
  $nearbyMissions = $this->repository->findOpenMissionsNearLocation($centerLocation, 2.0);

  $this->assertCount(1, $nearbyMissions);
  $this->assertEquals($nearbyMission->getId()->getValue(), $nearbyMissions[0]->getId()->getValue());
 }

 public function test_updates_existing_mission(): void
 {
  $mission = Mission::create(
   UserId::generate(),
   'Original description',
   TradeCategory::PLUMBER(),
   new GPS_Coordinates(5.3600, -4.0083),
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $this->repository->save($mission);

  // Verify initial save
  $this->assertDatabaseHas('missions', [
   'id' => $mission->getId()->getValue(),
   'description' => 'Original description',
  ]);

  // Update mission (simulate domain changes)
  $updatedMission = new Mission(
   $mission->getId(),
   $mission->getClientId(),
   'Updated description',
   $mission->getCategory(),
   $mission->getLocation(),
   $mission->getBudgetMin(),
   $mission->getBudgetMax(),
   $mission->getStatus(),
   [],
   $mission->getCreatedAt()
  );

  $this->repository->save($updatedMission);

  // Verify update
  $this->assertDatabaseHas('missions', [
   'id' => $mission->getId()->getValue(),
   'description' => 'Updated description',
  ]);

  // Should still be only one record
  $this->assertEquals(1, DB::table('missions')->where('id', $mission->getId()->getValue())->count());
 }

 public function test_stores_location_as_postgis_geography(): void
 {
  $location = new GPS_Coordinates(5.3600, -4.0083);
  $mission = Mission::create(
   UserId::generate(),
   'Test mission',
   TradeCategory::PLUMBER(),
   $location,
   MoneyAmount::fromFrancs(50000),
   MoneyAmount::fromFrancs(100000)
  );

  $this->repository->save($mission);

  // Verify PostGIS geography is stored correctly
  $result = DB::select(
   "SELECT ST_X(location) as longitude, ST_Y(location) as latitude FROM missions WHERE id = ?",
   [$mission->getId()->getValue()]
  );

  $this->assertCount(1, $result);
  $this->assertEquals($location->getLongitude(), $result[0]->longitude, '', 0.0001);
  $this->assertEquals($location->getLatitude(), $result[0]->latitude, '', 0.0001);
 }
}
