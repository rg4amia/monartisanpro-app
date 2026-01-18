<?php

namespace Tests\Unit\Application\Marketplace;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Repositories\MissionRepository;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-based tests for mission list pagination
 *
 * **Property 82: Mission List Pagination**
 * **Validates: Requirements 17.2**
 */
class MissionListPaginationPropertyTest extends TestCase
{
 use RefreshDatabase;

 private MissionRepository $missionRepository;

 protected function setUp(): void
 {
  parent::setUp();
  $this->missionRepository = app(MissionRepository::class);
 }

 /**
  * Property test: Mission list pagination should maintain consistency across pages
  *
  * This property tests that:
  * 1. Total count remains consistent across pagination requests
  * 2. No missions are duplicated across pages
  * 3. No missions are missing when combining all pages
  * 4. Page size is respected (20 items per page as per requirement 17.2)
  */
 public function test_mission_list_pagination_consistency_property()
 {
  // Test with different numbers of missions (property-based approach)
  $testCases = [
   5,   // Less than one page
   20,  // Exactly one page
   25,  // Just over one page
   40,  // Exactly two pages
   45,  // Just over two pages
   100, // Multiple pages
  ];

  foreach ($testCases as $totalMissions) {
   $this->refreshDatabase();

   // Create test client
   $clientId = UserId::generate();
   $this->createTestUser($clientId, 'CLIENT');

   // Create test missions
   $createdMissionIds = [];
   for ($i = 0; $i < $totalMissions; $i++) {
    $missionId = $this->createTestMission($clientId, "Mission {$i}");
    $createdMissionIds[] = $missionId;
   }

   // Test pagination consistency
   $this->assertPaginationConsistency($clientId, $createdMissionIds, $totalMissions);
  }
 }

 /**
  * Property test: Mission pagination should handle edge cases correctly
  */
 public function test_mission_pagination_edge_cases_property()
 {
  $clientId = UserId::generate();
  $this->createTestUser($clientId, 'CLIENT');

  // Test empty result set
  $result = $this->missionRepository->findByClientIdPaginated($clientId, 20, 0);
  $this->assertEquals(0, $result['total']);
  $this->assertEmpty($result['missions']);

  // Test with various page sizes and offsets
  $missionCount = 50;
  for ($i = 0; $i < $missionCount; $i++) {
   $this->createTestMission($clientId, "Mission {$i}");
  }

  // Test different page sizes
  $pageSizes = [1, 5, 10, 20, 25, 50, 100];
  foreach ($pageSizes as $pageSize) {
   $result = $this->missionRepository->findByClientIdPaginated($clientId, $pageSize, 0);

   // Property: Total should always be consistent
   $this->assertEquals($missionCount, $result['total']);

   // Property: Returned items should not exceed page size
   $this->assertLessThanOrEqual($pageSize, count($result['missions']));

   // Property: If total > page size, should return exactly page size items
   if ($missionCount > $pageSize) {
    $this->assertEquals($pageSize, count($result['missions']));
   } else {
    $this->assertEquals($missionCount, count($result['missions']));
   }
  }
 }

 /**
  * Property test: Location-based mission pagination should maintain spatial consistency
  */
 public function test_location_based_pagination_property()
 {
  // Test location-based pagination with different scenarios
  $testLocations = [
   new GPS_Coordinates(5.3600, -4.0083), // Abidjan
   new GPS_Coordinates(7.7500, -5.0167), // Bouaké
   new GPS_Coordinates(9.4500, -5.5167), // Korhogo
  ];

  foreach ($testLocations as $searchLocation) {
   $this->refreshDatabase();

   // Create missions at various distances from search location
   $missionCount = 30;
   for ($i = 0; $i < $missionCount; $i++) {
    $clientId = UserId::generate();
    $this->createTestUser($clientId, 'CLIENT');

    // Create missions with slight location variations
    $lat = $searchLocation->getLatitude() + (rand(-100, 100) / 10000); // ±0.01 degrees
    $lng = $searchLocation->getLongitude() + (rand(-100, 100) / 10000);
    $missionLocation = new GPS_Coordinates($lat, $lng);

    $this->createTestMissionAtLocation($clientId, "Mission {$i}", $missionLocation);
   }

   // Test pagination consistency for location-based search
   $radiusKm = 10.0;
   $pageSize = 20;

   // Get first page
   $firstPage = $this->missionRepository->findOpenMissionsNearLocationPaginated(
    $searchLocation,
    $radiusKm,
    $pageSize,
    0
   );

   // Get second page if there are enough missions
   if ($firstPage['total'] > $pageSize) {
    $secondPage = $this->missionRepository->findOpenMissionsNearLocationPaginated(
     $searchLocation,
     $radiusKm,
     $pageSize,
     $pageSize
    );

    // Property: No overlap between pages
    $firstPageIds = array_map(fn($m) => $m->getId()->toString(), $firstPage['missions']);
    $secondPageIds = array_map(fn($m) => $m->getId()->toString(), $secondPage['missions']);
    $this->assertEmpty(array_intersect($firstPageIds, $secondPageIds));

    // Property: Total should be consistent across pages
    $this->assertEquals($firstPage['total'], $secondPage['total']);
   }
  }
 }

