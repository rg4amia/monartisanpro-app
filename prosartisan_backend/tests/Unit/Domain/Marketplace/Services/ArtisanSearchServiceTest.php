<?php

namespace Tests\Unit\Domain\Marketplace\Services;

use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Marketplace\Services\DefaultArtisanSearchService;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ArtisanSearchService
 *
 * Tests proximity-based search with trade category filtering
 * and proper sorting (≤1km first, then by score)
 */
class ArtisanSearchServiceTest extends TestCase
{
    private UserRepository $mockRepository;

    private DefaultArtisanSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(UserRepository::class);
        $this->service = new DefaultArtisanSearchService($this->mockRepository);
    }

    /**
     * Test that searchNearby filters by trade category
     * Requirement 2.1: Filter results by trade category
     */
    public function test_search_nearby_filters_by_trade_category(): void
    {
        $clientLocation = new GPS_Coordinates(5.3600, -4.0083); // Abidjan

        // Create artisans with different categories
        $plumber = $this->createArtisan(
            'plumber@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3610, -4.0083) // ~1.1km away
        );

        $electrician = $this->createArtisan(
            'electrician@test.com',
            TradeCategory::ELECTRICIAN(),
            new GPS_Coordinates(5.3605, -4.0083) // ~0.5km away
        );

        $mason = $this->createArtisan(
            'mason@test.com',
            TradeCategory::MASON(),
            new GPS_Coordinates(5.3615, -4.0083) // ~1.6km away
        );

        // Mock repository to return all artisans
        $this->mockRepository
            ->expects($this->once())
            ->method('findArtisansNearLocation')
            ->with($clientLocation, 10.0)
            ->willReturn([$plumber, $electrician, $mason]);

        // Search for plumbers only
        $results = $this->service->searchNearby($clientLocation, TradeCategory::PLUMBER());

        // Should only return the plumber
        $this->assertCount(1, $results);
        $this->assertSame($plumber, $results[0]['artisan']);
    }

    /**
     * Test that searchNearby returns all artisans when no category filter is provided
     */
    public function test_search_nearby_without_category_filter_returns_all(): void
    {
        $clientLocation = new GPS_Coordinates(5.3600, -4.0083);

        $plumber = $this->createArtisan(
            'plumber@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3610, -4.0083)
        );

        $electrician = $this->createArtisan(
            'electrician@test.com',
            TradeCategory::ELECTRICIAN(),
            new GPS_Coordinates(5.3605, -4.0083)
        );

        $this->mockRepository
            ->expects($this->once())
            ->method('findArtisansNearLocation')
            ->with($clientLocation, 10.0)
            ->willReturn([$plumber, $electrician]);

        // Search without category filter
        $results = $this->service->searchNearby($clientLocation);

        // Should return all artisans
        $this->assertCount(2, $results);
    }

    /**
     * Test that artisans within 1km are marked as nearby
     * Requirement 2.2: Show artisans within 1km with golden markers at top
     */
    public function test_apply_proximity_boost_marks_artisans_within_1km_as_nearby(): void
    {
        $clientLocation = new GPS_Coordinates(5.3600, -4.0083);

        // Create artisan within 1km (~500m away)
        $nearbyArtisan = $this->createArtisan(
            'nearby@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3645, -4.0083) // ~0.5km away
        );

        // Create artisan beyond 1km (~2km away)
        $farArtisan = $this->createArtisan(
            'far@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3780, -4.0083) // ~2km away
        );

        $results = $this->service->applyProximityBoost(
            [$nearbyArtisan, $farArtisan],
            $clientLocation
        );

        // Check nearby status
        $this->assertTrue($results[0]['is_nearby'], 'Artisan within 1km should be marked as nearby');
        $this->assertFalse($results[1]['is_nearby'], 'Artisan beyond 1km should not be marked as nearby');
    }

    /**
     * Test that nearby artisans appear before far artisans
     * Requirement 2.2: Artisans within 1km appear at top of results
     */
    public function test_apply_proximity_boost_sorts_nearby_artisans_first(): void
    {
        $clientLocation = new GPS_Coordinates(5.3600, -4.0083);

        // Create far artisan (beyond 1km, ~2km away)
        $farArtisan = $this->createArtisan(
            'far@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3780, -4.0083) // ~2km away
        );

        // Create nearby artisan (within 1km, ~500m away)
        $nearbyArtisan = $this->createArtisan(
            'nearby@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3645, -4.0083) // ~0.5km away
        );

        // Pass artisans in wrong order (far first)
        $results = $this->service->applyProximityBoost(
            [$farArtisan, $nearbyArtisan],
            $clientLocation
        );

        // Nearby artisan should be first in results
        $this->assertSame($nearbyArtisan, $results[0]['artisan']);
        $this->assertSame($farArtisan, $results[1]['artisan']);
    }

    /**
     * Test that artisans are sorted by distance within same proximity group
     * Requirement 2.6: Sort by proximity first, then by Score_N'Zassa
     */
    public function test_apply_proximity_boost_sorts_by_distance_within_proximity_group(): void
    {
        $clientLocation = new GPS_Coordinates(5.3600, -4.0083);

        // Create three nearby artisans at different distances
        $artisan1 = $this->createArtisan(
            'artisan1@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3608, -4.0083) // ~0.9km away
        );

        $artisan2 = $this->createArtisan(
            'artisan2@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3602, -4.0083) // ~0.2km away
        );

        $artisan3 = $this->createArtisan(
            'artisan3@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3605, -4.0083) // ~0.5km away
        );

        // Pass artisans in random order
        $results = $this->service->applyProximityBoost(
            [$artisan1, $artisan2, $artisan3],
            $clientLocation
        );

        // All should be nearby
        $this->assertTrue($results[0]['is_nearby']);
        $this->assertTrue($results[1]['is_nearby']);
        $this->assertTrue($results[2]['is_nearby']);

        // Should be sorted by distance (closest first)
        $this->assertSame($artisan2, $results[0]['artisan'], 'Closest artisan should be first');
        $this->assertSame($artisan3, $results[1]['artisan'], 'Middle distance artisan should be second');
        $this->assertSame($artisan1, $results[2]['artisan'], 'Farthest artisan should be third');
    }

    /**
     * Test that results include distance information
     */
    public function test_apply_proximity_boost_includes_distance_information(): void
    {
        $clientLocation = new GPS_Coordinates(5.3600, -4.0083);

        $artisan = $this->createArtisan(
            'artisan@test.com',
            TradeCategory::PLUMBER(),
            new GPS_Coordinates(5.3605, -4.0083)
        );

        $results = $this->service->applyProximityBoost([$artisan], $clientLocation);

        $this->assertArrayHasKey('distance', $results[0]);
        $this->assertIsFloat($results[0]['distance']);
        $this->assertGreaterThan(0, $results[0]['distance']);
    }

    /**
     * Test that empty artisan array returns empty results
     */
    public function test_apply_proximity_boost_handles_empty_array(): void
    {
        $clientLocation = new GPS_Coordinates(5.3600, -4.0083);

        $results = $this->service->applyProximityBoost([], $clientLocation);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test that searchNearby uses custom radius
     */
    public function test_search_nearby_uses_custom_radius(): void
    {
        $clientLocation = new GPS_Coordinates(5.3600, -4.0083);
        $customRadius = 5.0;

        $this->mockRepository
            ->expects($this->once())
            ->method('findArtisansNearLocation')
            ->with($clientLocation, $customRadius)
            ->willReturn([]);

        $this->service->searchNearby($clientLocation, null, $customRadius);
    }

    /**
     * Helper method to create an Artisan for testing
     */
    private function createArtisan(
        string $email,
        TradeCategory $category,
        GPS_Coordinates $location
    ): Artisan {
        return new Artisan(
            UserId::generate(),
            Email::fromString($email),
            HashedPassword::fromPlainText('password123'),
            PhoneNumber::fromString('+2250123456789'),
            $category,
            $location,
            true, // KYC verified
            AccountStatus::ACTIVE()
        );
    }
}
