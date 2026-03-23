import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';

class EvaluationRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>> submit({
    required int missionId,
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
        'mission_id': missionId,
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
}
