import '../../core/network/api_client.dart';

class ParrainageRepository {
  final ApiClient _client = ApiClient();

  Future<List<Map<String, dynamic>>> getFilleuls() async {
    final res = await _client.get('/parrainages');
    final data = res.data['data'] as List?;
    if (data == null) return [];
    return List<Map<String, dynamic>>.from(data);
  }

  Future<Map<String, dynamic>> addFilleul(String phone) async {
    final res = await _client.post('/parrainages', data: {
      'filleul_phone': phone,
    });
    return res.data;
  }
}
