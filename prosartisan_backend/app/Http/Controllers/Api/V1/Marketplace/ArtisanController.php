<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Marketplace\Services\ArtisanSearchService;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\ArtisanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API Controller for Artisan search and discovery
 *
 * Requirements: 2.1, 2.2, 2.6
 */
class ArtisanController extends Controller
{
 public function __construct(
  private ArtisanSearchService $artisanSearchService
 ) {}

 /**
  * Search for artisans near a location
  *
  * GET /api/v1/artisans/search
  * Requirements: 2.1, 2.2, 2.6
  */
 public function search(Request $request): JsonResponse
 {
  $validated = $request->validate([
   'latitude' => ['required', 'numeric', 'between:-90,90'],
   'longitude' => ['required', 'numeric', 'between:-180,180'],
   'category' => ['nullable', 'string', Rule::in([
    TradeCategory::PLUMBER,
    TradeCategory::ELECTRICIAN,
    TradeCategory::MASON
   ])],
   'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
  ], [
   'latitude.required' => 'La latitude est obligatoire',
   'latitude.between' => 'La latitude doit être comprise entre -90 et 90',
   'longitude.required' => 'La longitude est obligatoire',
   'longitude.between' => 'La longitude doit être comprise entre -180 et 180',
   'category.in' => 'La catégorie doit être : PLUMBER, ELECTRICIAN ou MASON',
   'radius_km.min' => 'Le rayon de recherche doit être d\'au moins 0.1 km',
   'radius_km.max' => 'Le rayon de recherche ne peut pas dépasser 50 km',
  ]);

  $location = new GPS_Coordinates($validated['latitude'], $validated['longitude']);
  $category = isset($validated['category']) ? TradeCategory::fromString($validated['category']) : null;
  $radiusKm = $validated['radius_km'] ?? 10.0;

  $results = $this->artisanSearchService->searchNearby($location, $category, $radiusKm);

  // Transform results to include distance and nearby status
  $transformedResults = array_map(function ($result) {
   return [
    'artisan' => new ArtisanResource($result['artisan']),
    'distance_meters' => $result['distance'],
    'distance_km' => round($result['distance'] / 1000, 2),
    'is_nearby' => $result['is_nearby'], // Within 1km (golden marker)
    'score' => $result['score'] // N'Zassa score (placeholder for now)
   ];
  }, $results);

  return response()->json([
   'data' => $transformedResults,
   'meta' => [
    'search_location' => [
     'latitude' => $location->getLatitude(),
     'longitude' => $location->getLongitude()
    ],
    'search_radius_km' => $radiusKm,
    'category_filter' => $category?->getValue(),
    'total_results' => count($transformedResults),
    'nearby_count' => count(array_filter($transformedResults, fn($r) => $r['is_nearby']))
   ]
  ]);
 }
}
