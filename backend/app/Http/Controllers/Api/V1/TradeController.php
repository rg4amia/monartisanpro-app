<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
  /**
   * Get all sectors with their trades
   */
  public function getSectors(): JsonResponse
  {
    try {
      $sectors = Sector::with('trades')
        ->orderBy('name')
        ->get()
        ->map(function ($sector) {
          return [
            'id' => $sector->id,
            'code' => $sector->code,
            'name' => $sector->name,
            'trades_count' => $sector->trades->count(),
            'created_at' => $sector->created_at?->toISOString(),
            'updated_at' => $sector->updated_at?->toISOString(),
          ];
        });

      return response()->json([
        'success' => true,
        'data' => $sectors,
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Failed to fetch sectors',
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get trades by sector
   */
  public function getTradesBySector(Request $request, int $sectorId): JsonResponse
  {
    try {
      $sector = Sector::findOrFail($sectorId);

      $trades = Trade::where('sector_id', $sectorId)
        ->orderBy('name')
        ->get()
        ->map(function ($trade) {
          return [
            'id' => $trade->id,
            'sector_id' => $trade->sector_id,
            'code' => $trade->code,
            'name' => $trade->name,
          ];
        });

      return response()->json([
        'success' => true,
        'data' => [
          'sector' => [
            'id' => $sector->id,
            'code' => $sector->code,
            'name' => $sector->name,
          ],
          'trades' => $trades,
        ],
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Failed to fetch trades',
        'error' => $e->getMessage(),
      ], 404);
    }
  }

  /**
   * Get all trades
   */
  public function getAllTrades(): JsonResponse
  {
    try {
      $trades = Trade::with('sector')
        ->orderBy('name')
        ->get()
        ->map(function ($trade) {
          return [
            'id' => $trade->id,
            'sector_id' => $trade->sector_id,
            'code' => $trade->code,
            'name' => $trade->name,
            'sector' => $trade->sector ? [
              'id' => $trade->sector->id,
              'code' => $trade->sector->code,
              'name' => $trade->sector->name,
            ] : null,
          ];
        });

      return response()->json([
        'success' => true,
        'data' => $trades,
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Failed to fetch trades',
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Search artisans by location and filters
   */
  public function searchArtisans(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate([
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'radius' => 'nullable|numeric|min:1000|max:50000', // in meters
        'sector_id' => 'nullable|exists:sectors,id',
        'trade_id' => 'nullable|exists:trades,id',
        'min_score' => 'nullable|integer|min:0|max:100',
        'sort_by' => 'nullable|in:distance,rating,experience',
      ]);

      $latitude = $validated['latitude'];
      $longitude = $validated['longitude'];
      $radius = $validated['radius'] ?? 10000; // Default 10km
      $sortBy = $validated['sort_by'] ?? 'distance';

      // Build query
      $query = \App\Models\User::query()
        ->where('role', 'artisan')
        ->where('status', 'active')
        ->with(['artisanProfile.trade.sector', 'artisanScore'])
        ->whereHas('artisanProfile', function ($q) use ($validated) {
          if (isset($validated['trade_id'])) {
            $q->where('trade_id', $validated['trade_id']);
          } elseif (isset($validated['sector_id'])) {
            $q->whereHas('trade', function ($tq) use ($validated) {
              $tq->where('sector_id', $validated['sector_id']);
            });
          }
        });

      // Filter by score if specified
      if (isset($validated['min_score'])) {
        $query->whereHas('artisanScore', function ($q) use ($validated) {
          $q->where('total_score', '>=', $validated['min_score']);
        });
      }

      // Get artisans
      $artisans = $query->get();

      // Calculate distances and filter by radius
      $results = $artisans->map(function ($artisan) use ($latitude, $longitude, $radius) {
        $profile = $artisan->artisanProfile;

        if (!$profile || !$profile->latitude || !$profile->longitude) {
          return null;
        }

        // Calculate distance using Haversine formula
        $distance = $this->calculateDistance(
          $latitude,
          $longitude,
          $profile->latitude,
          $profile->longitude
        );

        if ($distance > $radius) {
          return null;
        }

        return [
          'id' => $artisan->id,
          'name' => $artisan->name,
          'email' => $artisan->email,
          'phone' => $artisan->phone,
          'avatar' => $artisan->avatar,
          'trade_id' => $profile->trade_id,
          'trade_name' => $profile->trade->name ?? null,
          'sector_name' => $profile->trade->sector->name ?? null,
          'zone_name' => $profile->zone_name,
          'bio' => $profile->bio,
          'experience_years' => $profile->experience_years,
          'available' => $profile->available,
          'distance' => round($distance),
          'distance_text' => $this->formatDistance($distance),
          'is_nearby' => $distance <= 2000, // Within 2km
          'latitude' => $profile->latitude,
          'longitude' => $profile->longitude,
          'nzassa_score' => $artisan->artisanScore->total_score ?? null,
          'average_rating' => $artisan->reviews_avg_rating ?? null,
          'reviews_count' => $artisan->reviews_count ?? 0,
          'projects_completed' => $artisan->completed_projects_count ?? 0,
        ];
      })->filter()->values();

      // Sort results
      $results = $this->sortResults($results, $sortBy);

      return response()->json([
        'success' => true,
        'data' => $results,
        'meta' => [
          'total' => $results->count(),
          'radius' => $radius,
          'nearby_count' => $results->where('is_nearby', true)->count(),
        ],
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Failed to search artisans',
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Calculate distance between two coordinates using Haversine formula
   */
  private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
  {
    $earthRadius = 6371000; // meters

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
      cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
      sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
  }

  /**
   * Format distance for display
   */
  private function formatDistance(float $meters): string
  {
    if ($meters < 1000) {
      return round($meters) . ' m';
    }

    return round($meters / 1000, 1) . ' km';
  }

  /**
   * Sort results based on criteria
   */
  private function sortResults($results, string $sortBy)
  {
    switch ($sortBy) {
      case 'rating':
        return $results->sortByDesc('average_rating');
      case 'experience':
        return $results->sortByDesc('experience_years');
      case 'distance':
      default:
        return $results->sortBy('distance');
    }
  }
}
