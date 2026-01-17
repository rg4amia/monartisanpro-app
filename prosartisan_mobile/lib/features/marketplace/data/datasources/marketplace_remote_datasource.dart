import 'package:dio/dio.dart';
import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/data/models/artisan_model.dart';
import 'package:prosartisan_mobile/features/marketplace/data/models/devis_model.dart';
import 'package:prosartisan_mobile/features/marketplace/data/models/mission_model.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';

abstract class MarketplaceRemoteDataSource {
  Future<List<ArtisanModel>> searchArtisans({
    required GPSCoordinates location,
    required double radiusKm,
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  });

  Future<ArtisanModel?> getArtisanById(String id);

  Future<List<MissionModel>> getMissions({
    int page = 1,
    int limit = 20,
    String? clientId,
    MissionStatus? status,
  });

  Future<MissionModel?> getMissionById(String id);

  Future<List<MissionModel>> getMissionsNearLocation(
    GPSCoordinates location,
    double radiusKm, {
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  });

  Future<MissionModel> createMission({
    required String description,
    required TradeCategory category,
    required GPSCoordinates location,
    required double budgetMin,
    required double budgetMax,
  });

  Future<List<DevisModel>> getDevisByMissionId(String missionId);

  Future<List<DevisModel>> getDevisByArtisanId(String artisanId);

  Future<DevisModel?> getDevisById(String id);

  Future<DevisModel> createDevis({
    required String missionId,
    required double totalAmount,
    required double materialsAmount,
    required double laborAmount,
    required List<DevisLine> lineItems,
    DateTime? expiresAt,
  });

  Future<DevisModel> acceptDevis(String devisId);

  Future<DevisModel> rejectDevis(String devisId);
}

class MarketplaceRemoteDataSourceImpl implements MarketplaceRemoteDataSource {
  final Dio _dio;

  MarketplaceRemoteDataSourceImpl(this._dio);

  @override
  Future<List<ArtisanModel>> searchArtisans({
    required GPSCoordinates location,
    required double radiusKm,
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  }) async {
    final queryParams = <String, String>{
      'latitude': location.latitude.toString(),
      'longitude': location.longitude.toString(),
      'radius_km': radiusKm.toString(),
      'page': page.toString(),
      'limit': limit.toString(),
    };

    if (category != null) {
      queryParams['category'] = category.value;
    }

    final response = await _dio.get(
      '/api/v1/artisans/search',
      queryParameters: queryParams,
    );

    final List<dynamic> data = response.data['data'] as List<dynamic>;
    return data
        .map((json) => ArtisanModel.fromJson(json as Map<String, dynamic>))
        .toList();
  }

  @override
  Future<ArtisanModel?> getArtisanById(String id) async {
    try {
      final response = await _dio.get('/api/v1/artisans/$id');
      return ArtisanModel.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        return null;
      }
      rethrow;
    }
  }

  @override
  Future<List<MissionModel>> getMissions({
    int page = 1,
    int limit = 20,
    String? clientId,
    MissionStatus? status,
  }) async {
    final queryParams = <String, String>{
      'page': page.toString(),
      'limit': limit.toString(),
    };

    if (clientId != null) {
      queryParams['client_id'] = clientId;
    }

    if (status != null) {
      queryParams['status'] = status.value;
    }

    final response = await _dio.get(
      '/api/v1/missions',
      queryParameters: queryParams,
    );

    final List<dynamic> data = response.data['data'] as List<dynamic>;
    return data
        .map((json) => MissionModel.fromJson(json as Map<String, dynamic>))
        .toList();
  }

  @override
  Future<MissionModel?> getMissionById(String id) async {
    try {
      final response = await _dio.get('/api/v1/missions/$id');
      return MissionModel.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        return null;
      }
      rethrow;
    }
  }

  @override
  Future<List<MissionModel>> getMissionsNearLocation(
    GPSCoordinates location,
    double radiusKm, {
    TradeCategory? category,
    int page = 1,
    int limit = 20,
  }) async {
    final queryParams = <String, String>{
      'latitude': location.latitude.toString(),
      'longitude': location.longitude.toString(),
      'radius_km': radiusKm.toString(),
      'page': page.toString(),
      'limit': limit.toString(),
    };

    if (category != null) {
      queryParams['category'] = category.value;
    }

    final response = await _dio.get(
      '/api/v1/missions/nearby',
      queryParameters: queryParams,
    );

    final List<dynamic> data = response.data['data'] as List<dynamic>;
    return data
        .map((json) => MissionModel.fromJson(json as Map<String, dynamic>))
        .toList();
  }

  @override
  Future<MissionModel> createMission({
    required String description,
    required TradeCategory category,
    required GPSCoordinates location,
    required double budgetMin,
    required double budgetMax,
  }) async {
    final response = await _dio.post(
      '/api/v1/missions',
      data: {
        'description': description,
        'trade_category': category.value,
        'location': location.toJson(),
        'budget_min': budgetMin,
        'budget_max': budgetMax,
      },
    );

    return MissionModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  @override
  Future<List<DevisModel>> getDevisByMissionId(String missionId) async {
    final response = await _dio.get('/api/v1/missions/$missionId/quotes');

    final List<dynamic> data = response.data['data'] as List<dynamic>;
    return data
        .map((json) => DevisModel.fromJson(json as Map<String, dynamic>))
        .toList();
  }

  @override
  Future<List<DevisModel>> getDevisByArtisanId(String artisanId) async {
    final response = await _dio.get('/api/v1/artisans/$artisanId/quotes');

    final List<dynamic> data = response.data['data'] as List<dynamic>;
    return data
        .map((json) => DevisModel.fromJson(json as Map<String, dynamic>))
        .toList();
  }

  @override
  Future<DevisModel?> getDevisById(String id) async {
    try {
      final response = await _dio.get('/api/v1/quotes/$id');
      return DevisModel.fromJson(response.data['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        return null;
      }
      rethrow;
    }
  }

  @override
  Future<DevisModel> createDevis({
    required String missionId,
    required double totalAmount,
    required double materialsAmount,
    required double laborAmount,
    required List<DevisLine> lineItems,
    DateTime? expiresAt,
  }) async {
    final response = await _dio.post(
      '/api/v1/missions/$missionId/quotes',
      data: {
        'total_amount': totalAmount,
        'materials_amount': materialsAmount,
        'labor_amount': laborAmount,
        'line_items': lineItems.map((item) => item.toJson()).toList(),
        'expires_at': expiresAt?.toIso8601String(),
      },
    );

    return DevisModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  @override
  Future<DevisModel> acceptDevis(String devisId) async {
    final response = await _dio.post('/api/v1/quotes/$devisId/accept');
    return DevisModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  @override
  Future<DevisModel> rejectDevis(String devisId) async {
    final response = await _dio.post('/api/v1/quotes/$devisId/reject');
    return DevisModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }
}
