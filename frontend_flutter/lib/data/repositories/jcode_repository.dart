import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/jcode_model.dart';

class JcodeRepository {
  final ApiClient _client = ApiClient();

  Future<JcodeModel> createJcode({
    required int missionId,
    required int montant,
  }) async {
    final res = await _client.post(ApiEndpoints.jcodes, data: {
      'mission_id': missionId,
      'montant': montant,
    });
    return JcodeModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  Future<JcodeModel?> getActiveJcode() async {
    final res = await _client.get(ApiEndpoints.jcodesActive);
    final data = res.data['data'];
    if (data == null) return null;
    return JcodeModel.fromJson(data as Map<String, dynamic>);
  }

  Future<JcodeModel> getJcode(int id) async {
    final res = await _client.get(ApiEndpoints.jcode(id));
    return JcodeModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  /// Supplier scans a J-Code. [lat]/[lng] = GPS of supplier at scan time.
  Future<Map<String, dynamic>> scanJcode({
    required int id,
    required double lat,
    required double lng,
  }) async {
    final res = await _client.post(ApiEndpoints.scanJcode(id), data: {
      'lat': lat,
      'lng': lng,
    });
    return res.data as Map<String, dynamic>;
  }
}
