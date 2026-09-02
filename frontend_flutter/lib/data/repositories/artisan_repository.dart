import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../models/artisan_model.dart';

class ArtisanRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<ArtisanModel> _store = CacheStore<ArtisanModel>(
    boxName: 'artisans_cache',
    fromJson: ArtisanModel.fromJson,
    toJson: (a) => a.toJson(),
  );

  static const Duration _nearbyTtl = Duration(minutes: 3);
  static const Duration _profileTtl = Duration(minutes: 10);

  String get _userScope => 'u${StorageService.getUserId() ?? 0}';

  Future<List<ArtisanModel>> getNearby({
    required double lat,
    required double lng,
    int? radiusMeters,
    String? sectorId,
    String? tradeId,
    bool? interventionNuit,
    bool forceRefresh = false,
  }) async {
    await _store.init();

    final key = '${_userScope}_nearby_'
        '${lat.toStringAsFixed(3)}_${lng.toStringAsFixed(3)}_'
        '${radiusMeters ?? 0}_${sectorId ?? ''}_${tradeId ?? ''}_'
        '${interventionNuit == true ? 1 : 0}';

    return _store.readList(
      key: key,
      ttl: _nearbyTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(
            ApiEndpoints.artisans,
            params: {
              'lat': lat,
              'lng': lng,
              if (radiusMeters != null) 'radius': radiusMeters,
              if (sectorId != null) 'sector': sectorId,
              if (tradeId != null) 'trade': tradeId,
              if (interventionNuit == true) 'intervention_nuit': 1,
            },
          ),
        );
        final list = res.data['data'] as List<dynamic>;
        return list
            .map((e) => ArtisanModel.fromJson(e as Map<String, dynamic>))
            .toList();
      },
    );
  }

  Future<ArtisanModel> getArtisan(
    int userId, {
    bool forceRefresh = false,
  }) async {
    await _store.init();

    return _store.readOne(
      key: '${_userScope}_artisan_$userId',
      ttl: _profileTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.artisan(userId)),
        );
        return ArtisanModel.fromJson(res.data['data'] as Map<String, dynamic>);
      },
    );
  }

  Future<Map<String, dynamic>> getScore(int userId) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.artisanScore(userId)),
    );
    return res.data as Map<String, dynamic>;
  }

  /// Purge le cache artisans (à appeler à la déconnexion).
  static Future<void> clearCache() async {
    await _store.init();
    await _store.clear();
  }
}
