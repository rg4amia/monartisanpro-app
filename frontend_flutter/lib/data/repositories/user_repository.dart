import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';

class UserRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>> updateProfile({
    required int userId,
    required String name,
    bool? nightInterventionAvailable,
    int? sectorId,
    int? tradeId,
  }) async {
    final response = await _client.put(
      ApiEndpoints.updateUser(userId),
      data: {
        'name': name,
        if (nightInterventionAvailable != null)
          'intervention_nuit': nightInterventionAvailable,
        if (sectorId != null) 'sector_id': sectorId,
        if (tradeId != null) 'trade_id': tradeId,
      },
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateLocation({
    required int userId,
    required double lat,
    required double lng,
  }) async {
    final response = await _client.put(
      ApiEndpoints.updateLocation(userId),
      data: {
        'lat': lat,
        'lng': lng,
      },
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> setRole({
    required int userId,
    required String role,
  }) async {
    final response = await _client.put(
      ApiEndpoints.setRole(userId),
      data: {'role': role},
    );
    return response.data as Map<String, dynamic>;
  }
}
