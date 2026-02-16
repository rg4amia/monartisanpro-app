import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/artisan_search_model.dart';
import 'dio_client.dart';

class SearchService {
  final Dio _dio = DioClient().dio;

  /// Search for artisans near a location
  Future<ApiResponse<List<ArtisanSearchResult>>> searchArtisans({
    required double latitude,
    required double longitude,
    int? tradeId,
    double? radius, // in meters
    int? minScore,
    String? sortBy, // distance, rating, experience
  }) async {
    try {
      final response = await _dio.get('/search/artisans', queryParameters: {
        'lat': latitude,
        'lng': longitude,
        if (tradeId != null) 'trade_id': tradeId,
        if (radius != null) 'radius': radius,
        if (minScore != null) 'min_score': minScore,
        if (sortBy != null) 'sort_by': sortBy,
      });

      return ApiResponse<List<ArtisanSearchResult>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((artisan) =>
                ArtisanSearchResult.fromJson(artisan as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<ArtisanSearchResult>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get nearby artisans (<2km with golden marker)
  Future<ApiResponse<List<ArtisanSearchResult>>> getNearbyArtisans({
    required double latitude,
    required double longitude,
    int? tradeId,
  }) async {
    try {
      final response = await _dio.get('/search/nearby', queryParameters: {
        'lat': latitude,
        'lng': longitude,
        if (tradeId != null) 'trade_id': tradeId,
      });

      return ApiResponse<List<ArtisanSearchResult>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((artisan) =>
                ArtisanSearchResult.fromJson(artisan as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<ArtisanSearchResult>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get artisan profile by ID
  Future<ApiResponse<ArtisanSearchResult>> getArtisanProfile(int id) async {
    try {
      final response = await _dio.get('/search/artisan/$id');

      return ApiResponse<ArtisanSearchResult>.fromJson(
        response.data,
        (json) => ArtisanSearchResult.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<ArtisanSearchResult>.fromJson(
          e.response!.data,
          (json) => ArtisanSearchResult.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get clustered markers for map view
  Future<ApiResponse<List<ClusterMarker>>> getClusteredMarkers({
    required double latitude,
    required double longitude,
    required double radius,
    required int zoomLevel,
    int? tradeId,
  }) async {
    try {
      final response = await _dio.get('/search/clusters', queryParameters: {
        'lat': latitude,
        'lng': longitude,
        'radius': radius,
        'zoom': zoomLevel,
        if (tradeId != null) 'trade_id': tradeId,
      });

      return ApiResponse<List<ClusterMarker>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((cluster) =>
                ClusterMarker.fromJson(cluster as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<ClusterMarker>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }
}
