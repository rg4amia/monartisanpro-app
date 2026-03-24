import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';

class UserRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>> updateProfile({
    required int userId,
    required String name,
    bool? nightInterventionAvailable,
  }) async {
    final response = await _client.put(
      ApiEndpoints.updateUser(userId),
      data: {
        'name': name,
        if (nightInterventionAvailable != null)
          'intervention_nuit': nightInterventionAvailable,
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
