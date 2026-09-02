import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';

class EvaluationRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>> submit({
    int? missionId,
    int? orderId,
    required int evalueId,
    required int note,
    String? commentaire,
    required int fiabilite,
    required int integrite,
    required int qualite,
    required int reactivite,
  }) async {
    final res = await _client.post(
      ApiEndpoints.evaluations,
      data: {
        if (missionId != null && missionId > 0) 'mission_id': missionId,
        if (orderId != null && orderId > 0) 'order_id': orderId,
        'evalue_id': evalueId,
        'note': note,
        'commentaire': commentaire == null || commentaire.trim().isEmpty
            ? null
            : commentaire.trim(),
        'fiabilite': fiabilite,
        'integrite': integrite,
        'qualite': qualite,
        'reactivite': reactivite,
      },
    );

    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>?> getMissionActors(int missionId) async {
    try {
      final res =
          await _client.get(ApiEndpoints.missionEvaluationsStatus(missionId));
      if (res.data is Map &&
          (res.data as Map<String, dynamic>)['data'] is Map) {
        return Map<String, dynamic>.from(
          (res.data as Map<String, dynamic>)['data'],
        );
      }
    } catch (_) {}
    return null;
  }

  Future<Map<String, dynamic>?> getOrderActors(int orderId) async {
    try {
      final res =
          await _client.get(ApiEndpoints.orderEvaluationsStatus(orderId));
      if (res.data is Map &&
          (res.data as Map<String, dynamic>)['data'] is Map) {
        return Map<String, dynamic>.from(
          (res.data as Map<String, dynamic>)['data'],
        );
      }
    } catch (_) {}
    return null;
  }

  Future<Map<String, dynamic>?> getMyEvaluations() async {
    try {
      final res = await _client.get(ApiEndpoints.myEvaluations);
      if (res.data is Map &&
          (res.data as Map<String, dynamic>)['data'] is Map) {
        return Map<String, dynamic>.from(
          (res.data as Map<String, dynamic>)['data'],
        );
      }
    } catch (_) {}
    return null;
  }
}
