import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/data/datasources/marketplace_remote_datasource.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/artisan.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/artisan_repository.dart';

class ArtisanRepositoryImpl implements ArtisanRepository {
  final MarketplaceRemoteDataSource _remoteDataSource;

  ArtisanRepositoryImpl(this._remoteDataSource);

  @override
  Future<List<Artisan>> searchArtisans({
    required GPSCoordinates location,
    required double radiusKm,
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  }) async {
    final models = await _remoteDataSource.searchArtisans(
      location: location,
      radiusKm: radiusKm,
      category: category,
      page: page,
      limit: limit,
    );
    return models.map((model) => model.toEntity()).toList();
  }

  @override
  Future<Artisan?> getArtisanById(String id) async {
    final model = await _remoteDataSource.getArtisanById(id);
    return model?.toEntity();
  }

  @override
  Future<List<Artisan>> getTopArtisans({
    int limit = 10,
    TradeCategory? category,
  }) async {
    // This would typically be a separate endpoint for top artisans
    // For now, we'll use the search with a large radius and sort by score
    final models = await _remoteDataSource.searchArtisans(
      location: const GPSCoordinates(latitude: 0, longitude: 0), // Placeholder
      radiusKm: 1000, // Large radius to get all artisans
      category: category,
      limit: limit,
    );

    final artisans = models.map((model) => model.toEntity()).toList();

    // Sort by N'Zassa score (highest first)
    artisans.sort((a, b) => b.nzassaScore.compareTo(a.nzassaScore));

    return artisans.take(limit).toList();
  }

  @override
  Future<List<Artisan>> getArtisansNearby({
    required GPSCoordinates location,
    required double radiusKm,
    int limit = 50,
  }) async {
    final models = await _remoteDataSource.searchArtisans(
      location: location,
      radiusKm: radiusKm,
      limit: limit,
    );
    return models.map((model) => model.toEntity()).toList();
  }
}
