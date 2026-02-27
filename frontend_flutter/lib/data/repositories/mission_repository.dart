import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/jalon_model.dart';
import '../models/mission_model.dart';

class MissionRepository {
  final ApiClient _client = ApiClient();

  Future<List<MissionModel>> getMissions({String? status}) async {
    final res = await _client.get(
      ApiEndpoints.missions,
      params: status != null ? {'status': status} : null,
    );
    final list = res.data['data'] as List<dynamic>;
    return list
        .map((e) => MissionModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<MissionModel> getMission(int id) async {
    final res = await _client.get(ApiEndpoints.mission(id));
    return MissionModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  Future<MissionModel> createMission({
    required int artisanId,
    required String description,
    required String category,
    required String urgency,
    String? location,
  }) async {
    final res = await _client.post(ApiEndpoints.missions, data: {
      'artisan_id': artisanId,
      'description': description,
      'category': category,
      'urgency': urgency,
      if (location != null) 'location': location,
    });
    return MissionModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> estimate({
    required String description,
    required String category,
  }) async {
    final res = await _client.post(ApiEndpoints.missionEstimate, data: {
      'description': description,
      'category': category,
    });
    return res.data as Map<String, dynamic>;
  }

  Future<void> updateStatus(int id, String status) async {
    await _client.put(ApiEndpoints.missionStatus(id), data: {'status': status});
  }

  Future<List<JalonModel>> getJalons(int missionId) async {
    final res = await _client.get(ApiEndpoints.missionJalons(missionId));
    final list = res.data['data'] as List<dynamic>;
    return list
        .map((e) => JalonModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> submitJalon(int jalonId) async {
    await _client.put(ApiEndpoints.submitJalon(jalonId));
  }

  Future<void> requestOtp(int jalonId) async {
    await _client.post(ApiEndpoints.requestOtp(jalonId));
  }

  Future<void> validateOtp(int jalonId, String otp) async {
    await _client.post(ApiEndpoints.validateOtp(jalonId), data: {'otp': otp});
  }
}
