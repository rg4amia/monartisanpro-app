import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/artisan.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/artisan_repository.dart';

class SearchArtisansUseCase {
  final ArtisanRepository _repository;

  SearchArtisansUseCase(this._repository);

  Future<List<Artisan>> execute({
    required GPSCoordinates location,
    required double radiusKm,
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  }) async {
    final artisans = await _repository.searchArtisans(
      location: location,
      radiusKm: radiusKm,
      category: category,
      page: page,
      limit: limit,
    );

    // Sort by proximity first, then by N'Zassa score
    artisans.sort((a, b) {
      final distanceA = a.distanceToLocation(location);
      final distanceB = b.distanceToLocation(location);

      // Golden range (≤1km) artisans come first
      final aIsGolden = a.isWithinGoldenRange(location);
      final bIsGolden = b.isWithinGoldenRange(location);

      if (aIsGolden && !bIsGolden) return -1;
      if (!aIsGolden && bIsGolden) return 1;

      // Within same range, sort by distance first
      final distanceComparison = distanceA.compareTo(distanceB);
      if (distanceComparison != 0) return distanceComparison;

      // If same distance, sort by N'Zassa score (higher is better)
      return b.nzassaScore.compareTo(a.nzassaScore);
    });

    return artisans;
  }
}
