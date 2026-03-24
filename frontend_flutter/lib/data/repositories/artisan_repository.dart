import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/artisan_model.dart';

class ArtisanRepository {
  final ApiClient _client = ApiClient();

  Future<List<ArtisanModel>> getNearby({
    required double lat,
    required double lng,
    int? radiusMeters,
    String? sectorId,
    String? tradeId,
    bool? interventionNuit,
  }) async {
    final res = await _client.get(
      ApiEndpoints.artisans,
      params: {
        'lat': lat,
        'lng': lng,
        if (radiusMeters != null) 'radius': radiusMeters,
        if (sectorId != null) 'sector': sectorId,
        if (tradeId != null) 'trade': tradeId,
        if (interventionNuit != null) 'intervention_nuit': interventionNuit,
      },
    );
    final list = res.data['data'] as List<dynamic>;
    return list
        .map((e) => ArtisanModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<ArtisanModel> getArtisan(int userId) async {
    final res = await _client.get(ApiEndpoints.artisan(userId));
    return ArtisanModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> getScore(int userId) async {
    final res = await _client.get(ApiEndpoints.artisanScore(userId));
    return res.data as Map<String, dynamic>;
  }
}
