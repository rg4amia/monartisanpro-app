<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;

/**
 * Default implementation of ArtisanSearchService
 *
 * Uses UserRepository with PostGIS for efficient proximity queries
 * Implements proximity-based sorting with 1km threshold for "nearby" artisans
 */
class DefaultArtisanSearchService implements ArtisanSearchService
{
 private const NEARBY_THRESHOLD_METERS = 1000; // 1km

 private UserRepository $userRepository;

 public function __construct(UserRepository $userRepository)
 {
  $this->userRepository = $userRepository;
 }

 /**
  * Search for artisans near a location with optional trade category filter
  *
  * Requirements:
  * - 2.1: Filter results by trade category (plumber, electrician, mason)
  * - 2.2: Show artisans within 1km with golden markers at top of results
  * - 2.6: Sort search results by proximity first, then by Score_N'Zassa
  *
  * @param GPS_Coordinates $location Client's search location
  * @param TradeCategory|null $category Optional trade category filter
  * @param float $radiusKm Search radius in kilometers (default 10km)
  * @return array Array of arrays with keys: 'artisan' (Artisan), 'distance' (float), 'is_nearby' (bool)
  */
 public function searchNearby(
  GPS_Coordinates $location,
  ?TradeCategory $category = null,
  float $radiusKm = 10.0
 ): array {
  // Get artisans from repository (already sorted by distance)
  $artisans = $this->userRepository->findArtisansNearLocation($location, $radiusKm);

  // Filter by trade category if specified
  if ($category !== null) {
   $artisans = array_filter($artisans, function (Artisan $artisan) use ($category) {
    return $artisan->getCategory()->equals($category);
   });
  }

  // Apply proximity boost and sorting
  return $this->applyProximityBoost($artisans, $location);
 }

 /**
  * Apply proximity boost to artisan search results
  *
  * Sorts artisans with those ≤1km appearing first (golden markers),
  * then by Score_N'Zassa within each group
  *
  * Algorithm:
  * 1. Calculate distance for each artisan
  * 2. Mark artisans ≤1km as "nearby"
  * 3. Sort by: is_nearby DESC, score DESC, distance ASC
  *
  * @param array $artisans Array of Artisan entities
  * @param GPS_Coordinates $clientLocation Client's location for distance calculation
  * @return array Array of arrays with keys: 'artisan', 'distance', 'is_nearby', 'score'
  */
 public function applyProximityBoost(array $artisans, GPS_Coordinates $clientLocation): array
 {
  // Calculate distance and nearby status for each artisan
  $enrichedResults = array_map(function (Artisan $artisan) use ($clientLocation) {
   $distance = $clientLocation->distanceTo($artisan->getLocation());
   $isNearby = $distance <= self::NEARBY_THRESHOLD_METERS;

   // TODO: Get actual N'Zassa score from Reputation context
   // For now, use a placeholder score of 0
   // This will be properly implemented when Reputation context is complete
   $score = 0;

   return [
    'artisan' => $artisan,
    'distance' => $distance,
    'is_nearby' => $isNearby,
    'score' => $score,
   ];
  }, $artisans);

  // Sort by: is_nearby DESC (nearby first), score DESC (higher score first), distance ASC (closer first)
  usort($enrichedResults, function ($a, $b) {
   // First, sort by nearby status (nearby artisans first)
   if ($a['is_nearby'] !== $b['is_nearby']) {
    return $b['is_nearby'] <=> $a['is_nearby'];
   }

   // Within same proximity group, sort by score (higher first)
   if ($a['score'] !== $b['score']) {
    return $b['score'] <=> $a['score'];
   }

   // If scores are equal, sort by distance (closer first)
   return $a['distance'] <=> $b['distance'];
  });

  return $enrichedResults;
 }
}
