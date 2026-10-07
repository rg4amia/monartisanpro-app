import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/utils/json_readers.dart';

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

    return requireMap(res.data);
  }

  /// `null` quand la réponse ne porte rien ; une panne remonte.
  Future<Map<String, dynamic>?> getMissionActors(int missionId) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.missionEvaluationsStatus(missionId)),
    );

    return readMap(readMap(res.data)?['data']);
  }

  /// `null` quand la réponse ne porte rien ; une panne remonte.
  Future<Map<String, dynamic>?> getOrderActors(int orderId) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.orderEvaluationsStatus(orderId)),
    );

    return readMap(readMap(res.data)?['data']);
  }

  /// `null` quand la réponse ne porte rien ; une panne remonte.
  Future<Map<String, dynamic>?> getMyEvaluations() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.myEvaluations),
    );

    return readMap(readMap(res.data)?['data']);
  }
}
