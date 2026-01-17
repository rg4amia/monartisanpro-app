<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;

/**
 * Domain service for searching and discovering artisans
 *
 * Handles proximity-based search with trade category filtering
 * Implements special sorting for artisans within 1km (golden markers)
 *
 * Requirements:
 * - 2.1: Filter results by trade category
 * - 2.2: Show artisans within 1km with golden markers at top of results
 * - 2.6: Sort search results by proximity first, then by Score_N'Zassa
 */
interface ArtisanSearchService
{
 /**
  * Search for artisans near a location with optional trade category filter
  *
  * Returns artisans sorted by:
  * 1. Proximity (artisans ≤1km appear first)
  * 2. Score_N'Zassa (descending) within each proximity group
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
 ): array;

 /**
  * Apply proximity boost to artisan search results
  *
  * Sorts artisans with those ≤1km appearing first (golden markers),
  * then by Score_N'Zassa within each group
  *
  * @param array $artisans Array of Artisan entities
  * @param GPS_Coordinates $clientLocation Client's location for distance calculation
  * @return array Array of arrays with keys: 'artisan', 'distance', 'is_nearby'
  */
 public function applyProximityBoost(array $artisans, GPS_Coordinates $clientLocation): array;
}
