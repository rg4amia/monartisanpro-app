import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/utils/json_readers.dart';
import '../models/jury_dossier_model.dart';

/// Espace juré (Chantier 12) : dossiers d'arbitrage anonymisés et vote.
///
/// Sans cache local : un dossier doit refléter l'état réel (délai de 48 h,
/// vote déjà rendu), et une panne s'affiche comme telle (Règle d'or 75).
class JuryRepository {
  JuryRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<List<JuryDossierSummary>> getDossiers() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.juryDossiers, params: {'per_page': 50}),
    );
    // Réponse paginée : `{data: {data: [...], current_page: …}}`.
    return readDataList(readMap(res.data)?['data'])
        .map(JuryDossierSummary.fromJson)
        .toList(growable: false);
  }

  Future<JuryDossierDetail> getDossier(int litigeId) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.juryDossier(litigeId)),
    );
    final data = readMap(readMap(res.data)?['data']);
    if (data == null) {
      throw const FormatException('Dossier absent de la réponse.');
    }

    return JuryDossierDetail.fromJson(data);
  }

  /// Vote motivé, définitif. Renvoie le message du serveur.
  Future<String> vote(
    int litigeId, {
    required String verdict,
    int? splitArtisanPercentage,
    String? technicalComment,
  }) async {
    final res = await _client.post(
      ApiEndpoints.juryDossierVote(litigeId),
      data: {
        'verdict': verdict,
        if (splitArtisanPercentage != null)
          'split_artisan_percentage': splitArtisanPercentage,
        if (technicalComment != null && technicalComment.trim().isNotEmpty)
          'technical_comment': technicalComment.trim(),
      },
    );

    return readApiMessage(readMap(res.data)) ?? 'Votre avis a été enregistré.';
  }
}
