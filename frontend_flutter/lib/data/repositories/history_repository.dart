import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/utils/json_readers.dart';
import '../models/history_models.dart';
import '../models/payout_model.dart';

/// Historiques consultés depuis « Mon historique » (Chantier 20).
///
/// Aucun repli sur un cache : un échec remonte au contrôleur, qui l'annonce.
/// Une panne ne passe jamais pour un historique vide (Règle d'or 29).
class HistoryRepository {
  HistoryRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  static const int pageSize = 20;

  Future<HistoryPage<T>> _page<T>(
    String path,
    T Function(Map<String, dynamic>) fromJson, {
    required int page,
    Map<String, dynamic> query = const {},
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.get(
        path,
        params: {
          'page': page,
          'per_page': pageSize,
          ...query,
        },
      ),
    );

    return HistoryPage<T>.fromResponse(res.data, fromJson);
  }

  /// Changements d'état d'une mission, du plus récent au plus ancien.
  Future<List<MissionStateLine>> missionTimeline(int missionId) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.missionStateHistory(missionId)),
    );
    final data = readMap(readMap(res.data)?['data']);
    final lines = readList(data?['transitions']);
    if (lines == null) {
      throw const FormatException('Réponse inattendue du serveur.');
    }

    return readMapList(lines).map(MissionStateLine.fromJson).toList();
  }

  /// Litiges du client ou de l'artisan connecté. [statut] : `ouvert`,
  /// `en_cours`, `resolu`, ou `null` pour tous.
  Future<HistoryPage<LitigeSummary>> myLitiges({
    String? statut,
    int page = 1,
  }) {
    return _page(
      ApiEndpoints.litiges,
      LitigeSummary.fromJson,
      page: page,
      query: {if (statut != null) 'statut': statut},
    );
  }

  Future<HistoryPage<ReferentLitige>> referentLitiges({
    String? statut,
    int page = 1,
  }) {
    return _page(
      ApiEndpoints.referentLitiges,
      ReferentLitige.fromJson,
      page: page,
      query: {if (statut != null) 'statut': statut},
    );
  }

  Future<HistoryPage<ReferentInspection>> referentInspections({int page = 1}) {
    return _page(
      ApiEndpoints.referentInspections,
      ReferentInspection.fromJson,
      page: page,
    );
  }

  /// Litiges des commandes de l'utilisateur (client, fournisseur, livreur),
  /// avec leur issue. [statut] : `ouvert`, `resolu`, ou `null` pour tous.
  Future<HistoryPage<OrderDisputeRecord>> orderDisputes({
    String? statut,
    int page = 1,
  }) {
    return _page(
      ApiEndpoints.orderDisputes,
      OrderDisputeRecord.fromJson,
      page: page,
      query: {if (statut != null) 'statut': statut},
    );
  }

  /// Historique complet des versements Mobile Money de l'utilisateur.
  /// [statut] : `verse`, `en_cours`, `echoue`, `annule`, ou `null` pour tous.
  Future<HistoryPage<PayoutModel>> payouts({String? statut, int page = 1}) {
    return _page(
      ApiEndpoints.payouts,
      PayoutModel.fromJson,
      page: page,
      query: {if (statut != null) 'statut': statut},
    );
  }

  /// Courses du livreur connecté dans les états demandés.
  Future<HistoryPage<DeliveryRecord>> deliveries({
    required List<String> statuses,
    int page = 1,
  }) {
    return _page(
      ApiEndpoints.orders,
      DeliveryRecord.fromJson,
      page: page,
      query: {'status': statuses.join(',')},
    );
  }
}
