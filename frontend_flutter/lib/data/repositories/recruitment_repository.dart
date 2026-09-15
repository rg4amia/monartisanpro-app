import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../models/recruitment_application_model.dart';
import '../models/recruitment_offer_model.dart';

class RecruitmentRepository {
  final ApiClient _client = ApiClient();

  List<Map<String, dynamic>> _asMapList(dynamic data) {
    final list = data is Map<String, dynamic> && data['data'] is Map<String, dynamic>
        ? (data['data'] as Map<String, dynamic>)['data']
        : null;
    if (list is! List) return const [];
    return list.whereType<Map<String, dynamic>>().toList();
  }

  /// Offres actives, filtrables par métier et commune (première page).
  Future<List<RecruitmentOfferModel>> listOffers({
    int? tradeId,
    String? commune,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.get(
        ApiEndpoints.recruitmentOffers,
        params: {
          if (tradeId != null) 'trade_id': tradeId,
          if (commune != null && commune.isNotEmpty) 'commune': commune,
        },
      ),
    );
    return _asMapList(res.data).map(RecruitmentOfferModel.fromJson).toList();
  }

  /// Offres publiées par l'utilisateur connecté (client, fournisseur, admin).
  Future<List<RecruitmentOfferModel>> myOffers() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.myRecruitmentOffers),
    );
    return _asMapList(res.data).map(RecruitmentOfferModel.fromJson).toList();
  }

  /// Candidatures de l'artisan connecté.
  Future<List<RecruitmentApplicationModel>> myApplications() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.myRecruitmentApplications),
    );
    return _asMapList(res.data)
        .map(RecruitmentApplicationModel.fromJson)
        .toList();
  }

  Future<RecruitmentOfferModel> createOffer({
    required int tradeId,
    required String title,
    required String description,
    required String missionType,
    required String commune,
    String? sousQuartier,
    int? dailyRateMin,
    int? dailyRateMax,
    int openingsCount = 1,
    String? deadlineAt,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.recruitmentOffers,
        data: {
          'trade_id': tradeId,
          'title': title,
          'description': description,
          'mission_type': missionType,
          'commune': commune,
          if (sousQuartier != null && sousQuartier.isNotEmpty)
            'sous_quartier': sousQuartier,
          if (dailyRateMin != null) 'daily_rate_min': dailyRateMin,
          if (dailyRateMax != null) 'daily_rate_max': dailyRateMax,
          'openings_count': openingsCount,
          if (deadlineAt != null) 'deadline_at': deadlineAt,
        },
      ),
    );
    final data = res.data;
    final offerJson = data is Map<String, dynamic> && data['data'] is Map<String, dynamic>
        ? data['data'] as Map<String, dynamic>
        : <String, dynamic>{};
    return RecruitmentOfferModel.fromJson(offerJson);
  }

  Future<void> applyToOffer(int offerId) async {
    await NetworkExecutor.run(
      () => _client.post(ApiEndpoints.recruitmentOfferApply(offerId)),
    );
  }
}
