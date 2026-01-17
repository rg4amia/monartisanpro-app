import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/artisan.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';

abstract class ArtisanRepository {
  Future<List<Artisan>> searchArtisans({
    required GPSCoordinates location,
    required double radiusKm,
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  });

  Future<Artisan?> getArtisanById(String id);

  Future<List<Artisan>> getTopArtisans({
    int limit = 10,
    TradeCategory? category,
  });

  Future<List<Artisan>> getArtisansNearby({
    required GPSCoordinates location,
    required double radiusKm,
    int limit = 50,
  });
}