 /**
  * Property test: Pagination offset calculations should be mathematically correct
  */
 public function test_pagination_offset_calculation_property()
 {
  $clientId = UserId::generate();
  $this->createTestUser($clientId, 'CLIENT');

  // Create a known number of missions
  $totalMissions = 47; // Prime number to test edge cases
  for ($i = 0; $i < $totalMissions; $i++) {
   $this->createTestMission($clientId, "Mission {$i}");
  }

  $pageSize = 20;
  $expectedPages = ceil($totalMissions / $pageSize); // Should be 3 pages

  $allRetrievedIds = [];

  for ($page = 0; $page < $expectedPages; $page++) {
   $offset = $page * $pageSize;
   $result = $this->missionRepository->findByClientIdPaginated($clientId, $pageSize, $offset);

   // Property: Total should always be consistent
   $this->assertEquals($totalMissions, $result['total']);

   // Property: Last page might have fewer items
   if ($page === $expectedPages - 1) {
    $expectedItemsOnLastPage = $totalMissions - ($page * $pageSize);
    $this->assertEquals($expectedItemsOnLastPage, count($result['missions']));
   } else {
    $this->assertEquals($pageSize, count($result['missions']));
   }

   // Collect IDs to check for duplicates
   foreach ($result['missions'] as $mission) {
    $allRetrievedIds[] = $mission->getId()->toString();
   }
  }

  // Property: All missions should be retrieved exactly once
  $this->assertEquals($totalMissions, count($allRetrievedIds));
  $this->assertEquals($totalMissions, count(array_unique($allRetrievedIds)));
 }

 private function assertPaginationConsistency(UserId $clientId, array $expectedMissionIds, int $totalMissions): void
 {
  $pageSize = 20; // As per requirement 17.2
  $expectedPages = ceil($totalMissions / $pageSize);

  $allRetrievedIds = [];

  for ($page = 0; $page < $expectedPages; $page++) {
   $offset = $page * $pageSize;
   $result = $this->missionRepository->findByClientIdPaginated($clientId, $pageSize, $offset);

   // Property: Total count should be consistent across all pages
   $this->assertEquals(
    $totalMissions,
    $result['total'],
    "Total count inconsistent on page {$page} for {$totalMissions} missions"
   );

   // Property: Page size should be respected
   $expectedItemsOnPage = min($pageSize, $totalMissions - $offset);
   $this->assertEquals(
    $expectedItemsOnPage,
    count($result['missions']),
    "Page size not respected on page {$page} for {$totalMissions} missions"
   );

   // Collect mission IDs
   foreach ($result['missions'] as $mission) {
    $allRetrievedIds[] = $mission->getId()->toString();
   }
  }

  // Property: All missions should be retrieved exactly once (no duplicates, no missing)
  $this->assertEquals(
   $totalMissions,
   count($allRetrievedIds),
   "Not all missions retrieved for {$totalMissions} missions"
  );
  $this->assertEquals(
   $totalMissions,
   count(array_unique($allRetrievedIds)),
   "Duplicate missions found in pagination for {$totalMissions} missions"
  );
 }

 private function createTestUser(UserId $userId, string $userType): void
 {
  \DB::table('users')->insert([
   'id' => $userId->toString(),
   'email' => "test-{$userId->toString()}@example.com",
   'password' => bcrypt('password'),
   'user_type' => $userType,
   'account_status' => 'ACTIVE',
   'created_at' => now(),
   'updated_at' => now(),
  ]);
 }

 private function createTestMission(UserId $clientId, string $description): string
 {
  $missionId = \Str::uuid();

  \DB::table('missions')->insert([
   'id' => $missionId,
   'client_id' => $clientId->toString(),
   'description' => $description,
   'trade_category' => 'PLUMBER',
   'location' => \DB::raw("ST_GeomFromText('POINT(-4.0083 5.3600)', 4326)"),
   'budget_min_centimes' => 5000000,
   'budget_max_centimes' => 10000000,
   'status' => 'OPEN',
   'created_at' => now(),
   'updated_at' => now(),
  ]);

  return $missionId;
 }

 private function createTestMissionAtLocation(UserId $clientId, string $description, GPS_Coordinates $location): string
 {
  $missionId = \Str::uuid();

  \DB::table('missions')->insert([
   'id' => $missionId,
   'client_id' => $clientId->toString(),
   'description' => $description,
   'trade_category' => 'PLUMBER',
   'location' => \DB::raw("ST_GeomFromText('POINT({$location->getLongitude()} {$location->getLatitude()})', 4326)"),
   'budget_min_centimes' => 5000000,
   'budget_max_centimes' => 10000000,
   'status' => 'OPEN',
   'created_at' => now(),
   'updated_at' => now(),
  ]);

  return $missionId;
 }
}
